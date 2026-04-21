<?php
require_once __DIR__ . "/functions.php";
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

function requireAdmin($authPayload) {
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

  // Supports /main.php/api/... and /api/... styles.
  if (stripos($path, 'main.php/') === 0) {
    $path = substr($path, strlen('main.php/'));
  } elseif ($path === 'main.php') {
    $path = '';
  }

  return $path;
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





  // // POST /api/login (legacy)
  // if ($method === 'POST' && $resource === 'login') {
  //   respond($auth->login($dt), 200);
  // }

  // // POST /api/refresh (legacy)
  // if ($method === 'POST' && $resource === 'refresh') {
  //   respond([
  //     'error' => 'Refresh is disabled in stateless JWT mode. Please login again.'
  //   ], 410);
  // }

  // // POST /api/logout (legacy)
  // if ($method === 'POST' && $resource === 'logout') {
  //   respond($auth->logout($dt), 200);
  // }










  $token = getBearerToken();
  if (empty($token)) {
    respond(['error' => 'Unauthorized'], 401); //401 = unauthorized 
  }

  $authPayload = $auth->verifyAccessToken($token);


  
  // GET /api/me
  if ($method === 'GET' && $resource === 'me') {
    respond(['user' => $authPayload], 200);
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
        if ($role === 'supplier') {
          $supplierId = getSupplierIdFromToken($authPayload);
          if ($supplierId < 1) {
            respond(['error' => 'Supplier account is not linked to a supplier_id'], 403);
          }

          $row = $scm->getSupplier($supplierId);
          if (!$row) {
            respond(['error' => 'Supplier not found'], 404);
          }

          // Supplier role can only see own supplier profile.
          if (!empty($id) && $id !== $supplierId) {
            respond(['error' => 'Forbidden. You can only access your own supplier record'], 403);
          }

          if (!empty($id)) {
            respond($row, 200);
          }

          respond([$row], 200);
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
        requireInternalUser($authPayload);
        respond($scm->createProcurement($dt), 201);
      }

      // PATCH /api/procurements/{id}
      if ($method === 'PATCH' && !empty($id)) {
        requireInternalUser($authPayload);
        $row = $scm->updateProcurementStatus($id, $dt);
        if (!$row) {
          respond(['error' => 'Procurement not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/procurements/{id}
      if ($method === 'DELETE' && !empty($id)) {
        requireInternalUser($authPayload);
        respond($scm->deleteProcurement($id), 200);
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
        requireInternalUser($authPayload);
        respond($scm->createShipment($dt), 201);
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
          requireInternalUser($authPayload);
          $row = $scm->updateShipment($id, $dt);
        }
        if (!$row) {
          respond(['error' => 'Shipment not found'], 404);
        }
        respond($row, 200);
      }

      // DELETE /api/shipments/{id}
      if ($method === 'DELETE' && !empty($id)) {
        requireInternalUser($authPayload);
        respond($scm->deleteShipment($id), 200);
      }
      break;


















    case 'products':
      // GET /api/products and GET /api/products/{id}
      if ($method === 'GET') {
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
        requireInternalEditor($authPayload);
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
        requireInternalEditor($authPayload);
        respond($scm->upsertInventory($dt), 201);
      }

      // PATCH /api/inventory/{id}
      if ($method === 'PATCH' && !empty($id)) {
        requireInternalEditor($authPayload);
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