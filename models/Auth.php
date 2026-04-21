<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class Auth {
  private $pdo = '';

  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }


  

  private function getJwtSecret() {
    if (!empty($_ENV['JWT_SECRET'])) {
      return $_ENV['JWT_SECRET'];
    }

    return 'replace_this_with_a_strong_secret'; //change
  }




  private function issueAccessToken($user) {
    $now = time();
    $ttl = !empty($_ENV['JWT_ACCESS_TTL']) ? (int) $_ENV['JWT_ACCESS_TTL'] : 3600;

    $payload = [
      'iss' => 'SCM-backend',
      'sub' => (int) $user['id'],
      'name' => $user['name'],
      'email' => $user['email'],
      'role' => $user['role'],
      'supplier_id' => isset($user['supplier_id']) ? (int) $user['supplier_id'] : null,
      'jti' => bin2hex(random_bytes(16)),
      'iat' => $now,
      'exp' => $now + $ttl
    ];

    return JWT::encode($payload, $this->getJwtSecret(), 'HS256');
  }




  
  public function verifyAccessToken($token) {
    if (empty($token)) {
      throw new InvalidArgumentException('Missing access token');
    }

    try {
      $decoded = JWT::decode($token, new Key($this->getJwtSecret(), 'HS256'));
      return (array) $decoded;
    } catch (ExpiredException $e) {
      throw new RuntimeException('Access token expired');
    } catch (SignatureInvalidException $e) {
      throw new RuntimeException('Invalid access token signature');
    } catch (Throwable $e) {
      throw new RuntimeException('Invalid access token');
    }
  }








  public function login($dt) {
    if ((empty($dt->name) && empty($dt->email)) || empty($dt->password)) {
      throw new InvalidArgumentException('either name or email and password are required');
    }

    $stmt = $this->pdo->prepare(
      'SELECT id, name, email, password_hash, role, is_active, supplier_id
       FROM users
       WHERE email = ? OR name = ?
       LIMIT 1'
    );
    $stmt->execute([
      strtolower(trim($dt->email ?? '')),
      trim($dt->name ?? '')
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['is_active'] !== 1 || !password_verify($dt->password, $user['password_hash'])) {
      throw new RuntimeException('Invalid credentials');
    }

    $updateLogin = $this->pdo->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
    $updateLogin->execute([(int) $user['id']]);

    $accessToken = $this->issueAccessToken($user);

    return [
      'access_token' => $accessToken,
      'token_type' => 'Bearer',
      'expires_in' => !empty($_ENV['JWT_ACCESS_TTL']) ? (int) $_ENV['JWT_ACCESS_TTL'] : 3600,
      'user' => [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'supplier_id' => isset($user['supplier_id']) ? (int) $user['supplier_id'] : null
      ]
    ];
  }






  public function register($dt) {
    if (empty($dt->name) || empty($dt->email) || empty($dt->password)) {
      throw new InvalidArgumentException('name, email and password are required');
    }

    $email = strtolower(trim($dt->email));
    $check = $this->pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
      throw new RuntimeException('Email already exists');
    }

    $role = !empty($dt->role) ? $dt->role : 'viewer';
    if (!in_array($role, ['superadmin', 'admin', 'procurement', 'warehouse', 'viewer', 'supplier'], true)) {
      throw new InvalidArgumentException('Invalid role value');
    }

    $supplierId = null;
    if ($role === 'supplier') {
      if (empty($dt->supplier_id)) {
        throw new InvalidArgumentException('supplier_id is required for supplier role');
      }
      $supplierId = (int) $dt->supplier_id;
    }

    $stmt = $this->pdo->prepare(
      'INSERT INTO users (name, email, password_hash, role, supplier_id, is_active)
       VALUES (?, ?, ?, ?, ?, 1)'
    );
    $stmt->execute([
      trim($dt->name),
      $email,
      password_hash($dt->password, PASSWORD_DEFAULT),
      $role,
      $supplierId
    ]);

    $id = (int) $this->pdo->lastInsertId();
    $row = $this->pdo->prepare(
      'SELECT id, name, email, role, supplier_id, is_active, created_at, updated_at
       FROM users
       WHERE id = ?'
    );
    $row->execute([$id]);

    return $row->fetch(PDO::FETCH_ASSOC);
  }






  public function logout($dt = null) {
    return [
      'message' => 'Logged out on client.'
    ];
  }
}
