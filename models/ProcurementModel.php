<?php

class ProcurementModel extends BaseModel {
  private $tableSupport = [];

  private function hasTable($tableName) {
    if (array_key_exists($tableName, $this->tableSupport)) {
      return $this->tableSupport[$tableName];
    }
    $stmt = $this->pdo->prepare(
      "SELECT 1
       FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = ?
       LIMIT 1"
    );
    $stmt->execute([$tableName]);
    $this->tableSupport[$tableName] = (bool) $stmt->fetchColumn();
    return $this->tableSupport[$tableName];
  }

  private function assertProcurementAccess($procurementId, $supplierId = null) {
    $params = [(int) $procurementId];
    $sql = "SELECT id, supplier_id FROM procurements WHERE id = ?";
    if (!empty($supplierId)) {
      $sql .= " AND supplier_id = ?";
      $params[] = (int) $supplierId;
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
  }

  private function recalculateProcurementTotal($procurementId) {
    $sumStmt = $this->pdo->prepare(
      "SELECT COALESCE(SUM(subtotal), 0) AS total
       FROM procurement_items
       WHERE procurement_id = ?"
    );
    $sumStmt->execute([(int) $procurementId]);
    $total = (float) ($sumStmt->fetch()['total'] ?? 0);

    $update = $this->pdo->prepare("UPDATE procurements SET total_amount = ? WHERE id = ?");
    $update->execute([round($total, 2), (int) $procurementId]);
  }

  public function listProcurements($status = null, $supplierId = null) {
    $sql = "SELECT p.id, p.supplier_id, s.name AS supplier_name, p.created_by,
             u.name AS created_by_name, p.order_date, p.expected_delivery,
             p.status, p.received_by, rw.name AS received_by_name, p.total_amount, p.created_at,
             COALESCE(pi.items_count, 0) AS items_count,
             COALESCE(pi.items_preview, '') AS items_preview
            FROM procurements p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            INNER JOIN users u ON u.id = p.created_by
            LEFT JOIN users rw ON rw.id = p.received_by
            LEFT JOIN (
              SELECT
                procurement_id,
                COUNT(*) AS items_count,
                GROUP_CONCAT(
                  CONCAT(product_name, ' x', quantity)
                  ORDER BY id ASC
                  SEPARATOR ', '
                ) AS items_preview
              FROM procurement_items
              GROUP BY procurement_id
            ) pi ON pi.procurement_id = p.id";

    $params = [];
    if (!empty($supplierId)) {
      $sql .= " WHERE p.supplier_id = ?";
      $params[] = (int) $supplierId;
    }

    if (!empty($status)) {
      $sql .= empty($params) ? " WHERE p.status = ?" : " AND p.status = ?";
      $params[] = $status;
    }

    $sql .= " ORDER BY p.id DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public function getProcurement($id, $supplierId = null) {
    $params = [(int) $id];
    $supplierFilter = '';
    if (!empty($supplierId)) {
      $supplierFilter = ' AND p.supplier_id = ?';
      $params[] = (int) $supplierId;
    }

    $stmt = $this->pdo->prepare(
            "SELECT p.id, p.supplier_id, s.name AS supplier_name, p.created_by,
              u.name AS created_by_name, p.order_date, p.expected_delivery,
              p.status, p.received_by, rw.name AS received_by_name, p.total_amount, p.created_at
       FROM procurements p
       INNER JOIN suppliers s ON s.id = p.supplier_id
       INNER JOIN users u ON u.id = p.created_by
       LEFT JOIN users rw ON rw.id = p.received_by
       WHERE p.id = ?" . $supplierFilter
    );

    $stmt->execute($params);
    $procurement = $stmt->fetch();
    if (!$procurement) {
      return null;
    }

    $items = $this->pdo->prepare(
      "SELECT id, procurement_id, product_id, product_name, quantity, unit_price, subtotal
       FROM procurement_items
       WHERE procurement_id = ?
       ORDER BY id ASC"
    );
    $items->execute([(int) $id]);

    $shipment = $this->pdo->prepare(
      "SELECT id, procurement_id, tracking_number, carrier, origin, destination,
              shipped_at, expected_delivery, delivered_at, status, notes, created_at, updated_at
       FROM shipments
       WHERE procurement_id = ?"
    );
    $shipment->execute([(int) $id]);

    $procurement['items'] = $items->fetchAll();
    $procurement['shipment'] = $shipment->fetch();

    return $procurement;
  }

  public function createProcurement($dt) {
    if (empty($dt->supplier_id) || empty($dt->created_by) || empty($dt->order_date) || empty($dt->items)) {
      throw new InvalidArgumentException("supplier_id, created_by, order_date and items are required");
    }

    if (!is_array($dt->items) || count($dt->items) < 1) {
      throw new InvalidArgumentException("items must be a non-empty array");
    }

    $status = $dt->status ?? 'pending';
    $this->statusAllowed($status, ['pending', 'approved', 'shipped', 'delivered', 'cancelled'], 'procurement status');

    $this->pdo->beginTransaction();
    try {
      $insertProcurement = $this->pdo->prepare(
        "INSERT INTO procurements (supplier_id, created_by, order_date, expected_delivery, status, total_amount)
         VALUES (?, ?, ?, ?, ?, 0.00)"
      );
      $insertProcurement->execute([
        (int) $dt->supplier_id,
        (int) $dt->created_by,
        $dt->order_date,
        $dt->expected_delivery ?? null,
        $status
      ]);

      $procurementId = (int) $this->pdo->lastInsertId();
      $insertItem = $this->pdo->prepare(
        "INSERT INTO procurement_items (procurement_id, product_id, product_name, quantity, unit_price, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)"
      );
      $upsertSupplierProduct = null;
      if ($this->hasTable('supplier_products')) {
        $upsertSupplierProduct = $this->pdo->prepare(
          "INSERT INTO supplier_products (supplier_id, product_id, unit_price)
           VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE unit_price = VALUES(unit_price), updated_at = CURRENT_TIMESTAMP"
        );
      }

      $total = 0.00;
      foreach ($dt->items as $item) {
        if (empty($item->product_name) || empty($item->quantity) || !isset($item->unit_price)) {
          throw new InvalidArgumentException("Each item needs product_name, quantity and unit_price");
        }

        $quantity = (int) $item->quantity;
        $unitPrice = (float) $item->unit_price;
        if ($quantity <= 0 || $unitPrice < 0) {
          throw new InvalidArgumentException("Item quantity must be > 0 and unit_price must be >= 0");
        }

        $subtotal = round($quantity * $unitPrice, 2);
        $total += $subtotal;

        $insertItem->execute([
          $procurementId,
          isset($item->product_id) ? (int) $item->product_id : null,
          trim($item->product_name),
          $quantity,
          $unitPrice,
          $subtotal
        ]);

        $productId = isset($item->product_id) ? (int) $item->product_id : 0;
        if ($productId > 0 && $upsertSupplierProduct !== null) {
          $upsertSupplierProduct->execute([
            (int) $dt->supplier_id,
            $productId,
            $unitPrice,
          ]);
        }
      }

      $updateTotal = $this->pdo->prepare("UPDATE procurements SET total_amount = ? WHERE id = ?");
      $updateTotal->execute([round($total, 2), $procurementId]);

      $this->pdo->commit();
      return $this->getProcurement($procurementId);
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  public function updateProcurementStatus($id, $dt) {
    if (empty($dt->status)) {
      throw new InvalidArgumentException("status is required");
    }

    $this->statusAllowed($dt->status, ['pending', 'approved', 'shipped', 'delivered', 'cancelled'], 'procurement status');

    $current = $this->getProcurement($id);
    if (!$current) {
      return null;
    }

    $receivedBy = isset($dt->received_by) ? (int) $dt->received_by : ($current['received_by'] ?? null);

    $stmt = $this->pdo->prepare(
      "UPDATE procurements
       SET status = ?, received_by = ?
       WHERE id = ?"
    );
    $stmt->execute([$dt->status, $receivedBy, (int) $id]);

    if ($stmt->rowCount() < 1) {
      return null;
    }

    return $this->getProcurement($id);
  }

  public function updateProcurementStatusForSupplier($id, $supplierId, $dt) {
    if (empty($dt->status)) {
      throw new InvalidArgumentException("status is required");
    }
    $nextStatus = (string) $dt->status;
    $this->statusAllowed($nextStatus, ['approved', 'cancelled'], 'procurement status');

    $current = $this->getProcurement($id, $supplierId);
    if (!$current) {
      return null;
    }
    $currentStatus = (string) ($current['status'] ?? '');
    if ($nextStatus === 'approved' && $currentStatus !== 'pending') {
      throw new InvalidArgumentException("Supplier can only approve pending orders");
    }
    if ($nextStatus === 'cancelled' && in_array($currentStatus, ['delivered', 'cancelled'], true)) {
      throw new InvalidArgumentException("This order can no longer be cancelled");
    }

    $stmt = $this->pdo->prepare(
      "UPDATE procurements
       SET status = ?
       WHERE id = ? AND supplier_id = ?"
    );
    $stmt->execute([$nextStatus, (int) $id, (int) $supplierId]);

    return $this->getProcurement($id, $supplierId);
  }

  public function receiveProcurementByWarehouse($id, $warehouseUserId) {
    $current = $this->getProcurement($id);
    if (!$current) {
      return null;
    }

    $stmt = $this->pdo->prepare(
      "UPDATE procurements
       SET status = 'delivered', received_by = ?
       WHERE id = ?"
    );
    $stmt->execute([(int) $warehouseUserId, (int) $id]);
    return $this->getProcurement($id);
  }

  public function deleteProcurement($id) {
    $stmt = $this->pdo->prepare("DELETE FROM procurements WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }

  public function listProcurementItems($procurementId, $supplierId = null) {
    $procurement = $this->assertProcurementAccess($procurementId, $supplierId);
    if (!$procurement) {
      return null;
    }

    $stmt = $this->pdo->prepare(
      "SELECT id, procurement_id, product_id, product_name, quantity, unit_price, subtotal
       FROM procurement_items
       WHERE procurement_id = ?
       ORDER BY id ASC"
    );
    $stmt->execute([(int) $procurementId]);
    return $stmt->fetchAll();
  }

  public function createProcurementItem($procurementId, $dt, $supplierId = null) {
    $procurement = $this->assertProcurementAccess($procurementId, $supplierId);
    if (!$procurement) {
      return null;
    }
    if (empty($dt->product_name) || !isset($dt->quantity) || !isset($dt->unit_price)) {
      throw new InvalidArgumentException("product_name, quantity and unit_price are required");
    }

    $quantity = (int) $dt->quantity;
    $unitPrice = (float) $dt->unit_price;
    if ($quantity <= 0 || $unitPrice < 0) {
      throw new InvalidArgumentException("quantity must be > 0 and unit_price must be >= 0");
    }

    $subtotal = round($quantity * $unitPrice, 2);
    $insert = $this->pdo->prepare(
      "INSERT INTO procurement_items (procurement_id, product_id, product_name, quantity, unit_price, subtotal)
       VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert->execute([
      (int) $procurementId,
      isset($dt->product_id) ? (int) $dt->product_id : null,
      trim($dt->product_name),
      $quantity,
      $unitPrice,
      $subtotal,
    ]);

    $this->recalculateProcurementTotal($procurementId);

    $stmt = $this->pdo->prepare(
      "SELECT id, procurement_id, product_id, product_name, quantity, unit_price, subtotal
       FROM procurement_items
       WHERE id = ? AND procurement_id = ?"
    );
    $stmt->execute([(int) $this->pdo->lastInsertId(), (int) $procurementId]);
    return $stmt->fetch();
  }

  public function updateProcurementItem($procurementId, $itemId, $dt, $supplierId = null) {
    $procurement = $this->assertProcurementAccess($procurementId, $supplierId);
    if (!$procurement) {
      return null;
    }

    $get = $this->pdo->prepare(
      "SELECT id, procurement_id, product_id, product_name, quantity, unit_price, subtotal
       FROM procurement_items
       WHERE id = ? AND procurement_id = ?"
    );
    $get->execute([(int) $itemId, (int) $procurementId]);
    $current = $get->fetch();
    if (!$current) {
      return null;
    }

    $productName = isset($dt->product_name) ? trim((string) $dt->product_name) : $current['product_name'];
    $quantity = isset($dt->quantity) ? (int) $dt->quantity : (int) $current['quantity'];
    $unitPrice = isset($dt->unit_price) ? (float) $dt->unit_price : (float) $current['unit_price'];
    $productId = property_exists($dt, 'product_id') ? ($dt->product_id !== null ? (int) $dt->product_id : null) : $current['product_id'];

    if ($productName === '' || $quantity <= 0 || $unitPrice < 0) {
      throw new InvalidArgumentException("product_name must not be empty, quantity must be > 0 and unit_price must be >= 0");
    }

    $subtotal = round($quantity * $unitPrice, 2);

    $update = $this->pdo->prepare(
      "UPDATE procurement_items
       SET product_id = ?, product_name = ?, quantity = ?, unit_price = ?, subtotal = ?
       WHERE id = ? AND procurement_id = ?"
    );
    $update->execute([$productId, $productName, $quantity, $unitPrice, $subtotal, (int) $itemId, (int) $procurementId]);

    $this->recalculateProcurementTotal($procurementId);

    $get->execute([(int) $itemId, (int) $procurementId]);
    return $get->fetch();
  }

  public function deleteProcurementItem($procurementId, $itemId, $supplierId = null) {
    $procurement = $this->assertProcurementAccess($procurementId, $supplierId);
    if (!$procurement) {
      return null;
    }

    $delete = $this->pdo->prepare("DELETE FROM procurement_items WHERE id = ? AND procurement_id = ?");
    $delete->execute([(int) $itemId, (int) $procurementId]);
    $deleted = $delete->rowCount() > 0;
    if ($deleted) {
      $this->recalculateProcurementTotal($procurementId);
    }
    return ['deleted' => $deleted];
  }

  public function adminSuppliers() {
    $stmt = $this->pdo->query(
      "SELECT s.id, s.name, s.contact_person, s.email, s.phone, s.status, s.created_at,
              COUNT(p.id) AS total_orders,
              COALESCE(SUM(p.total_amount), 0) AS total_spend
       FROM suppliers s
       LEFT JOIN procurements p ON p.supplier_id = s.id
       GROUP BY s.id, s.name, s.contact_person, s.email, s.phone, s.status, s.created_at
       ORDER BY s.id DESC"
    );
    return $stmt->fetchAll();
  }

  public function adminOrders() {
    $stmt = $this->pdo->query(
      "SELECT p.id, p.order_date, p.expected_delivery, p.status, p.total_amount, p.created_at,
              s.id AS supplier_id, s.name AS supplier_name,
              u.id AS created_by, u.name AS created_by_name,
              COUNT(pi.id) AS item_count
       FROM procurements p
       INNER JOIN suppliers s ON s.id = p.supplier_id
       INNER JOIN users u ON u.id = p.created_by
       LEFT JOIN procurement_items pi ON pi.procurement_id = p.id
       GROUP BY p.id, p.order_date, p.expected_delivery, p.status, p.total_amount, p.created_at,
                s.id, s.name, u.id, u.name
       ORDER BY p.id DESC"
    );
    return $stmt->fetchAll();
  }

  public function reportOrderStatus() {
    $stmt = $this->pdo->query(
      "SELECT status, COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_amount
       FROM procurements
       GROUP BY status
       ORDER BY status ASC"
    );
    return $stmt->fetchAll();
  }

  public function reportSupplierPerformance() {
    $stmt = $this->pdo->query(
      "SELECT s.id AS supplier_id, s.name AS supplier_name,
              COUNT(p.id) AS total_orders,
              COALESCE(SUM(p.total_amount), 0) AS total_amount,
              COALESCE(SUM(CASE WHEN p.status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_orders,
              COALESCE(SUM(CASE WHEN p.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_orders
       FROM suppliers s
       LEFT JOIN procurements p ON p.supplier_id = s.id
       GROUP BY s.id, s.name
       ORDER BY total_amount DESC, total_orders DESC"
    );
    return $stmt->fetchAll();
  }
}
