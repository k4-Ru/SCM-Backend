<?php

class SupplierModel extends BaseModel {
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
}
