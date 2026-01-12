<?php
/**
 * ============================================================================
 * ARCHIVO: orders.php - RECURSO DE ÓRDENES
 * ============================================================================
 * 
 * PROPÓSITO:
 * Maneja todas las operaciones relacionadas con órdenes de compra.
 * Este es el recurso más complejo de la API y utiliza el Patrón Mediator.
 * 
 * REFACTORIZACIÓN CLEAN CODE:
 * Este archivo fue refactorizado para seguir principios de Clean Code.
 * ANTES: +300 líneas mezclando SQL, validaciones, stock, eventos...
 * DESPUÉS: ~90 líneas delegando al Mediator
 * 
 * ENDPOINTS:
 * - GET    /api.php?resource=orders          → Listar órdenes (paginado)
 * - GET    /api.php?resource=orders&id=7     → Obtener orden #7
 * - POST   /api.php?resource=orders          → Crear orden desde carrito
 * - DELETE /api.php?resource=orders&id=7     → Cancelar orden #7
 * 
 * PATRÓN MEDIATOR:
 * El OrderMediator coordina la interacción entre:
 * - InventoryService (stock)
 * - CartService (carrito)
 * - OrderService (persistencia)
 * - NotificationService (RabbitMQ)
 * 
 * Esto permite que el controlador (este archivo) sea "delgado" y
 * solo se encargue de routing y formateo de respuestas.
 */
declare(strict_types=1);

// ============================================================================
// IMPORTS - CLASES NECESARIAS
// ============================================================================
// Importamos las clases que vamos a usar.
// En PHP moderno con namespaces, usamos 'use' para acortar los nombres.
use Api\Patterns\OrderMediator;
use Api\Services\InventoryService;
use Api\Services\CartService;
use Api\Services\OrderService;
use Api\Services\NotificationService;

// ============================================================================
// CARGA DE DEPENDENCIAS
// ============================================================================
// Como no tenemos autoloading PSR-4 configurado, cargamos manualmente.
// OrderMediator.php a su vez carga los servicios que necesita.
// En un proyecto con Composer autoload, esto no sería necesario.
require_once __DIR__ . '/../patterns/OrderMediator.php';

// ============================================================================
// INICIALIZACIÓN DE DEPENDENCIAS (MANUAL DI)
// ============================================================================
// En un framework moderno (Laravel, Symfony), esto vendría de un
// Contenedor de Inyección de Dependencias (DI Container).
// 
// Aquí lo hacemos manualmente, pero el principio es el mismo:
// 1. Creamos las dependencias
// 2. Las inyectamos en el constructor del Mediator
// 
// Nota: $pdo viene de api.php (está disponible en el scope)
$inventoryService = new InventoryService($pdo);
$cartService = new CartService($pdo);
$orderService = new OrderService($pdo);
$notificationService = new NotificationService();

// Creamos el Mediator con todas sus dependencias inyectadas
$mediator = new OrderMediator(
    $pdo,
    $inventoryService,
    $cartService,
    $orderService,
    $notificationService
);

// ============================================================================
// GET - LISTAR U OBTENER ÓRDENES
// ============================================================================
// - GET ?resource=orders → Lista paginada de todas las órdenes
// - GET ?resource=orders&id=7 → Detalles de la orden #7
if ($method === 'GET') {
    $id = q('id');  // Lee el parámetro 'id' si existe
    
    if ($id) {
        // --------------------------------------------------------------------
        // OBTENER DETALLE DE UNA ORDEN
        // --------------------------------------------------------------------
        try {
            // Validamos que el ID sea un entero positivo
            $orderId = require_positive_int($id);
            
            // Delegamos al servicio (no al mediator, porque es solo lectura)
            $order = $orderService->getOrderDetails($orderId);
            
            out(['ok' => true, 'data' => $order]);
        } catch (Exception $e) {
            fail($e->getMessage(), 404);
        }
    } else {
        // --------------------------------------------------------------------
        // LISTAR TODAS LAS ÓRDENES (PAGINADO)
        // --------------------------------------------------------------------
        // Usamos la función helper listWithPagination para simplificar.
        // TODO: Podríamos mover esto a OrderService->listOrders()
        $response = listWithPagination(
            $pdo,
            "SELECT o.order_id, o.order_number, o.status, o.payment_status,
                    o.subtotal, o.tax, o.shipping_cost, o.total, o.currency,
                    o.placed_at, o.updated_at,
                    cu.email AS customer_email
             FROM orders o
             JOIN customers cu ON cu.customer_id = o.customer_id
             ORDER BY o.placed_at DESC",
            "SELECT COUNT(*) FROM orders"
        );
        out($response);
    }
}

// ============================================================================
// POST - CREAR ORDEN (USA MEDIATOR)
// ============================================================================
// Body esperado: { "cart_id": 5, "status": "pending", "notes": "..." }
// 
// FLUJO COMPLETO:
// 1. Controller valida que cart_id sea válido
// 2. Mediator.placeOrder() hace TODA la magia:
//    - Valida carrito
//    - Verifica y reserva stock
//    - Crea orden e items
//    - Cierra carrito
//    - Publica evento a RabbitMQ
// 3. Controller formatea y retorna respuesta
if ($method === 'POST') {
    // Leemos el body JSON de la petición
    $body = body_json();
    
    // Validamos que venga cart_id y sea un entero positivo
    $cartId = require_positive_int($body['cart_id'] ?? null, 'cart_id inválido');
    
    try {
        // ====================================================================
        // DELEGACIÓN AL MEDIATOR
        // ====================================================================
        // Aquí está la magia del patrón Mediator.
        // Una sola línea reemplaza +200 líneas de código acoplado.
        // El Mediator coordina: stock, carrito, orden, eventos.
        $order = $mediator->placeOrder($cartId, $body);
        
        // Respondemos con 201 Created (estándar REST para POST exitoso)
        out(['ok' => true, 'data' => $order], 201);
        
    } catch (Exception $e) {
        // --------------------------------------------------------------------
        // ERROR DE LÓGICA DE NEGOCIO
        // --------------------------------------------------------------------
        // Estos son errores esperados: stock insuficiente, carrito inválido, etc.
        // Usamos 409 Conflict porque el estado actual impide la operación.
        fail($e->getMessage(), 409); 
        
    } catch (Throwable $t) {
        // --------------------------------------------------------------------
        // ERROR INESPERADO (BUG)
        // --------------------------------------------------------------------
        // Si llegamos aquí, algo salió muy mal (error de programación)
        error_log($t->getMessage());
        fail('Error interno procesando la orden', 500);
    }
}

// ============================================================================
// DELETE - CANCELAR ORDEN (USA MEDIATOR)
// ============================================================================
// DELETE ?resource=orders&id=7 → Cancela y revierte la orden #7
// 
// FLUJO:
// 1. Mediator.cancelOrder() revierte TODO:
//    - Devuelve stock
//    - Registra movimientos de ajuste
//    - Elimina la orden
//    - Reabre el carrito
if ($method === 'DELETE') {
    // Leemos y validamos el parámetro 'id'
    $id = ensure_id_param();

    try {
        // Delegamos la cancelación al Mediator
        $mediator->cancelOrder($id);
        
        out(['ok' => true, 'message' => 'Orden eliminada y stock revertido']);
        
    } catch (Exception $e) {
        fail($e->getMessage(), 400);
    }
}
