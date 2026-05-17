<?php

class SupplierModel extends BaseModel {
  private $hasSupplierProductsTable = null;

  private function supplierProductsTableExists() {
    if ($this->hasSupplierProductsTable !== null) {
      return $this->hasSupplierProductsTable;
    }
    $stmt = $this->pdo->prepare(
      "SELECT 1
       FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'supplier_products'
       LIMIT 1"
    );
    $stmt->execute();
    $this->hasSupplierProductsTable = (bool) $stmt->fetchColumn();
    return $this->hasSupplierProductsTable;
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
    $this->statusAllowed($status, ['pending', 'active', 'inactive'], 'supplier status');

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
    $this->statusAllowed($status, ['pending', 'active', 'inactive'], 'supplier status');

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

  public function listSupplierProducts($id) {
    if ($this->supplierProductsTableExists()) {
      $stmt = $this->pdo->prepare(
        "SELECT sp.product_id, p.name AS product_name, sp.unit_price
         FROM supplier_products sp
         INNER JOIN products p ON p.id = sp.product_id
         WHERE sp.supplier_id = ?
         ORDER BY p.name ASC"
      );
      $stmt->execute([(int) $id]);
      return $stmt->fetchAll();
    }

    // Fallback for pre-migration deployments.
    $stmt = $this->pdo->prepare(
      "SELECT DISTINCT pi.product_id, p.name AS product_name, pi.unit_price
       FROM procurements pr
       INNER JOIN procurement_items pi ON pi.procurement_id = pr.id
       INNER JOIN products p ON p.id = pi.product_id
       WHERE pr.supplier_id = ? AND pi.product_id IS NOT NULL
       ORDER BY p.name ASC"
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetchAll();
  }

  public function listOwnedProducts($supplierId) {
    if ($this->supplierProductsTableExists()) {
      $stmt = $this->pdo->prepare(
        "SELECT
           p.id,
           p.sku,
           p.name,
           p.unit,
           p.stock_quantity,
           p.reorder_level,
           p.created_at,
           sp.unit_price
         FROM supplier_products sp
         INNER JOIN products p ON p.id = sp.product_id
         WHERE sp.supplier_id = ?
         ORDER BY p.name ASC"
      );
      $stmt->execute([(int) $supplierId]);
      return $stmt->fetchAll();
    }

    // Fallback for pre-migration deployments.
    $stmt = $this->pdo->prepare(
      "SELECT DISTINCT
         p.id,
         p.sku,
         p.name,
         p.unit,
         p.stock_quantity,
         p.reorder_level,
         p.created_at,
         pi.unit_price
       FROM procurements pr
       INNER JOIN procurement_items pi ON pi.procurement_id = pr.id
       INNER JOIN products p ON p.id = pi.product_id
       WHERE pr.supplier_id = ? AND pi.product_id IS NOT NULL
       ORDER BY p.name ASC"
    );
    $stmt->execute([(int) $supplierId]);
    return $stmt->fetchAll();
  }

  public function listSupplierProductCatalog($supplierId) {
    if (!$this->supplierProductsTableExists()) {
      throw new RuntimeException('supplier_products table is missing. Run migration first.');
    }

    $stmt = $this->pdo->prepare(
      "SELECT
         p.id AS product_id,
         p.sku,
         p.name AS product_name,
         p.unit,
         sp.unit_price,
         CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END AS is_mapped
       FROM products p
       LEFT JOIN supplier_products sp
         ON sp.product_id = p.id
        AND sp.supplier_id = ?
       ORDER BY p.name ASC"
    );
    $stmt->execute([(int) $supplierId]);
    return $stmt->fetchAll();
  }

  public function replaceSupplierProducts($supplierId, $items) {
    if (!$this->supplierProductsTableExists()) {
      throw new RuntimeException('supplier_products table is missing. Run migration first.');
    }

    if (!is_array($items)) {
      throw new InvalidArgumentException('items must be an array');
    }

    $normalized = [];
    foreach ($items as $item) {
      if (is_object($item)) {
        $item = (array) $item;
      }
      if (!is_array($item)) {
        continue;
      }
      $productId = (int) ($item['product_id'] ?? 0);
      $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0;
      if ($productId < 1 || $unitPrice < 0) {
        continue;
      }
      $normalized[$productId] = $unitPrice;
    }

    $this->pdo->beginTransaction();
    try {
      $deleteStmt = $this->pdo->prepare("DELETE FROM supplier_products WHERE supplier_id = ?");
      $deleteStmt->execute([(int) $supplierId]);

      if (!empty($normalized)) {
        $insertStmt = $this->pdo->prepare(
          "INSERT INTO supplier_products (supplier_id, product_id, unit_price)
           VALUES (?, ?, ?)"
        );

        foreach ($normalized as $productId => $unitPrice) {
          $insertStmt->execute([(int) $supplierId, (int) $productId, $unitPrice]);
        }
      }

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }

    return $this->listSupplierProducts($supplierId);
  }
}
