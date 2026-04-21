<?php

class NotificationModel extends BaseModel {
  public function listNotifications($userId = null) {
    $params = [];
    $sql = 'SELECT id, user_id, title, message, is_read, created_at FROM notifications';

    if (!empty($userId)) {
      $sql .= ' WHERE user_id = ?';
      $params[] = (int) $userId;
    }

    $sql .= ' ORDER BY id DESC';
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
  }

  public function getNotification($id) {
    $stmt = $this->pdo->prepare(
      'SELECT id, user_id, title, message, is_read, created_at
       FROM notifications
       WHERE id = ?'
    );
    $stmt->execute([(int) $id]);
    return $stmt->fetch();
  }


  public function markNotificationRead($id, $isRead = 1) {
    $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = ? WHERE id = ?');
    $stmt->execute([(int) ((bool) $isRead), (int) $id]);

    if ($stmt->rowCount() < 1) {
      return null;
    }

    $row = $this->pdo->prepare('SELECT id, user_id, title, message, is_read, created_at FROM notifications WHERE id = ?');
    $row->execute([(int) $id]);
    return $row->fetch();
  }

  public function createNotification($dt) {
    if (empty($dt->user_id) || empty($dt->title) || empty($dt->message)) {
      throw new InvalidArgumentException('user_id, title and message are required');
    }

    $stmt = $this->pdo->prepare(
      'INSERT INTO notifications (user_id, title, message, is_read)
       VALUES (?, ?, ?, 0)'
    );
    
    $stmt->execute([
      (int) $dt->user_id,
      trim($dt->title),
      trim($dt->message)
    ]);

    $id = (int) $this->pdo->lastInsertId();
    $row = $this->pdo->prepare('SELECT id, user_id, title, message, is_read, created_at FROM notifications WHERE id = ?');
    $row->execute([$id]);
    return $row->fetch();
  }
}
