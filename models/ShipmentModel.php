<?php

class ShipmentModel extends BaseModel {
  private function markProcurementReceived($procurementId, $warehouseUserId) {
    $stmt = $this->pdo->prepare(
      "UPDATE procurements
       SET status = 'delivered', received_by = ?
       WHERE id = ?"
    );
    $stmt->execute([(int) $warehouseUserId, (int) $procurementId]);
  }

  private function applyDeliveredStockToInventory($procurementId, $location = null) {
    $itemsStmt = $this->pdo->prepare(
      "SELECT product_id, quantity
       FROM procurement_items
       WHERE procurement_id = ?"
    );
    $itemsStmt->execute([(int) $procurementId]);
    $items = $itemsStmt->fetchAll();

    if (!$items) return;

    $upsertInventory = $this->pdo->prepare(
      "INSERT INTO inventory (product_id, stock_quantity, location)
       VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE
         stock_quantity = stock_quantity + VALUES(stock_quantity),
         location = COALESCE(VALUES(location), location),
         last_updated = CURRENT_TIMESTAMP"
    );

    $updateProductStock = $this->pdo->prepare(
      "UPDATE products
       SET stock_quantity = stock_quantity + ?
       WHERE id = ?"
    );

    foreach ($items as $item) {
      $productId = isset($item['product_id']) ? (int) $item['product_id'] : 0;
      $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;

      if ($productId < 1 || $quantity <= 0) {
        continue;
      }

      $upsertInventory->execute([$productId, $quantity, $location]);
      $updateProductStock->execute([$quantity, $productId]);
    }
  }

  public function listShipments($status = null, $supplierId = null) {
    $sql = "SELECT sh.id, sh.procurement_id, sh.warehouse_user_id, w.name AS warehouse_name, p.order_date, sh.tracking_number, sh.carrier,
                   sh.origin, sh.destination, sh.shipped_at, sh.expected_delivery,
                   sh.delivered_at, sh.status, sh.notes, sh.created_at, sh.updated_at
            FROM shipments sh
            INNER JOIN procurements p ON p.id = sh.procurement_id
            LEFT JOIN users w ON w.id = sh.warehouse_user_id";

    $params = [];
    if (!empty($supplierId)) {
      $sql .= " WHERE p.supplier_id = ?";
      $params[] = (int) $supplierId;
    }

    if (!empty($status)) {
      $sql .= empty($params) ? " WHERE sh.status = ?" : " AND sh.status = ?";
      $params[] = $status;
    }

    $sql .= " ORDER BY sh.id DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public function getShipment($id, $supplierId = null) {
    $params = [(int) $id];
    $supplierFilter = '';
    if (!empty($supplierId)) {
      $supplierFilter = ' AND p.supplier_id = ?';
      $params[] = (int) $supplierId;
    }

    $stmt = $this->pdo->prepare(
      "SELECT sh.id, sh.procurement_id, sh.warehouse_user_id, w.name AS warehouse_name, sh.tracking_number, sh.carrier, sh.origin, sh.destination,
              sh.shipped_at, sh.expected_delivery, sh.delivered_at, sh.status, sh.notes, sh.created_at, sh.updated_at
       FROM shipments sh
       INNER JOIN procurements p ON p.id = sh.procurement_id
       LEFT JOIN users w ON w.id = sh.warehouse_user_id
       WHERE sh.id = ?" . $supplierFilter
    );
    $stmt->execute($params);
    return $stmt->fetch();
  }


  
  public function updateShipmentStatusForSupplier($id, $supplierId, $dt) {
    if (empty($dt->status)) {
      throw new InvalidArgumentException('status is required');
    }

    $this->statusAllowed($dt->status, ['shipped', 'in_transit', 'delivered'], 'shipment status');

    $shipment = $this->getShipment($id, $supplierId);
    if (!$shipment) {
      return null;
    }

    $deliveredAt = $shipment['delivered_at'];
    $isDeliveringNow = $dt->status === 'delivered' && $shipment['status'] !== 'delivered';
    if ($dt->status === 'delivered') {
      $deliveredAt = date('Y-m-d H:i:s');
    }

    $this->pdo->beginTransaction();
    try {
      $stmt = $this->pdo->prepare(
        'UPDATE shipments
         SET status = ?, notes = ?, delivered_at = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
      );

      $stmt->execute([
        $dt->status,
        $dt->notes ?? $shipment['notes'],
        $deliveredAt,
        (int) $id
      ]);

      if ($isDeliveringNow) {
        $this->applyDeliveredStockToInventory((int) $shipment['procurement_id'], $shipment['destination'] ?? null);
      }

      $this->pdo->commit();
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }

    return $this->getShipment($id, $supplierId);
  }

  public function createShipment($dt, $supplierId = null) {
    if (
      empty($dt->procurement_id) || empty($dt->tracking_number) || empty($dt->carrier) ||
      empty($dt->origin) || empty($dt->destination) || empty($dt->warehouse_user_id)
    ) {
      throw new InvalidArgumentException("procurement_id, warehouse_user_id, tracking_number, carrier, origin and destination are required");
    }

    $status = $dt->status ?? 'pending';
    $this->statusAllowed($status, ['pending', 'approved', 'shipped', 'in_transit', 'delivered', 'cancelled'], 'shipment status');

    if (!empty($supplierId)) {
      $procurementCheck = $this->pdo->prepare(
        "SELECT id, status
         FROM procurements
         WHERE id = ? AND supplier_id = ?
         LIMIT 1"
      );
      $procurementCheck->execute([(int) $dt->procurement_id, (int) $supplierId]);
      $ownedProcurement = $procurementCheck->fetch();
      if (!$ownedProcurement) {
        throw new InvalidArgumentException('You can only create shipments for your own procurements');
      }
      if (($ownedProcurement['status'] ?? '') !== 'approved') {
        throw new InvalidArgumentException('You can only create shipments for approved orders');
      }
    }

    $warehouseUserId = (int) $dt->warehouse_user_id;
    $warehouseStmt = $this->pdo->prepare(
      "SELECT id, name
       FROM users
       WHERE id = ? AND role = 'warehouse' AND is_active = 1
       LIMIT 1"
    );
    $warehouseStmt->execute([$warehouseUserId]);
    $warehouseUser = $warehouseStmt->fetch();
    if (!$warehouseUser) {
      throw new InvalidArgumentException('Selected warehouse user is invalid or inactive');
    }

    $duplicateStmt = $this->pdo->prepare(
      "SELECT id
       FROM shipments
       WHERE procurement_id = ?
       LIMIT 1"
    );
    $duplicateStmt->execute([(int) $dt->procurement_id]);
    if ($duplicateStmt->fetch()) {
      throw new InvalidArgumentException('A shipment already exists for this order');
    }

    $this->pdo->beginTransaction();
    try {
      $stmt = $this->pdo->prepare(
        "INSERT INTO shipments (
            procurement_id, warehouse_user_id, tracking_number, carrier, origin, destination,
            shipped_at, expected_delivery, delivered_at, status, notes
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );

      $stmt->execute([
        (int) $dt->procurement_id,
        $warehouseUserId,
        trim($dt->tracking_number),
        trim($dt->carrier),
        trim($dt->origin),
        trim($dt->destination),
        $dt->shipped_at ?? null,
        $dt->expected_delivery ?? null,
        $dt->delivered_at ?? null,
        $status,
        $dt->notes ?? null
      ]);

      $shipmentId = (int) $this->pdo->lastInsertId();
      if ($status === 'delivered') {
        $this->applyDeliveredStockToInventory((int) $dt->procurement_id, trim($dt->destination));
      }

      $this->pdo->commit();
      return $this->getShipment($shipmentId);
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }





  public function updateShipment($id, $dt, $warehouseUserId = null) {
    $current = $this->getShipment($id);
    if (!$current) {
      return null;
    }

    $status = $dt->status ?? $current['status'];
    $this->statusAllowed($status, ['pending', 'approved', 'shipped', 'in_transit', 'delivered', 'cancelled'], 'shipment status');
    $isDeliveringNow = $status === 'delivered' && $current['status'] !== 'delivered';

    $nextDestination = trim($dt->destination ?? $current['destination']);

    $this->pdo->beginTransaction();
    try {
      $stmt = $this->pdo->prepare(
        "UPDATE shipments
         SET tracking_number = ?, carrier = ?, origin = ?, destination = ?,
             shipped_at = ?, expected_delivery = ?, delivered_at = ?, status = ?, notes = ?
         WHERE id = ?"
      );

      $stmt->execute([
        trim($dt->tracking_number ?? $current['tracking_number']),
        trim($dt->carrier ?? $current['carrier']),
        trim($dt->origin ?? $current['origin']),
        $nextDestination,
        $dt->shipped_at ?? $current['shipped_at'],
        $dt->expected_delivery ?? $current['expected_delivery'],
        $dt->delivered_at ?? $current['delivered_at'],
        $status,
        $dt->notes ?? $current['notes'],
        (int) $id
      ]);

      if ($isDeliveringNow) {
        $this->applyDeliveredStockToInventory((int) $current['procurement_id'], $nextDestination ?: null);
        if (!empty($warehouseUserId)) {
          $this->markProcurementReceived((int) $current['procurement_id'], (int) $warehouseUserId);
        }
      }

      $this->pdo->commit();
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }

    return $this->getShipment($id);
  }




  public function deleteShipment($id) {
    $stmt = $this->pdo->prepare("DELETE FROM shipments WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }
}
