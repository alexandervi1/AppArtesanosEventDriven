<?php
/**
 * ============================================================================
 * ARCHIVO: api.php - PUNTO DE ENTRADA Y ROUTER PRINCIPAL
 * ============================================================================
 * 
 * PROPÓSITO:
 * Este es el único punto de entrada de la API REST.
 * Todas las peticiones HTTP llegan aquí y son enrutadas al recurso correcto.
 * 
 * ARQUITECTURA:
 * - Front Controller Pattern: Un solo archivo maneja todas las peticiones
 * - Modular: Cada recurso tiene su propio archivo en /resources
 * - RESTful: Usa métodos HTTP (GET, POST, PUT, DELETE) semánticamente
 * 
 * URL FORMAT:
 * http://localhost/api/api.php?resource=RECURSO&id=ID
 * 
 * Ejemplos:
 * - GET  ?resource=products           → Listar productos
 * - GET  ?resource=products&id=5      → Obtener producto 5
 * - POST ?resource=orders             → Crear orden
 * - DELETE ?resource=orders&id=7      → Cancelar orden 7
 * 
 * RECURSOS DISPONIBLES:
 * - ping: Health check
 * - categories: Categorías de productos
 * - artisans: Artesanos/vendedores
 * - products: Productos
 * - customers: Clientes
 * - carts, cart_items: Carritos de compra
 * - orders: Pedidos (usa Patrón Mediator)
 * - catalog, low_stock, etc.: Reportes
 */
declare(strict_types=1);

// ============================================================================
// HEADERS CORS (Cross-Origin Resource Sharing)
// ============================================================================
// Permiten que el frontend Angular (en otro puerto/dominio) acceda a la API.
// Sin estos headers, el navegador bloquearía las peticiones.

header('Access-Control-Allow-Origin: *');                                    // Permite cualquier origen
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');     // Métodos HTTP permitidos
header('Access-Control-Allow-Headers: Content-Type, Authorization');         // Headers personalizados permitidos
header('Content-Type: application/json; charset=UTF-8');                     // Respuestas en JSON UTF-8

// ============================================================================
// MANEJO DE PREFLIGHT (OPTIONS)
// ============================================================================
// Los navegadores envían una petición OPTIONS antes de POST/PUT/DELETE
// para verificar que el servidor acepta CORS. Respondemos con 204 (sin cuerpo).
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================================
// AUTOLOADER DE COMPOSER
// ============================================================================
// Carga automática de dependencias instaladas via Composer (ej: php-amqplib).
// Si el archivo no existe, las dependencias de Composer no estarán disponibles.
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// ============================================================================
// INCLUDES CORE
// ============================================================================
// Cargamos los archivos fundamentales de la API en orden de dependencia:
// 1. config.php: Constantes de configuración (DB, RabbitMQ)
// 2. helpers.php: Funciones utilitarias (q, out, fail, etc.)
// 3. db.php: Función get_db_connection()
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db.php';

// ============================================================================
// INICIALIZACIÓN DE BASE DE DATOS
// ============================================================================
// Creamos la conexión PDO que usarán todos los recursos.
// Esta variable $pdo está disponible en los archivos de recursos incluidos.
$pdo = get_db_connection();

// ============================================================================
// ROUTING - ENRUTAMIENTO DE RECURSOS
// ============================================================================
// Leemos el parámetro 'resource' de la URL para saber qué recurso manejar.
// Si no viene, por defecto retornamos 'ping' (health check).
$resource = strtolower((string)q('resource', 'ping'));
$method = strtoupper($_SERVER['REQUEST_METHOD']);

try {
    // Switch basado en el recurso solicitado
    switch ($resource) {
        
        // ====================================================================
        // PING - Health Check
        // ====================================================================
        // Endpoint simple para verificar que la API está funcionando.
        // Útil para monitoreo y debugging.
        case 'ping':
            out([
                'ok' => true, 
                'service' => 'ecommerce_api', 
                'time' => date('c')  // ISO 8601 timestamp
            ]);
            break;

        // ====================================================================
        // CATEGORÍAS
        // ====================================================================
        case 'categories':
            require __DIR__ . '/resources/categories.php';
            break;

        // ====================================================================
        // ARTESANOS
        // ====================================================================
        case 'artisans':
            require __DIR__ . '/resources/artisans.php';
            break;

        // ====================================================================
        // PRODUCTOS
        // ====================================================================
        case 'products':
            require __DIR__ . '/resources/products.php';
            break;

        // ====================================================================
        // CLIENTES
        // ====================================================================
        case 'customers':
            require __DIR__ . '/resources/customers.php';
            break;

        // ====================================================================
        // CARRITOS Y ITEMS
        // ====================================================================
        // Ambos recursos son manejados por el mismo archivo.
        case 'carts':
        case 'cart_items':
            require __DIR__ . '/resources/carts.php';
            break;

        // ====================================================================
        // ÓRDENES - USA PATRÓN MEDIATOR
        // ====================================================================
        // Este es el recurso más complejo. Usa OrderMediator para coordinar
        // la creación de órdenes con stock, carrito, y notificaciones.
        case 'orders':
            require __DIR__ . '/resources/orders.php';
            break;

        // ====================================================================
        // REPORTES
        // ====================================================================
        // Múltiples reportes manejados por un solo archivo.
        case 'catalog':
        case 'low_stock':
        case 'inventory_overview':
        case 'category_totals':
            require __DIR__ . '/resources/reports.php';
            break;

        // ====================================================================
        // RECURSO NO ENCONTRADO
        // ====================================================================
        default:
            fail('Recurso no encontrado o no válido', 404);
    }
    
} catch (Throwable $e) {
    // ========================================================================
    // MANEJO GLOBAL DE ERRORES
    // ========================================================================
    // Si cualquier recurso lanza una excepción no manejada, la capturamos aquí.
    // Logueamos el error real para debugging y respondemos con error genérico.
    error_log('[API Error] ' . $e->getMessage());
    fail('Error interno del servidor', 500, ['detail' => $e->getMessage()]);
}
