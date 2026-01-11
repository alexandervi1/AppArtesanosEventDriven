<?php
declare(strict_types=1);

/*******************************************************
 * API ecommerce_artesanos (Modular)
 * - Entry point & Router
 *******************************************************/

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
  require_once $autoloadPath;
}

// Core Includes
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';

// Initialize DB
$pdo = get_db_connection();

// Routing
$resource = strtolower((string)q('resource', 'ping'));
$method = strtoupper($_SERVER['REQUEST_METHOD']);

try {
  switch ($resource) {
    case 'ping':
      out(['ok' => true, 'service' => 'ecommerce_api', 'time' => date('c')]);
      break;

    case 'categories':
      require __DIR__ . '/resources/categories.php';
      break;

    case 'artisans':
      require __DIR__ . '/resources/artisans.php';
      break;

    case 'products':
      require __DIR__ . '/resources/products.php';
      break;

    case 'customers':
      require __DIR__ . '/resources/customers.php';
      break;

    case 'carts':
    case 'cart_items':
      require __DIR__ . '/resources/carts.php';
      break;

    case 'orders':
      require __DIR__ . '/resources/orders.php';
      break;

    case 'catalog':
    case 'low_stock':
    case 'inventory_overview':
    case 'category_totals':
      require __DIR__ . '/resources/reports.php';
      break;

    default:
      fail('Recurso no encontrado o no válido', 404);
  }
} catch (Throwable $e) {
  error_log('[API Error] ' . $e->getMessage());
  fail('Error interno del servidor', 500, ['detail' => $e->getMessage()]);
}
