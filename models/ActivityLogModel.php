<?php

class ActivityLogModel extends BaseModel { 


//for audit logging
  public function listActivityLogs($userId = null) {
    $params = [];
    $sql = 'SELECT id, user_id, action, target_table, target_id, description, created_at FROM activity_logs';
    if (!empty($userId)) {
      $sql .= ' WHERE user_id = ?';
      $params[] = (int) $userId;
    }
    $sql .= ' ORDER BY id DESC';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }








  
  public function createActivityLog($dt) {
    if (empty($dt->user_id) || empty($dt->action)) {
      throw new InvalidArgumentException('user_id and action are required');
    }

    $stmt = $this->pdo->prepare(
      'INSERT INTO activity_logs (user_id, action, target_table, target_id, description)
       VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
      (int) $dt->user_id,
      trim($dt->action),
      $dt->target_table ?? null,
      isset($dt->target_id) ? (int) $dt->target_id : null,
      $dt->description ?? null
    ]);

    $id = (int) $this->pdo->lastInsertId();
    $row = $this->pdo->prepare('SELECT id, user_id, action, target_table, target_id, description, created_at FROM activity_logs WHERE id = ?');
    $row->execute([$id]);
    return $row->fetch();
  }
}
