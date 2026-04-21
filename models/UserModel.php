<?php

class UserModel extends BaseModel {


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
    $this->statusAllowed($role, ['superadmin', 'admin', 'procurement', 'warehouse', 'viewer', 'supplier'], 'role');
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

    $this->statusAllowed($role, ['superadmin', 'admin', 'procurement', 'warehouse', 'viewer', 'supplier'], 'role');
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

  public function deleteUser($id) {
    $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([(int) $id]);
    return ['deleted' => $stmt->rowCount() > 0];
  }
}
