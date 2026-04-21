<?php

class SupplierApplicationModel extends BaseModel {
  public function listSupplierApplications($status = null) {
    $params = [];
    $sql = 'SELECT id, company_name, contact_person, email, phone, address, status, reviewed_by, reviewed_at, notes, created_at FROM supplier_applications';

    if (!empty($status)) {
      $sql .= ' WHERE status = ?';
      $params[] = $status;
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public function getSupplierApplication($id) {
    $stmt = $this->pdo->prepare(
      'SELECT id, company_name, contact_person, email, phone, address, status, reviewed_by, reviewed_at, notes, created_at
       FROM supplier_applications
       WHERE id = ?'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
  }

  public function createSupplierApplication($dt) {
    if (empty($dt->company_name) || empty($dt->contact_person) || empty($dt->email)) {
      throw new InvalidArgumentException('company_name, contact_person and email are required');
    }

    $stmt = $this->pdo->prepare(
      'INSERT INTO supplier_applications (company_name, contact_person, email, phone, address, status, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
      trim($dt->company_name),
      trim($dt->contact_person),
      strtolower(trim($dt->email)),
      $dt->phone ?? null,
      $dt->address ?? null,
      'pending',
      $dt->notes ?? null
    ]);

    return $this->getSupplierApplication($this->pdo->lastInsertId());
  }

  public function reviewSupplierApplication($id, $dt, $reviewedBy) {
    if (empty($dt->status)) {
      throw new InvalidArgumentException('status is required');
    }

    $this->statusAllowed($dt->status, ['approved', 'rejected'], 'supplier application status');
    $current = $this->getSupplierApplication($id);
    if (!$current) {
      return null;
    }

    $stmt = $this->pdo->prepare(
      'UPDATE supplier_applications
       SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, notes = ?
       WHERE id = ?'
    );
    $stmt->execute([
      $dt->status,
      (int) $reviewedBy,
      $dt->notes ?? $current['notes'],
      (int) $id
    ]);

    return $this->getSupplierApplication($id);
  }
}
