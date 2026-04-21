<?php

class ProcurementModel extends BaseModel {
  public function listProcurements($status = null, $supplierId = null) {
    $sql = "SELECT p.id, p.supplier_id, s.name AS supplier_name, p.created_by,
             u.name AS created_by_name, p.order_date, p.expected_delivery,
             p.status, p.received_by, p.total_amount, p.created_at
            FROM procurements p
            INNER JOIN suppliers s ON s.id = p.supplier_id
            INNER JOIN users u ON u.id = p.created_by";

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
              p.status, p.received_by, p.total_amount, p.created_at
       FROM procurements p
       INNER JOIN suppliers s ON s.id = p.supplier_id
       INNER JOIN users u ON u.id = p.created_by
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

  public function deleteProcurement($id) {
    $stmt = $this->pdo->prepare("DELETE FROM procurements WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
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
