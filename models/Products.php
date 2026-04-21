<?php
class Products {
  private $dt = '';
  public function __construct($dt) {
    $this->dt = $dt;
  }

  public function getProducts() {
    // return "product: " . $this->dt;
    return array("category"=>"Appliance", "product"=>$this->dt);
  }
}