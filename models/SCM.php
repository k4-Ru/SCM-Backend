<?php

class SCM {
  private $pdo;

  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

  private function paginate($page, $limit) {
    $page = max(1, (int) $page);
    $limit = max(1, min(100, (int) $limit));
    $offset = ($page - 1) * $limit;
    return [$limit, $offset, $page];
  }

  private function statusAllowed($value, $allowed, $field) {
    if (!in_array($value, $allowed, true)) {
      throw new InvalidArgumentException("Invalid {$field} value");
    }
  }

  public function listUsers($page = 1, $limit = 20) {
    list($limit, $offset) = $this->paginate($page, $limit);

    $stmt = $this->pdo->prepare(
      "SELECT id, name, email, role, supplier_id, is_active, last_login, created_at, updated_at
       FROM users
       ORDER BY id DESC
       LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  public function getUser($id) {
    $stmt = $this->pdo->prepare(
      "SELECT id, name, email, role, supplier_id, is_active, last_login, created_at, updated_at
       FROM users
       WHERE id = ?"
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
  }

  public function getProfile($userId) {
    return $this->getUser((int) $userId);
  }

  public function updateProfile($userId, $dt) {
    $current = $this->getUser($userId);
    if (!$current) {
      return null;
    }

    $name = isset($dt->name) ? trim($dt->name) : $current['name'];
    $email = isset($dt->email) ? strtolower(trim($dt->email)) : $current['email'];

    if ($email !== $current['email']) {
      $check = $this->pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $check->execute([$email, (int) $userId]);
      if ($check->fetch()) {
        throw new RuntimeException('Email already exists');
      }
    }

    if (!empty($dt->password)) {
      $stmt = $this->pdo->prepare(
        'UPDATE users
         SET name = ?, email = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
      );
      $stmt->execute([
        $name,
        $email,
        password_hash($dt->password, PASSWORD_DEFAULT),
        (int) $userId
      ]);
    } else {
      $stmt = $this->pdo->prepare(
        'UPDATE users
         SET name = ?, email = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
      );
      $stmt->execute([$name, $email, (int) $userId]);
    }

    return $this->getUser((int) $userId);
  }

  public function createUser($dt) {
    if (empty($dt->name) || empty($dt->email) || empty($dt->password)) {
      throw new InvalidArgumentException("name, email and password are required");
    }

    $role = $dt->role ?? 'viewer';
    $this->statusAllowed($role, ['admin', 'procurement', 'warehouse', 'logistics', 'viewer', 'supplier'], 'role');
    $isActive = isset($dt->is_active) ? (int) ((bool) $dt->is_active) : 1;
    $supplierId = isset($dt->supplier_id) ? (int) $dt->supplier_id : null;

    if ($role === 'supplier' && empty($supplierId)) {
      throw new InvalidArgumentException('supplier_id is required for supplier role');
    }

    $stmt = $this->pdo->prepare(
      "INSERT INTO users (name, email, password_hash, role, supplier_id, is_active)
       VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
      trim($dt->name),
      strtolower(trim($dt->email)),
      password_hash($dt->password, PASSWORD_DEFAULT),
      $role,
      $supplierId,
      $isActive
    ]);

    return $this->getUser($this->pdo->lastInsertId());
  }

  public function updateUser($id, $dt) {
    $current = $this->getUser($id);
    if (!$current) {
      return null;
    }

    $name = $dt->name ?? $current['name'];
    $email = isset($dt->email) ? strtolower(trim($dt->email)) : $current['email'];
    $role = $dt->role ?? $current['role'];
    $isActive = isset($dt->is_active) ? (int) ((bool) $dt->is_active) : (int) $current['is_active'];

    $this->statusAllowed($role, ['admin', 'procurement', 'warehouse', 'logistics', 'viewer', 'supplier'], 'role');
    $supplierId = isset($dt->supplier_id) ? (int) $dt->supplier_id : ($current['supplier_id'] ?? null);

    if ($role === 'supplier' && empty($supplierId)) {
      throw new InvalidArgumentException('supplier_id is required for supplier role');
    }

    if ($role !== 'supplier') {
      $supplierId = null;
    }

    if (!empty($dt->password)) {
      $stmt = $this->pdo->prepare(
        "UPDATE users
         SET name = ?, email = ?, password_hash = ?, role = ?, supplier_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
      );

      $stmt->execute([
        trim($name),
        $email,
        password_hash($dt->password, PASSWORD_DEFAULT),
        $role,
        $supplierId,
        $isActive,
        (int) $id
      ]);
    } else {
      $stmt = $this->pdo->prepare(
        "UPDATE users
         SET name = ?, email = ?, role = ?, supplier_id = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?"
      );

      $stmt->execute([
        trim($name),
        $email,
        $role,
        $supplierId,
        $isActive,
        (int) $id
      ]);
    }

    return $this->getUser($id);
  }

  public function deleteUser($id) {    //not yet used on clientside
    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }




  public function listSuppliers() {
    $stmt = $this->pdo->query(
      "SELECT id, name, contact_person, email, phone, address, status, created_at
       FROM suppliers
       ORDER BY id DESC"
    );
    return $stmt->fetchAll();
  }

  public function getSupplier($id) {
    $stmt = $this->pdo->prepare(
      "SELECT id, name, contact_person, email, phone, address, status, created_at
       FROM suppliers
       WHERE id = ?"
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
  }

  public function createSupplier($dt) {
    if (empty($dt->name)) {
      throw new InvalidArgumentException("name is required");
    }

    $status = $dt->status ?? 'active';
    $this->statusAllowed($status, ['active', 'inactive'], 'supplier status');

    $stmt = $this->pdo->prepare(
      "INSERT INTO suppliers (name, contact_person, email, phone, address, status)
       VALUES (?, ?, ?, ?, ?, ?)"
    );

    $stmt->execute([
      trim($dt->name),
      $dt->contact_person ?? null,
      isset($dt->email) ? strtolower(trim($dt->email)) : null,
      $dt->phone ?? null,
      $dt->address ?? null,
      $status
    ]);

    return $this->getSupplier($this->pdo->lastInsertId());
  }

  public function updateSupplier($id, $dt) {
    $current = $this->getSupplier($id);
    if (!$current) {
      return null;
    }

    $status = $dt->status ?? $current['status'];
    $this->statusAllowed($status, ['active', 'inactive'], 'supplier status');

    $stmt = $this->pdo->prepare(
      "UPDATE suppliers
       SET name = ?, contact_person = ?, email = ?, phone = ?, address = ?, status = ?
       WHERE id = ?"
    );

    $stmt->execute([
      trim($dt->name ?? $current['name']),
      $dt->contact_person ?? $current['contact_person'],
      isset($dt->email) ? strtolower(trim($dt->email)) : $current['email'],
      $dt->phone ?? $current['phone'],
      $dt->address ?? $current['address'],
      $status,
      (int) $id
    ]);

    return $this->getSupplier($id);
  }

  public function deleteSupplier($id) {
    $stmt = $this->pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }

  public function listProcurements($status = null, $supplierId = null) {
    $sql = "SELECT p.id, p.supplier_id, s.name AS supplier_name, p.created_by,
                   u.name AS created_by_name, p.order_date, p.expected_delivery,
                   p.status, p.total_amount, p.created_at
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
              p.status, p.total_amount, p.created_at
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
      "SELECT id, procurement_id, product_name, quantity, unit_price, subtotal
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
        "INSERT INTO procurement_items (procurement_id, product_name, quantity, unit_price, subtotal)
         VALUES (?, ?, ?, ?, ?)"
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

    $stmt = $this->pdo->prepare("UPDATE procurements SET status = ? WHERE id = ?");
    $stmt->execute([$dt->status, (int) $id]);

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
