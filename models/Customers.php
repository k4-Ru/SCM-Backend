<?php
class Customers {
  private $pdo = '';
  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function getCustomers() {
    return execQuery("CALL getCustomers()", null, $this->pdo);
  }

  public function getCustomer($dt) {
    return execQuery("CALL getIndividualCustomer(?)", [$dt->id], $this->pdo);
  }

  public function insertCustomer($dt) {
    $values = [$dt->id, $dt->fname, $dt->lname];
    return execQuery("CALL insertCustomer(?, ?, ?)", $values, $this->pdo);
  }

  public function updateCustomer($dt) {
    $values = [$dt->id, $dt->fname, $dt->lname];
    return execQuery("CALL updateCustomer(?, ?, ?)", $values, $this->pdo);
  }

  public function deleteCustomer($dt) {
    $values = [$dt->id];
    return execQuery("CALL deleteCustomer(?)", $values, $this->pdo);
  }

  public function archiveCustomer($dt) {
    $values = [$dt->id, $dt->isdeleted];
    return execQuery("CALL archiveCustomer(?, ?)", $values, $this->pdo);
  }
}