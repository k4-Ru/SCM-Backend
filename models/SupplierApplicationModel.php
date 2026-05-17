<?php

class SupplierApplicationModel extends BaseModel {
  private $columnSupport = [];

  private function hasColumn($columnName) {
    if (array_key_exists($columnName, $this->columnSupport)) {
      return $this->columnSupport[$columnName];
    }

    $stmt = $this->pdo->prepare(
      "SELECT 1
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'supplier_applications'
         AND COLUMN_NAME = ?
       LIMIT 1"
    );
    $stmt->execute([$columnName]);
    $this->columnSupport[$columnName] = (bool) $stmt->fetchColumn();
    return $this->columnSupport[$columnName];
  }

  private function selectFieldsForSupplierApplication() {
    $base = 'id, company_name, contact_person, email, phone, contact_number, address, products_offered, document_name, document_path';
    $documentsField = $this->hasColumn('documents_json') ? 'documents_json' : 'NULL AS documents_json';
    $passwordField = $this->hasColumn('password_hash') ? 'password_hash' : 'NULL AS password_hash';
    $phoneEncField = $this->hasColumn('phone_enc') ? 'phone_enc' : 'NULL AS phone_enc';
    $phoneIvField = $this->hasColumn('phone_iv') ? 'phone_iv' : 'NULL AS phone_iv';
    $phoneTagField = $this->hasColumn('phone_tag') ? 'phone_tag' : 'NULL AS phone_tag';
    return $base . ', ' . $documentsField . ', ' . $passwordField . ', ' . $phoneEncField . ', ' . $phoneIvField . ', ' . $phoneTagField . ', status, reviewed_by, reviewed_at, notes, created_at';
  }

  private function encryptedPhoneSupported() {
    return $this->hasColumn('phone_enc') && $this->hasColumn('phone_iv') && $this->hasColumn('phone_tag');
  }

  private function encryptedUsersEmailSupported() {
    $stmt = $this->pdo->prepare(
      "SELECT COLUMN_NAME
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'users'
         AND COLUMN_NAME IN ('email_enc', 'email_iv', 'email_tag')"
    );
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return in_array('email_enc', $columns, true) && in_array('email_iv', $columns, true) && in_array('email_tag', $columns, true);
  }

  private function decryptPhoneForOutput($row) {
    if (!$row) {
      return $row;
    }

    if ($this->encryptedPhoneSupported() && !empty($row['phone_enc']) && !empty($row['phone_iv']) && !empty($row['phone_tag'])) {
      $row['phone'] = Crypto::decryptString($row['phone_enc'], $row['phone_iv'], $row['phone_tag']);
    }

    unset($row['phone_enc'], $row['phone_iv'], $row['phone_tag']);
    return $row;
  }

  public function listSupplierApplications($status = null) {
    $params = [];
    $sql = 'SELECT ' . $this->selectFieldsForSupplierApplication() . ' FROM supplier_applications';

    if (!empty($status)) {
      $sql .= ' WHERE status = ?';
      $params[] = $status;
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
      $row = $this->decryptPhoneForOutput($row);
    }
    unset($row);
    return $rows;
  }

  public function getSupplierApplication($id) {
    $stmt = $this->pdo->prepare(
      'SELECT ' . $this->selectFieldsForSupplierApplication() . '
       FROM supplier_applications
       WHERE id = ?'
    );
    $stmt->execute([(int) $id]);
    return $this->decryptPhoneForOutput($stmt->fetch());
  }

  public function createSupplierApplication($dt) {
    if (empty($dt->company_name) || empty($dt->contact_person) || empty($dt->email)) {
      throw new InvalidArgumentException('company_name, contact_person and email are required');
    }
    if (empty($dt->password_hash)) {
      throw new InvalidArgumentException('password is required');
    }
    if (!$this->hasColumn('password_hash')) {
      throw new RuntimeException('Database is missing supplier_applications.password_hash. Run migration before accepting supplier applications.');
    }

    $columns = ['company_name', 'contact_person', 'email', 'phone', 'contact_number', 'address', 'products_offered', 'document_name', 'document_path'];
    $values = [
      trim($dt->company_name),
      trim($dt->contact_person),
      strtolower(trim($dt->email)),
      $dt->phone ?? null,
      $dt->contact_number ?? null,
      $dt->address ?? null,
      $dt->products_offered ?? null,
      $dt->document_name ?? null,
      $dt->document_path ?? null
    ];

    if ($this->encryptedPhoneSupported() && !empty($dt->phone)) {
      $phoneEncrypted = Crypto::encryptString((string) $dt->phone);
      $columns[] = 'phone_enc';
      $columns[] = 'phone_iv';
      $columns[] = 'phone_tag';
      $values[] = $phoneEncrypted['ciphertext'];
      $values[] = $phoneEncrypted['iv'];
      $values[] = $phoneEncrypted['tag'];
    }

    if ($this->hasColumn('documents_json')) {
      $columns[] = 'documents_json';
      $values[] = $dt->documents_json ?? null;
    }
    $columns[] = 'password_hash';
    $values[] = $dt->password_hash;

    $columns[] = 'status';
    $columns[] = 'notes';
    $values[] = 'pending';
    $values[] = $dt->notes ?? null;

    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $this->pdo->prepare(
      'INSERT INTO supplier_applications (' . implode(', ', $columns) . ')
       VALUES (' . $placeholders . ')'
    );
    $stmt->execute($values);

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

    if ($dt->status === 'approved') {
      $supplierLookup = $this->pdo->prepare('SELECT id FROM suppliers WHERE email = ? LIMIT 1');
      $supplierLookup->execute([$current['email']]);
      $supplier = $supplierLookup->fetch();

      $supplierId = $supplier ? (int) $supplier['id'] : 0;
      if ($supplierId < 1) {
        $insertSupplier = $this->pdo->prepare(
          'INSERT INTO suppliers (name, contact_person, email, phone, address, status)
           VALUES (?, ?, ?, ?, ?, ?)'
        );
        $insertSupplier->execute([
          trim($current['company_name']),
          $current['contact_person'] ?? null,
          strtolower(trim($current['email'])),
          $current['contact_number'] ?? ($current['phone'] ?? null),
          $current['address'] ?? null,
          'active',
        ]);
        $supplierId = (int) $this->pdo->lastInsertId();
      }

      $userLookup = $this->pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $userLookup->execute([$current['email']]);
      $user = $userLookup->fetch();
      if (!$user) {
        $passwordHash = $current['password_hash'] ?? null;
        if (empty($passwordHash)) {
          throw new RuntimeException('Cannot approve application without applicant password');
        }

        $normalizedEmail = strtolower(trim($current['email']));
        if ($this->encryptedUsersEmailSupported()) {
          $emailEncrypted = Crypto::encryptString($normalizedEmail);
          $insertUser = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, supplier_id, is_active, email_enc, email_iv, email_tag)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)'
          );
          $insertUser->execute([
            trim($current['contact_person'] ?: $current['company_name']),
            $normalizedEmail,
            $passwordHash,
            'supplier',
            $supplierId,
            $emailEncrypted['ciphertext'],
            $emailEncrypted['iv'],
            $emailEncrypted['tag'],
          ]);
        } else {
          $insertUser = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, supplier_id, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
          );
          $insertUser->execute([
            trim($current['contact_person'] ?: $current['company_name']),
            $normalizedEmail,
            $passwordHash,
            'supplier',
            $supplierId,
          ]);
        }
      }
    }

    return $this->getSupplierApplication($id);
  }
}
