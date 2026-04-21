<?php

class InventoryModel extends BaseModel {
  public function listInventory() {
    $stmt = $this->pdo->query(
      "SELECT i.id, i.product_id, p.name AS product_name, i.stock_quantity, i.location, i.last_updated
       FROM inventory i
       INNER JOIN products p ON p.id = i.product_id
       ORDER BY i.id DESC"
    );
    return $stmt->fetchAll();
  }

  public function getInventory($id) {
    $stmt = $this->pdo->prepare(
      "SELECT i.id, i.product_id, p.name AS product_name, i.stock_quantity, i.location, i.last_updated
       FROM inventory i
       INNER JOIN products p ON p.id = i.product_id
       WHERE i.id = ?"
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
  }

  public function upsertInventory($dt) {
    if (empty($dt->product_id)) {
      throw new InvalidArgumentException('product_id is required');
    }

    $stockQuantity = isset($dt->stock_quantity) ? (int) $dt->stock_quantity : 0;
    $location = $dt->location ?? null;

    $stmt = $this->pdo->prepare(
      'INSERT INTO inventory (product_id, stock_quantity, location)
       VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE stock_quantity = VALUES(stock_quantity), location = VALUES(location)'
    );
    $stmt->execute([
      (int) $dt->product_id,
      $stockQuantity,
      $location
    ]);

    $row = $this->pdo->prepare('SELECT id FROM inventory WHERE product_id = ? LIMIT 1');
    $row->execute([(int) $dt->product_id]);
    $inventory = $row->fetch();

    return $inventory ? $this->getInventory((int) $inventory['id']) : null;
  }

  public function updateInventory($id, $dt) {
    $current = $this->getInventory($id);
    if (!$current) {
      return null;
    }

    $stmt = $this->pdo->prepare(
      'UPDATE inventory
       SET stock_quantity = ?, location = ?
       WHERE id = ?'
    );
    $stmt->execute([
      isset($dt->stock_quantity) ? (int) $dt->stock_quantity : (int) $current['stock_quantity'],
      $dt->location ?? $current['location'],
      (int) $id
    ]);

    return $this->getInventory($id);
  }
}
