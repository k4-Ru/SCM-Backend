<?php

abstract class BaseModel {
  protected $pdo;

  public function __construct(
    \PDO $pdo
  ) {
    $this->pdo = $pdo;
  }

  protected function paginate($page, $limit) {
    $page = max(1, (int) $page);
    $limit = max(1, min(100, (int) $limit));
    $offset = ($page - 1) * $limit;
    return [$limit, $offset, $page];
  }

  protected function statusAllowed($value, $allowed, $field) {
    if (!in_array($value, $allowed, true)) {
      throw new InvalidArgumentException("Invalid {$field} value");
    }
  }
}
