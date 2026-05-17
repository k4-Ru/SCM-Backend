<?php

class UserModel extends BaseModel {
  private $columnSupport = [];

  private function hasColumn($columnName) {
    if (array_key_exists($columnName, $this->columnSupport)) {
      return $this->columnSupport[$columnName];
    }

    $stmt = $this->pdo->prepare(
      "SELECT 1
       FROM information_schema.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'users'
         AND COLUMN_NAME = ?
       LIMIT 1"
    );
    $stmt->execute([$columnName]);
    $this->columnSupport[$columnName] = (bool) $stmt->fetchColumn();
    return $this->columnSupport[$columnName];
  }

  private function encryptedEmailSupported() {
    return $this->hasColumn('email_enc') && $this->hasColumn('email_iv') && $this->hasColumn('email_tag');
  }

  private function userSelectFields() {
    $base = 'id, name, email, role, supplier_id, is_active, last_login, created_at, updated_at';
    if ($this->encryptedEmailSupported()) {
      return $base . ', email_enc, email_iv, email_tag';
    }
    return $base . ', NULL AS email_enc, NULL AS email_iv, NULL AS email_tag';
  }

  private function decryptEmailForOutput($row) {
    if (!$row) {
      return $row;
    }

    if ($this->encryptedEmailSupported() && !empty($row['email_enc']) && !empty($row['email_iv']) && !empty($row['email_tag'])) {
      $row['email'] = Crypto::decryptString($row['email_enc'], $row['email_iv'], $row['email_tag']);
    }

    unset($row['email_enc'], $row['email_iv'], $row['email_tag']);
    return $row;
  }


  public function listUsers($page = 1, $limit = 20) {
    list($limit, $offset) = $this->paginate($page, $limit);

    $stmt = $this->pdo->prepare(
      "SELECT " . $this->userSelectFields() . "
       FROM users
       ORDER BY id DESC
       LIMIT ? OFFSET ?"
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
      $row = $this->decryptEmailForOutput($row);
    }
    unset($row);
    return $rows;
  }

  public function getUser($id) {
    $stmt = $this->pdo->prepare(
      "SELECT " . $this->userSelectFields() . "
       FROM users
       WHERE id = ?"
    );
    $stmt->execute([(int) $id]);
    return $this->decryptEmailForOutput($stmt->fetch());
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

    $emailEncrypted = null;
    if ($this->encryptedEmailSupported()) {
      $emailEncrypted = Crypto::encryptString($email);
    }

    if (!empty($dt->password)) {
      if ($this->encryptedEmailSupported()) {
        $stmt = $this->pdo->prepare(
          'UPDATE users
           SET name = ?, email = ?, password_hash = ?, email_enc = ?, email_iv = ?, email_tag = ?, updated_at = CURRENT_TIMESTAMP
           WHERE id = ?'
        );
        $stmt->execute([
          $name,
          $email,
          password_hash($dt->password, PASSWORD_DEFAULT),
          $emailEncrypted['ciphertext'] ?? null,
          $emailEncrypted['iv'] ?? null,
          $emailEncrypted['tag'] ?? null,
          (int) $userId
        ]);
      } else {
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
      }
    } else {
      if ($this->encryptedEmailSupported()) {
        $stmt = $this->pdo->prepare(
          'UPDATE users
           SET name = ?, email = ?, email_enc = ?, email_iv = ?, email_tag = ?, updated_at = CURRENT_TIMESTAMP
           WHERE id = ?'
        );
        $stmt->execute([
          $name,
          $email,
          $emailEncrypted['ciphertext'] ?? null,
          $emailEncrypted['iv'] ?? null,
          $emailEncrypted['tag'] ?? null,
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
    }

    return $this->getUser((int) $userId);
  }




  
  public function createUser($dt) {
    if (empty($dt->name) || empty($dt->email) || empty($dt->password)) {
      throw new InvalidArgumentException("name, email and password are required");
    }

    $role = $dt->role ?? 'viewer';
    $this->statusAllowed($role, ['superadmin', 'admin', 'procurement', 'warehouse', 'viewer', 'supplier'], 'role');
    $isActive = isset($dt->is_active) ? (int) ((bool) $dt->is_active) : 1;
    $supplierId = isset($dt->supplier_id) ? (int) $dt->supplier_id : null;

    if ($role === 'supplier' && empty($supplierId)) {
      throw new InvalidArgumentException('supplier_id is required for supplier role');
    }

    $email = strtolower(trim($dt->email));
    $emailEncrypted = null;
    if ($this->encryptedEmailSupported()) {
      $emailEncrypted = Crypto::encryptString($email);
    }

    if ($this->encryptedEmailSupported()) {
      $stmt = $this->pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, supplier_id, is_active, email_enc, email_iv, email_tag)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );

      $stmt->execute([
        trim($dt->name),
        $email,
        password_hash($dt->password, PASSWORD_DEFAULT),
        $role,
        $supplierId,
        $isActive,
        $emailEncrypted['ciphertext'] ?? null,
        $emailEncrypted['iv'] ?? null,
        $emailEncrypted['tag'] ?? null
      ]);
    } else {
      $stmt = $this->pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, supplier_id, is_active)
         VALUES (?, ?, ?, ?, ?, ?)"
      );

      $stmt->execute([
        trim($dt->name),
        $email,
        password_hash($dt->password, PASSWORD_DEFAULT),
        $role,
        $supplierId,
        $isActive
      ]);
    }

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

    $this->statusAllowed($role, ['superadmin', 'admin', 'procurement', 'warehouse', 'viewer', 'supplier'], 'role');
    $supplierId = isset($dt->supplier_id) ? (int) $dt->supplier_id : ($current['supplier_id'] ?? null);

    if ($role === 'supplier' && empty($supplierId)) {
      throw new InvalidArgumentException('supplier_id is required for supplier role');
    }

    if ($role !== 'supplier') {
      $supplierId = null;
    }

    $emailEncrypted = null;
    if ($this->encryptedEmailSupported()) {
      $emailEncrypted = Crypto::encryptString($email);
    }

    if (!empty($dt->password)) {
      if ($this->encryptedEmailSupported()) {
        $stmt = $this->pdo->prepare(
          "UPDATE users
           SET name = ?, email = ?, password_hash = ?, role = ?, supplier_id = ?, is_active = ?, email_enc = ?, email_iv = ?, email_tag = ?, updated_at = CURRENT_TIMESTAMP
           WHERE id = ?"
        );

        $stmt->execute([
          trim($name),
          $email,
          password_hash($dt->password, PASSWORD_DEFAULT),
          $role,
          $supplierId,
          $isActive,
          $emailEncrypted['ciphertext'] ?? null,
          $emailEncrypted['iv'] ?? null,
          $emailEncrypted['tag'] ?? null,
          (int) $id
        ]);
      } else {
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
      }
    } else {
      if ($this->encryptedEmailSupported()) {
        $stmt = $this->pdo->prepare(
          "UPDATE users
           SET name = ?, email = ?, role = ?, supplier_id = ?, is_active = ?, email_enc = ?, email_iv = ?, email_tag = ?, updated_at = CURRENT_TIMESTAMP
           WHERE id = ?"
        );

        $stmt->execute([
          trim($name),
          $email,
          $role,
          $supplierId,
          $isActive,
          $emailEncrypted['ciphertext'] ?? null,
          $emailEncrypted['iv'] ?? null,
          $emailEncrypted['tag'] ?? null,
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
    }

    return $this->getUser($id);
  }

  public function deleteUser($id) {
    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }

  public function listWarehouseUsers() {
    $stmt = $this->pdo->query(
      "SELECT id, name, email
       FROM users
       WHERE role = 'warehouse' AND is_active = 1
       ORDER BY name ASC, id ASC"
    );
    return $stmt->fetchAll();
  }
}
