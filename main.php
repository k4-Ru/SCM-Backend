<?php
require __DIR__ . "/vendor/autoload.php";

$env = Dotenv\Dotenv::createImmutable(__DIR__ . "/config/");
$env->load();

// CORS origns
$allowedOrigins = [
  'http://localhost:5173',
  'http://127.0.0.1:5173'
];


if (!empty($_ENV['FRONTEND_ORIGIN'])) {
  $allowedOrigins[] = trim($_ENV['FRONTEND_ORIGIN']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');



if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

header('Content-Type: application/json');







//for response
function respond($data, $status = 200) {
  http_response_code($status);
  echo json_encode($data);
  exit;
}




function getBearerToken() {
  $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (stripos($header, 'Bearer ') === 0) {
    return trim(substr($header, 7));
  }
  return null;
}








function requireAdmin($authPayload) { //for admin access since rbac tayo
  $role = $authPayload['role'] ?? '';
  if ($role !== 'superadmin' && $role !== 'admin') {
    respond(['error' => 'Forbidden. Admin access required'], 403);
  }
}

function requireInternalUser($authPayload) {
  if (($authPayload['role'] ?? '') === 'supplier') {
    respond(['error' => 'Forbidden for supplier role'], 403);
  }
}

function requireInternalEditor($authPayload) {
  $role = $authPayload['role'] ?? '';
  if ($role === 'supplier' || $role === 'viewer') {
    respond(['error' => 'Forbidden for this role'], 403);
  }
}

function requireSupplierUser($authPayload) {
  if (($authPayload['role'] ?? '') !== 'supplier') {
    respond(['error' => 'Forbidden. Supplier access required'], 403);
  }
}

function getSupplierIdFromToken($authPayload) {
  return isset($authPayload['supplier_id']) ? (int) $authPayload['supplier_id'] : 0;
}



function resolveRouteParams() { 
  $rawParams = $_GET['params'] ?? '';
  if (trim((string) $rawParams) !== '') {
    return $rawParams;
  }

  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
  $path = trim($path, '/');


  if (stripos($path, 'main.php/') === 0) {
    $path = substr($path, strlen('main.php/'));
  } elseif ($path === 'main.php') {
    $path = '';
  }

  return $path;
}

function buildSupplierApplicationPayloadFromRequest() {
  $payload = new stdClass();
  $payload->company_name = trim($_POST['company_name'] ?? '');
  $payload->contact_person = trim($_POST['contact_person'] ?? '');
  $payload->email = trim($_POST['email'] ?? '');
  $payload->phone = trim($_POST['phone'] ?? '');
  $payload->contact_number = trim($_POST['contact_number'] ?? '');
  $payload->address = trim($_POST['address'] ?? '');
  $payload->products_offered = trim($_POST['products_offered'] ?? '');
  $payload->notes = trim($_POST['notes'] ?? '');
  $password = (string) ($_POST['password'] ?? '');
  if (strlen($password) < 8) {
    throw new InvalidArgumentException('Password must be at least 8 characters');
  }
  $payload->password_hash = password_hash($password, PASSWORD_DEFAULT);
  $payload->document_name = null;
  $payload->document_path = null;
  $payload->documents_json = null;

  $documents = [];
  $fileSource = null;
  if (!empty($_FILES['documents'])) {
    $fileSource = $_FILES['documents'];
  } elseif (!empty($_FILES['document'])) {
    $fileSource = $_FILES['document'];
  }

  if (!empty($fileSource)) {
    $names = is_array($fileSource['name'] ?? null) ? $fileSource['name'] : [($fileSource['name'] ?? '')];
    $tmpNames = is_array($fileSource['tmp_name'] ?? null) ? $fileSource['tmp_name'] : [($fileSource['tmp_name'] ?? '')];
    $sizes = is_array($fileSource['size'] ?? null) ? $fileSource['size'] : [($fileSource['size'] ?? 0)];
    $errors = is_array($fileSource['error'] ?? null) ? $fileSource['error'] : [($fileSource['error'] ?? UPLOAD_ERR_NO_FILE)];

    $uploadDir = __DIR__ . '/uploads/supplier-docs';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
      throw new RuntimeException('Unable to create upload directory');
    }

    $allowed = ['pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx'];
    $maxBytes = 5 * 1024 * 1024;

    for ($i = 0; $i < count($names); $i += 1) {
      if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
      }
      if (($errors[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One of the documents failed to upload');
      }

      $size = (int) ($sizes[$i] ?? 0);
      if ($size < 1 || $size > $maxBytes) {
        throw new InvalidArgumentException('Each document must be between 1 byte and 5 MB');
      }

      $originalName = (string) ($names[$i] ?? '');
      $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
      if (!in_array($ext, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported document type found in upload');
      }

      $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($originalName, PATHINFO_FILENAME));
      $safeBase = $safeBase ?: 'document';
      $newName = sprintf('%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(4)), $ext);
      $targetPath = $uploadDir . '/' . $newName;

      if (!move_uploaded_file((string) ($tmpNames[$i] ?? ''), $targetPath)) {
        throw new RuntimeException('Unable to store one of the uploaded documents');
      }

      $documents[] = [
        'name' => substr($safeBase, 0, 120) . '.' . $ext,
        'path' => '/uploads/supplier-docs/' . $newName,
      ];
    }
  }

  if (!empty($documents)) {
    $payload->document_name = $documents[0]['name'];
    $payload->document_path = $documents[0]['path'];
    $payload->documents_json = json_encode($documents);
  }

  return $payload;
}





try {
  $db = new Connection();
  $pdo = $db->connect();

  $auth = new Auth($pdo);
  $scm = new SCM($pdo);

  $rawParams = resolveRouteParams();
  $param = array_values(array_filter(explode('/', trim($rawParams, '/')), 'strlen'));

  $resource = $param[0] ?? '';
  $subResource = $param[1] ?? '';
  $id = isset($param[1]) ? (int) $param[1] : null;
  if ($resource === 'api') {
    $resource = $param[1] ?? '';
    $subResource = $param[2] ?? '';
    $id = isset($param[2]) ? (int) $param[2] : null;
  }
  $method = strtoupper($_SERVER['REQUEST_METHOD']);
  $dt = json_decode(file_get_contents("php://input"));

  // POST /api/auth/register
  if ($method === 'POST' && $resource === 'auth' && $subResource === 'register') {
    respond($auth->register($dt), 201);
  }

  // POST /api/auth/login
  if ($method === 'POST' && $resource === 'auth' && $subResource === 'login') {
    respond($auth->login($dt), 200);
  }

  // POST /api/auth/refresh
  if ($method === 'POST' && $resource === 'auth' && $subResource === 'refresh') {
    respond([
      'error' => 'token expired. login again'
    ], 410);
  }

  // POST /api/auth/logout
  if ($method === 'POST' && $resource === 'auth' && $subResource === 'logout') {
    respond($auth->logout($dt), 200);
  }

  // POST /api/public/supplier-applications
  if ($method === 'POST' && $resource === 'public' && $subResource === 'supplier-applications') {
    $applicationPayload = buildSupplierApplicationPayloadFromRequest();
    respond($scm->createSupplierApplication($applicationPayload), 201);
  }





  //old 
  // // POST /api/login 
  // if ($method === 'POST' && $resource === 'login') {
  //   respond($auth->login($dt), 200);
  // }

  // // POST /api/refresh
  // if ($method === 'POST' && $resource === 'refresh') {
  //   respond([
  //     'error' => ' Please login again.'
  //   ], 410);
  // }

  // // POST /api/logout
  // if ($method === 'POST' && $resource === 'logout') {
  //   respond($auth->logout($dt), 200);
  // }










  $token = getBearerToken();
  if (empty($token)) {
    respond(['error' => 'Unauthorized'], 401); //401 = unauthorized 
  }

  $authPayload = $auth->verifyAccessToken($token);


  
  // GET /api/me
  if ($method === 'GET' && $resource === 'me' && $subResource === '') {
    respond(['user' => $authPayload], 200);
  }

  // GET|PUT /api/me/supplier-products
  if ($resource === 'me' && $subResource === 'supplier-products') {
    requireSupplierUser($authPayload);
    $supplierId = getSupplierIdFromToken($authPayload);
    if ($supplierId < 1) {
      respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
    }

    if ($method === 'GET') {
      respond($scm->listSupplierProductCatalog($supplierId), 200);
    }

    if ($method === 'PUT') {
      $items = [];
      if (is_object($dt) && isset($dt->items) && is_array($dt->items)) {
        $items = $dt->items;
      }
      respond($scm->replaceSupplierProducts($supplierId, $items), 200);
    }
  }




  // GET|PUT /api/users/profile
  if ($resource === 'users' && $subResource === 'profile') {
    $userId = (int) ($authPayload['sub'] ?? 0);
    if ($method === 'GET') {
      $profile = $scm->getProfile($userId);
      if (!$profile) {
        respond(['error' => 'User not found'], 404);
      }
      respond($profile, 200);
    }

    if ($method === 'PUT') {
      $profile = $scm->updateProfile($userId, $dt);
      if (!$profile) {
        respond(['error' => 'User not found'], 404);
      }
      respond($profile, 200);
    }
  }

  // GET /api/warehouses
  if ($method === 'GET' && $resource === 'warehouses') {
    $role = $authPayload['role'] ?? '';
    if ($role === 'viewer') {
      respond(['error' => 'Forbidden for this role'], 403);
    }
    respond($scm->listWarehouseUsers(), 200);
  }

  // GET /api/admin/suppliers
  // GET /api/admin/orders
  if ($resource === 'admin' && $method === 'GET') {
    requireAdmin($authPayload);
    if ($subResource === 'suppliers') {
      respond($scm->adminSuppliers(), 200);
    }
    if ($subResource === 'orders') {
      respond($scm->adminOrders(), 200);
    }
  }

  // GET /api/reports/order-status
  // GET /api/reports/supplier-performance
  if ($resource === 'reports' && $method === 'GET') {
    requireInternalUser($authPayload);
    if ($subResource === 'order-status') {
      respond($scm->reportOrderStatus(), 200);
    }
    if ($subResource === 'supplier-performance') {
      respond($scm->reportSupplierPerformance(), 200);
    }
  }

  switch ($resource) {
    case 'users':
      requireInternalUser($authPayload);
      // GET /api/users and GET /api/users/{id}
      if ($method === 'GET') {
        if (!empty($id)) {
          $row = $scm->getUser($id);
          if (!$row) {
            respond(['error' => 'User not found'], 404);
          }
          respond($row, 200);
        }
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 20;
        respond($scm->listUsers($page, $limit), 200);
      }

      // POST /api/users
      if ($method === 'POST') {
        respond($scm->createUser($dt), 201);
      }

      // PATCH /api/users/{id}
      if ($method === 'PATCH' && !empty($id)) {
        $row = $scm->updateUser($id, $dt);
        if (!$row) {
          respond(['error' => 'User not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/users/{id}
      if ($method === 'DELETE' && !empty($id)) {
        respond($scm->deleteUser($id), 200);
      }
      break;

















    case 'suppliers':
      // GET /api/suppliers and GET /api/suppliers/{id}
      if ($method === 'GET') {
        $role = $authPayload['role'] ?? '';
        if ($role === 'viewer') {
          respond(['error' => 'Forbidden. Viewers cannot access suppliers'], 403);
        }
        $resourceIndex = (($param[0] ?? '') === 'api') ? 1 : 0;
        $supplierId = (int) ($param[$resourceIndex + 1] ?? 0);
        $supplierNestedResource = $param[$resourceIndex + 2] ?? '';

        // GET /api/suppliers/{id}/products
        if ($supplierId > 0 && $supplierNestedResource === 'products') {
          respond($scm->listSupplierProducts($supplierId), 200);
        }

        if (!empty($id)) {
          $row = $scm->getSupplier($id);
          if (!$row) {
            respond(['error' => 'Supplier not found'], 404);
          }
          respond($row, 200);
        }
        respond($scm->listSuppliers(), 200);
      }

      // POST /api/suppliers
      if ($method === 'POST') {
        requireAdmin($authPayload);
        respond($scm->createSupplier($dt), 201);
      }

      // PATCH /api/suppliers/{id}
      if ($method === 'PATCH' && !empty($id)) {
        requireAdmin($authPayload);
        $row = $scm->updateSupplier($id, $dt);
        if (!$row) {
          respond(['error' => 'Supplier not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/suppliers/{id}
      if ($method === 'DELETE' && !empty($id)) {
        requireAdmin($authPayload);
        respond($scm->deleteSupplier($id), 200);
      }
      break;















    case 'procurements':
      $role = $authPayload['role'] ?? '';
      if ($role === 'viewer') {
        respond(['error' => 'Forbidden. Viewers cannot access procurements'], 403);
      }
      $resourceIndex = (($param[0] ?? '') === 'api') ? 1 : 0;
      $procurementId = (int) ($param[$resourceIndex + 1] ?? 0);
      $nestedResource = $param[$resourceIndex + 2] ?? '';
      $nestedId = (int) ($param[$resourceIndex + 3] ?? 0);

      // GET|POST|PATCH|DELETE /api/procurements/{id}/items[/itemId]
      if ($procurementId > 0 && $nestedResource === 'items') {
        $supplierId = null;
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
        }

        if ($method === 'GET') {
          $items = $scm->listProcurementItems($procurementId, $supplierId);
          if ($items === null) {
            respond(['error' => 'Procurement not found'], 404);
          }
          respond($items, 200);
        }

        if ($method === 'POST') {
          requireInternalUser($authPayload);
          $item = $scm->createProcurementItem($procurementId, $dt, $supplierId);
          if ($item === null) {
            respond(['error' => 'Procurement not found'], 404);
          }
          respond($item, 201);
        }

        if ($method === 'PATCH' && $nestedId > 0) {
          requireInternalUser($authPayload);
          $item = $scm->updateProcurementItem($procurementId, $nestedId, $dt, $supplierId);
          if ($item === null) {
            respond(['error' => 'Procurement item not found'], 404);
          }
          respond($item, 200);
        }

        if ($method === 'DELETE' && $nestedId > 0) {
          requireInternalUser($authPayload);
          $deleted = $scm->deleteProcurementItem($procurementId, $nestedId, $supplierId);
          if ($deleted === null) {
            respond(['error' => 'Procurement not found'], 404);
          }
          respond($deleted, 200);
        }
      }

      // GET /api/procurements and GET /api/procurements/{id}
      if ($method === 'GET') {
        $supplierId = null;
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
        }

        if (!empty($id)) {
          $row = $scm->getProcurement($id, $supplierId);
          if (!$row) {
            respond(['error' => 'Procurement not found'], 404);
          }
          respond($row, 200);
        }
        $status = $_GET['status'] ?? null;
        respond($scm->listProcurements($status, $supplierId), 200);
      }

      // POST /api/procurements
      if ($method === 'POST') {
        if ($role !== 'procurement') {
          respond(['error' => 'Forbidden. Procurement access required'], 403);
        }
        respond($scm->createProcurement($dt), 201);
      }

      // PATCH /api/procurements/{id}
      if ($method === 'PATCH' && !empty($id)) {
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
          $row = $scm->updateProcurementStatusForSupplier($id, $supplierId, $dt);
        } elseif ($role === 'warehouse') {
          $nextStatus = (string) ($dt->status ?? '');
          if ($nextStatus !== 'delivered') {
            respond(['error' => 'Warehouse can only mark orders as delivered'], 403);
          }
          $warehouseUserId = (int) ($authPayload['sub'] ?? 0);
          $row = $scm->receiveProcurementByWarehouse($id, $warehouseUserId);
        } else {
          respond(['error' => 'Forbidden. Only supplier or warehouse can update order status'], 403);
        }
        if (!$row) {
          respond(['error' => 'Procurement not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/procurements/{id}
      if ($method === 'DELETE' && !empty($id)) {
        respond(['error' => 'Forbidden'], 403);
      }
      break;












    case 'shipments':
      $role = $authPayload['role'] ?? '';
      if ($role === 'viewer') {
        respond(['error' => 'Forbidden. Viewers cannot access shipments'], 403);
      }
      // GET /api/shipments and GET /api/shipments/{id}
      if ($method === 'GET') {
        $supplierId = null;
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
        }

        if (!empty($id)) {
          $row = $scm->getShipment($id, $supplierId);
          if (!$row) {
            respond(['error' => 'Shipment not found'], 404);
          }
          respond($row, 200);
        }
        $status = $_GET['status'] ?? null;
        respond($scm->listShipments($status, $supplierId), 200);
      }

      // POST /api/shipments
      if ($method === 'POST') {
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
          respond($scm->createShipment($dt, $supplierId), 201);
        }
        respond(['error' => 'Forbidden. Supplier access required'], 403);
      }

      // PATCH /api/shipments/{id}
      if ($method === 'PATCH' && !empty($id)) {
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
          $row = $scm->updateShipmentStatusForSupplier($id, $supplierId, $dt);
        } else {
          if ($role !== 'warehouse') {
            respond(['error' => 'Forbidden. Warehouse access required'], 403);
          }
          $warehouseUserId = (int) ($authPayload['sub'] ?? 0);
          $row = $scm->updateShipment($id, $dt, $warehouseUserId);
        }
        if (!$row) {
          respond(['error' => 'Shipment not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/shipments/{id}
      if ($method === 'DELETE' && !empty($id)) {
        respond(['error' => 'Forbidden'], 403);
      }
      break;


















    case 'products':
      // GET /api/products and GET /api/products/{id}
      if ($method === 'GET') {
        $role = $authPayload['role'] ?? '';
        if ($role === 'supplier' && empty($id)) {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
          respond($scm->listOwnedProducts($supplierId), 200);
        }
        if (!empty($id)) {
          $row = $scm->getProduct($id);
          if (!$row) {
            respond(['error' => 'Product not found'], 404);
          }
          respond($row, 200);
        }
        respond($scm->listProducts(), 200);
      }

      // POST /api/products
      if ($method === 'POST') {
        requireInternalEditor($authPayload);
        respond($scm->createProduct($dt), 201);
      }

      // PATCH /api/products/{id}
      if ($method === 'PATCH' && !empty($id)) {
        requireInternalEditor($authPayload);
        $row = $scm->updateProduct($id, $dt);
        if (!$row) {
          respond(['error' => 'Product not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/products/{id}
      if ($method === 'DELETE' && !empty($id)) {
        requireAdmin($authPayload);
        respond($scm->deleteProduct($id), 200);
      }
      break;















    case 'inventory':
      // GET /api/inventory and GET /api/inventory/{id}
      if ($method === 'GET') {
        $role = $authPayload['role'] ?? '';
        if ($role === 'viewer') {
          respond(['error' => 'Forbidden for this role'], 403);
        }
        if ($role === 'supplier' && empty($id)) {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }
          respond($scm->listInventoryBySupplier($supplierId), 200);
        }
        if (!empty($id)) {
          $row = $scm->getInventory($id);
          if (!$row) {
            respond(['error' => 'Inventory row not found'], 404);
          }
          respond($row, 200);
        }
        respond($scm->listInventory(), 200);
      }

      // POST /api/inventory (upsert by product_id)
      if ($method === 'POST') {
        $role = $authPayload['role'] ?? '';
        if ($role !== 'warehouse') {
          respond(['error' => 'Forbidden. Warehouse access required'], 403);
        }
        respond($scm->upsertInventory($dt), 201);
      }

      // PATCH /api/inventory/{id}
      if ($method === 'PATCH' && !empty($id)) {
        $role = $authPayload['role'] ?? '';
        if ($role !== 'warehouse') {
          respond(['error' => 'Forbidden. Warehouse access required'], 403);
        }
        $row = $scm->updateInventory($id, $dt);
        if (!$row) {
          respond(['error' => 'Inventory row not found'], 404);
        }
        respond($row, 200);
      }
      break;

    case 'notifications':
      // GET /api/notifications
      if ($method === 'GET') {
        $role = $authPayload['role'] ?? '';
        $userId = (int) ($authPayload['sub'] ?? 0);
        if ($role === 'admin' || $role === 'superadmin') {
          respond($scm->listNotifications(), 200);
        }
        respond($scm->listNotifications($userId), 200);
      }

      // POST /api/notifications
      if ($method === 'POST') {
        requireInternalEditor($authPayload);
        respond($scm->createNotification($dt), 201);
      }

      // PATCH /api/notifications/{id}
      if ($method === 'PATCH' && !empty($id)) {
        $role = $authPayload['role'] ?? '';
        $userId = (int) ($authPayload['sub'] ?? 0);
        if ($role !== 'admin' && $role !== 'superadmin') {
          $notification = $scm->getNotification($id);
          if (!$notification || (int) $notification['user_id'] !== $userId) {
            respond(['error' => 'Forbidden. You can only update your own notifications'], 403);
          }
        }
        $row = $scm->markNotificationRead($id, $dt->is_read ?? 1);
        if (!$row) {
          respond(['error' => 'Notification not found'], 404);
        }
        respond($row, 200);
      }
      break;


















    case 'supplier-applications':
      // GET /api/supplier-applications and GET /api/supplier-applications/{id}
      if ($method === 'GET') {
        requireInternalEditor($authPayload);
        if (!empty($id)) {
          $row = $scm->getSupplierApplication($id);
          if (!$row) {
            respond(['error' => 'Supplier application not found'], 404);
          }
          respond($row, 200);
        }
        $status = $_GET['status'] ?? null;
        respond($scm->listSupplierApplications($status), 200);
      }

      // POST /api/supplier-applications
      if ($method === 'POST') {
        requireInternalEditor($authPayload);
        respond($scm->createSupplierApplication($dt), 201);
      }

      // PATCH /api/supplier-applications/{id}
      if ($method === 'PATCH' && !empty($id)) {
        requireAdmin($authPayload);
        $reviewedBy = (int) ($authPayload['sub'] ?? 0);
        $row = $scm->reviewSupplierApplication($id, $dt, $reviewedBy);
        if (!$row) {
          respond(['error' => 'Supplier application not found'], 404);
        }
        respond($row, 200);
      }
      break;














    case 'activity-logs':
      // GET /api/activity-logs
      if ($method === 'GET') {
        requireAdmin($authPayload);
        $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
        respond($scm->listActivityLogs($userId), 200);
      }

      // POST /api/activity-logs
      if ($method === 'POST') {
        requireInternalEditor($authPayload);
        respond($scm->createActivityLog($dt), 201);
      }
      break;
  }







  
  respond(['error' => 'Endpoint not found'], 404);
} catch (InvalidArgumentException $e) {
  respond(['error' => $e->getMessage()], 400);
} catch (RuntimeException $e) {
  if (stripos($e->getMessage(), 'token') !== false || stripos($e->getMessage(), 'credentials') !== false) {
    respond(['error' => $e->getMessage()], 401);
  }
  respond(['error' => $e->getMessage()], 400);
} catch (PDOException $e) {
  respond(['error' => 'Database error', 'message' => $e->getMessage()], 500);
} catch (Throwable $e) {
  respond(['error' => 'Server error', 'message' => $e->getMessage()], 500);
}
