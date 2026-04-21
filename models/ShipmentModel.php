<?php

class ShipmentModel extends BaseModel {
  public function listShipments($status = null, $supplierId = null) {
    $sql = "SELECT sh.id, sh.procurement_id, p.order_date, sh.tracking_number, sh.carrier,
                   sh.origin, sh.destination, sh.shipped_at, sh.expected_delivery,
                   sh.delivered_at, sh.status, sh.notes, sh.created_at, sh.updated_at
            FROM shipments sh
            INNER JOIN procurements p ON p.id = sh.procurement_id";

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
      "SELECT sh.id, sh.procurement_id, sh.tracking_number, sh.carrier, sh.origin, sh.destination,
              sh.shipped_at, sh.expected_delivery, sh.delivered_at, sh.status, sh.notes, sh.created_at, sh.updated_at
       FROM shipments sh
       INNER JOIN procurements p ON p.id = sh.procurement_id
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
    if ($dt->status === 'delivered') {
      $deliveredAt = date('Y-m-d H:i:s');
    }

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

    return $this->getShipment($id, $supplierId);
  }

  public function createShipment($dt) {
    if (
      empty($dt->procurement_id) || empty($dt->tracking_number) || empty($dt->carrier) ||
      empty($dt->origin) || empty($dt->destination)
    ) {
      throw new InvalidArgumentException("procurement_id, tracking_number, carrier, origin and destination are required");
    }

    $status = $dt->status ?? 'pending';
    $this->statusAllowed($status, ['pending', 'approved', 'shipped', 'in_transit', 'delivered', 'cancelled'], 'shipment status');

    $stmt = $this->pdo->prepare(
      "INSERT INTO shipments (
          procurement_id, tracking_number, carrier, origin, destination,
          shipped_at, expected_delivery, delivered_at, status, notes
       ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
      (int) $dt->procurement_id,
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

    return $this->getShipment($this->pdo->lastInsertId());
  }





  public function updateShipment($id, $dt) {
    $current = $this->getShipment($id);
    if (!$current) {
      return null;
    }

    $status = $dt->status ?? $current['status'];
    $this->statusAllowed($status, ['pending', 'approved', 'shipped', 'in_transit', 'delivered', 'cancelled'], 'shipment status');

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
      trim($dt->destination ?? $current['destination']),
      $dt->shipped_at ?? $current['shipped_at'],
      $dt->expected_delivery ?? $current['expected_delivery'],
      $dt->delivered_at ?? $current['delivered_at'],
      $status,
      $dt->notes ?? $current['notes'],
      (int) $id
    ]);

    return $this->getShipment($id);
  }




  public function deleteShipment($id) {
    $stmt = $this->pdo->prepare("DELETE FROM shipments WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }
}
