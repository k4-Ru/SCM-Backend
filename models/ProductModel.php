<?php

class ProductModel extends BaseModel {
  public function listProducts() {
    $stmt = $this->pdo->query(
      "SELECT id, name, unit, stock_quantity, reorder_level, created_at
       FROM products
       ORDER BY id DESC"
    );
    return $stmt->fetchAll();
  }

  public function getProduct($id) {
    $stmt = $this->pdo->prepare(
      "SELECT id, name, unit, stock_quantity, reorder_level, created_at
       FROM products
       WHERE id = ?"
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
  }

  public function createProduct($dt) {
    if (empty($dt->name)) {
      throw new InvalidArgumentException('name is required');
    }

    $stockQuantity = isset($dt->stock_quantity) ? (int) $dt->stock_quantity : 0;
    $reorderLevel = isset($dt->reorder_level) ? (int) $dt->reorder_level : 0;

    $stmt = $this->pdo->prepare(
      'INSERT INTO products (name, unit, stock_quantity, reorder_level)
       VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
      trim($dt->name),
      $dt->unit ?? null,
      $stockQuantity,
      $reorderLevel
    ]);

    return $this->getProduct($this->pdo->lastInsertId());
  }

  public function updateProduct($id, $dt) {
    $current = $this->getProduct($id);
    if (!$current) {
      return null;
    }

    $stmt = $this->pdo->prepare(
      'UPDATE products
       SET name = ?, unit = ?, stock_quantity = ?, reorder_level = ?
       WHERE id = ?'
    );
    $stmt->execute([
      trim($dt->name ?? $current['name']),
      $dt->unit ?? $current['unit'],
      isset($dt->stock_quantity) ? (int) $dt->stock_quantity : (int) $current['stock_quantity'],
      isset($dt->reorder_level) ? (int) $dt->reorder_level : (int) $current['reorder_level'],
      (int) $id
    ]);

    return $this->getProduct($id);
  }

  public function deleteProduct($id) {
    $stmt = $this->pdo->prepare('DELETE FROM products WHERE id = ?');
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }
}
