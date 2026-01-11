<?php
declare(strict_types=1);

/**
 * Recurso: Orders
 * Refactorizado con Patrón Mediator para "Clean Code".
 */

use Api\Patterns\OrderMediator;
use Api\Services\InventoryService;
use Api\Services\CartService;
use Api\Services\OrderService;
use Api\Services\NotificationService;

// Dependencias necesarias (el autoload debería manejar esto en producción)
require_once __DIR__ . '/../patterns/OrderMediator.php';

// Inicialización de Dependencias
// En un framework moderno, esto vendría de un Cotenedor de Inyección de Dependencias (DI Container).
// Aquí lo hacemos manualmente.
$inventoryService = new InventoryService($pdo);
$cartService = new CartService($pdo);
$orderService = new OrderService($pdo);
$notificationService = new NotificationService();

$mediator = new OrderMediator(
    $pdo,
    $inventoryService,
    $cartService,
    $orderService,
    $notificationService
);

// --- Router del Recurso ---

if ($method === 'GET') {
    $id = q('id');
    if ($id) {
        // Detalle de orden
        try {
            $order = $orderService->getOrderDetails(require_positive_int($id));
            out(['ok' => true, 'data' => $order]);
        } catch (Exception $e) {
            fail($e->getMessage(), 404);
        }
    } else {
        // Listado (Aún usamos la lógica simple en helpers o query directa para paginación)
        // Podríamos mover esto a OrderService->listOrders()
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

if ($method === 'POST') {
    $body = body_json();
    $cartId = require_positive_int($body['cart_id'] ?? null, 'cart_id inválido');
    
    // Todo el trabajo sucio lo hace el Mediador
    try {
        $order = $mediator->placeOrder($cartId, $body);
        out(['ok' => true, 'data' => $order], 201);
    } catch (Exception $e) {
        // Clean Code: Mensajes de error claros.
        // Si es error de lógica (stock), 409 Conflict. Si es otro, 500 o 400.
        // Por simplicidad, 400 o 409 según el mensaje, o genérico.
        fail($e->getMessage(), 409); 
    } catch (Throwable $t) {
        error_log($t->getMessage());
        fail('Error interno procesando la orden', 500);
    }
}

if ($method === 'DELETE') {
    $id = ensure_id_param();

    try {
        $mediator->cancelOrder($id);
        out(['ok' => true, 'message' => 'Orden eliminada y stock revertido']);
    } catch (Exception $e) {
        fail($e->getMessage(), 400);
    }
}
