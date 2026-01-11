<?php
declare(strict_types=1);

namespace Api\Patterns;

use PDO;
use Exception;
use Throwable;
use Api\Services\InventoryService;
use Api\Services\CartService;
use Api\Services\OrderService;
use Api\Services\NotificationService;

// Manual imports due to lack of Composer autoloader for these new classes
require_once __DIR__ . '/Mediator.php';
require_once __DIR__ . '/../services/InventoryService.php';
require_once __DIR__ . '/../services/CartService.php';
require_once __DIR__ . '/../services/OrderService.php';
require_once __DIR__ . '/../services/NotificationService.php';

class OrderMediator implements Mediator {
    private PDO $pdo;
    private InventoryService $inventory;
    private CartService $cart;
    private OrderService $order;
    private NotificationService $notification;

    public function __construct(
        PDO $pdo,
        InventoryService $inventory,
        CartService $cart,
        OrderService $order,
        NotificationService $notification
    ) {
        $this->pdo = $pdo;
        $this->inventory = $inventory;
        $this->cart = $cart;
        $this->order = $order;
        $this->notification = $notification;
    }

    // Implementation regarding generic Mediator interface
    public function notify(object $sender, string $event, array $data = []): mixed {
        // En un patrón Mediator puro, los componentes llamarían a notify().
        // Para este caso de uso 'Place Order', usaremos un método específico.
        // Pero podríamos usar esto para eventos como 'stockChanged' -> 'checkCart'.
        return null; 
    }

    /**
     * Orchestrates the complex Order Placement flow.
     */
    public function placeOrder(int $cartId, array $orderParams): array {
        $this->pdo->beginTransaction();
        
        try {
            // 1. Validar Carrito
            $cart = $this->cart->getCart($cartId);
            $items = $this->cart->getCartItems($cartId);
            $this->cart->validateForCheckout($cart, $items);

            // 2. Calcular Totales y Reservar Stock
            $subtotal = 0.0;
            foreach ($items as $item) {
                // Check stock (lanzará excepción si falla)
                $this->inventory->checkAndReserveStock(
                    (int)$item['product_id'], 
                    (int)$item['quantity'], 
                    $item['name']
                );
                $subtotal += (float)$item['line_total'];
            }

            // 3. Preparar datos de la orden
            $tax = (float)($orderParams['tax'] ?? 0.0);
            $shipping = (float)($orderParams['shipping_cost'] ?? 0.0);
            $total = $subtotal + $tax + $shipping;
            $orderNumber = $this->order->generateOrderNumber(); // Generamos número único

            $orderData = [
                'customer_id'    => $cart['customer_id'],
                'cart_id'        => $cartId,
                'order_number'   => $orderNumber,
                'status'         => $orderParams['status'] ?? 'pending',
                'payment_status' => $orderParams['payment_status'] ?? 'pending',
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'shipping_cost'  => $shipping,
                'total'          => $total,
                'currency'       => $orderParams['currency'] ?? 'USD',
                'notes'          => $orderParams['notes'] ?? null,
            ];

            // 4. Crear Orden
            $orderId = $this->order->createOrder($orderData);

            // 5. Crear Items de Orden y Loguear Movimientos
            foreach ($items as $item) {
                $this->order->addOrderItem($orderId, [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'line_total' => $item['line_total']
                ]);

                $this->inventory->logMovement(
                    (int)$item['product_id'], 
                    (int)$item['quantity'], 
                    $orderNumber
                );
            }

            // 6. Cerrar Carrito
            $this->cart->closeCart($cartId);

            // 7. Commit Transacción
            $this->pdo->commit();

            // 8. Recuperar Orden completa para evento y respuesta
            $fullOrder = $this->order->getOrderDetails($orderId);
            
            // 9. Notificar (RabbitMQ)
            // Lo hacemos FUERA de la transacción de BD, pero dentro del try/catch
            // para que si falla el envío, al menos la orden se creó (o revertimos si es crítico).
            // Generalmente, si falla el evento, no revertimos la orden, solo logueamos.
            try {
                $this->notification->publishOrderCreated($fullOrder);
            } catch (Throwable $e) {
                error_log("OrderMediator: Error enviando notificación: " . $e->getMessage());
            }

            return $fullOrder;

        } catch (Throwable $tx) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Relanzamos para que el controlador (resource) maneje la respuesta HTTP
            throw $tx;
        }
    }

    /**
     * Reverses an order: Restocks items, logs movement, deletes order, opens cart.
     */
    public function cancelOrder(int $orderId): void {
        $this->pdo->beginTransaction();
        try {
            // 1. Obtener detalles para saber qué revertir
            // Usamos FOR UPDATE para bloquear
            $stmtLock = $this->pdo->prepare("SELECT order_id, cart_id, order_number FROM orders WHERE order_id = :id FOR UPDATE");
            $stmtLock->execute([':id' => $orderId]);
            $order = $stmtLock->fetch();

            if (!$order) {
                throw new Exception("Orden ID {$orderId} no encontrada.");
            }

            // 2. Obtener items
            $stmtItems = $this->pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = :id");
            $stmtItems->execute([':id' => $orderId]);
            $items = $stmtItems->fetchAll();

            // 3. Revertir Stock y Loguear
            foreach ($items as $item) {
                // Devolvemos stock (sumamos)
                $this->pdo->prepare("UPDATE products SET stock = stock + :qty WHERE product_id = :pid")
                     ->execute([':qty' => $item['quantity'], ':pid' => $item['product_id']]);

                $this->pdo->prepare(
                    "INSERT INTO inventory_movements (product_id, quantity_change, movement_type, reference, note)
                     VALUES (:pid, :qty, 'adjustment', :ref, :note)"
                )->execute([
                    ':pid' => $item['product_id'],
                    ':qty' => $item['quantity'],
                    ':ref' => $order['order_number'],
                    ':note' => 'Reverso por cancelación de orden'
                ]);
            }

            // 4. Eliminar Orden
            $this->pdo->prepare("DELETE FROM orders WHERE order_id = :id")->execute([':id' => $orderId]);

            // 5. Reabrir Carrito
            if ($order['cart_id']) {
                $this->pdo->prepare("UPDATE carts SET status = 'open' WHERE cart_id = :id")
                     ->execute([':id' => $order['cart_id']]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
