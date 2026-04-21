<?php

class SCM {
  private $models;
  private $methodMap = [
    'listUsers' => 'users',
    'getUser' => 'users',
    'getProfile' => 'users',
    'updateProfile' => 'users',
    'createUser' => 'users',
    'updateUser' => 'users',
    'deleteUser' => 'users',
    'listSuppliers' => 'suppliers',
    'getSupplier' => 'suppliers',
    'createSupplier' => 'suppliers',
    'updateSupplier' => 'suppliers',
    'deleteSupplier' => 'suppliers',
    'listProcurements' => 'procurements',
    'getProcurement' => 'procurements',
    'createProcurement' => 'procurements',
    'updateProcurementStatus' => 'procurements',
    'deleteProcurement' => 'procurements',
    'adminSuppliers' => 'procurements',
    'adminOrders' => 'procurements',
    'reportOrderStatus' => 'procurements',
    'reportSupplierPerformance' => 'procurements',
    'listShipments' => 'shipments',
    'getShipment' => 'shipments',
    'updateShipmentStatusForSupplier' => 'shipments',
    'createShipment' => 'shipments',
    'updateShipment' => 'shipments',
    'deleteShipment' => 'shipments',
    'listProducts' => 'products',
    'getProduct' => 'products',
    'createProduct' => 'products',
    'updateProduct' => 'products',
    'deleteProduct' => 'products',
    'listInventory' => 'inventory',
    'getInventory' => 'inventory',
    'upsertInventory' => 'inventory',
    'updateInventory' => 'inventory',
    'listNotifications' => 'notifications',
    'getNotification' => 'notifications',
    'markNotificationRead' => 'notifications',
    'createNotification' => 'notifications',
    'listSupplierApplications' => 'supplierApplications',
    'getSupplierApplication' => 'supplierApplications',
    'createSupplierApplication' => 'supplierApplications',
    'reviewSupplierApplication' => 'supplierApplications',
    'listActivityLogs' => 'activityLogs',
    'createActivityLog' => 'activityLogs'
  ];

  public function __construct(\PDO $pdo) {
    $this->models = [
      'users' => new UserModel($pdo),
      'suppliers' => new SupplierModel($pdo),
      'procurements' => new ProcurementModel($pdo),
      'shipments' => new ShipmentModel($pdo),
      'products' => new ProductModel($pdo),
      'inventory' => new InventoryModel($pdo),
      'notifications' => new NotificationModel($pdo),
      'supplierApplications' => new SupplierApplicationModel($pdo),
      'activityLogs' => new ActivityLogModel($pdo)
    ];
  }

  public function __call($name, $arguments) {
    if (!isset($this->methodMap[$name])) {
      throw new BadMethodCallException('Undefined method ' . __CLASS__ . '::' . $name . '()');
    }

    $modelKey = $this->methodMap[$name];
    return $this->models[$modelKey]->$name(...$arguments);
  }
}

