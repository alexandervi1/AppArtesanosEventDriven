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

// ============================================================================
// IMPORTACIÓN DE DEPENDENCIAS
// ============================================================================
// Como no usamos Composer autoload PSR-4, incluimos los archivos manualmente.
// En un proyecto con autoloading configurado, estos require_once no serían necesarios.
require_once __DIR__ . '/Mediator.php';
require_once __DIR__ . '/../services/InventoryService.php';
require_once __DIR__ . '/../services/CartService.php';
require_once __DIR__ . '/../services/OrderService.php';
require_once __DIR__ . '/../services/NotificationService.php';

/**
 * ============================================================================
 * CLASE OrderMediator - PATRÓN MEDIATOR
 * ============================================================================
 * 
 * PROPÓSITO:
 * Esta clase actúa como un "director de orquesta" que coordina la interacción
 * entre múltiples servicios durante el proceso de creación y cancelación de órdenes.
 * 
 * BENEFICIOS DEL PATRÓN MEDIATOR:
 * 1. DESACOPLAMIENTO: Los servicios no se conocen entre sí, solo conocen al Mediator.
 * 2. SINGLE RESPONSIBILITY: Cada servicio hace una sola cosa bien.
 * 3. TRANSACCIONALIDAD: El Mediator controla toda la transacción de BD.
 * 4. TESTABILIDAD: Fácil de mockear servicios para pruebas unitarias.
 * 5. MANTENIBILIDAD: Cambiar un servicio no afecta a los demás.
 * 
 * FLUJO PRINCIPAL (placeOrder):
 * Controller → Mediator → [CartService, InventoryService, OrderService, NotificationService]
 * 
 * @implements Mediator
 */
class OrderMediator implements Mediator {
    
    // ========================================================================
    // PROPIEDADES - DEPENDENCIAS INYECTADAS
    // ========================================================================
    
    /** @var PDO Conexión a la base de datos MySQL */
    private PDO $pdo;
    
    /** @var InventoryService Servicio para gestión de stock y movimientos */
    private InventoryService $inventory;
    
    /** @var CartService Servicio para validación y gestión de carritos */
    private CartService $cart;
    
    /** @var OrderService Servicio para persistencia de órdenes */
    private OrderService $order;
    
    /** @var NotificationService Servicio para publicar eventos a RabbitMQ */
    private NotificationService $notification;

    /**
     * ========================================================================
     * CONSTRUCTOR - INYECCIÓN DE DEPENDENCIAS
     * ========================================================================
     * 
     * Recibe todas las dependencias como parámetros. Esto permite:
     * - Sustituir servicios fácilmente (ej: MockInventoryService para tests)
     * - Mantener el código flexible y extensible
     * - Seguir el principio de Inversión de Dependencias (SOLID)
     * 
     * @param PDO $pdo Conexión a base de datos
     * @param InventoryService $inventory Servicio de inventario
     * @param CartService $cart Servicio de carrito
     * @param OrderService $order Servicio de órdenes
     * @param NotificationService $notification Servicio de notificaciones
     */
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

    /**
     * ========================================================================
     * MÉTODO notify() - INTERFAZ MEDIATOR
     * ========================================================================
     * 
     * Este método está definido en la interfaz Mediator pero NO LO USAMOS
     * en esta implementación. ¿Por qué?
     * 
     * RAZÓN: Para un flujo lineal como crear órdenes, es más claro usar
     * métodos específicos (placeOrder, cancelOrder) que un sistema genérico
     * de eventos con notify().
     * 
     * CUÁNDO SERÍA ÚTIL notify():
     * Si necesitáramos comunicación bidireccional entre servicios.
     * Ej: InventoryService detecta stock bajo → notify('stockLow') → Mediator
     *     reacciona enviando email al admin.
     * 
     * @param object $sender El componente que inicia la acción
     * @param string $event Nombre del evento
     * @param array $data Datos adicionales
     * @return mixed
     */
    public function notify(object $sender, string $event, array $data = []): mixed {
        // Implementación vacía - usamos métodos específicos en su lugar
        return null; 
    }

    /**
     * ========================================================================
     * MÉTODO placeOrder() - ORQUESTACIÓN DE CREACIÓN DE ORDEN
     * ========================================================================
     * 
     * Este es el método principal que coordina TODO el proceso de checkout.
     * 
     * FLUJO DETALLADO:
     * ┌─────────────────────────────────────────────────────────────┐
     * │                   TRANSACCIÓN ATÓMICA                       │
     * │                                                             │
     * │  1. Obtener y validar carrito                               │
     * │  2. Verificar y reservar stock de cada producto             │
     * │  3. Calcular totales (subtotal + tax + shipping)            │
     * │  4. Generar número de orden único (ORD-YYYYMMDD-XXX)        │
     * │  5. Insertar orden en tabla 'orders'                        │
     * │  6. Insertar items en tabla 'order_items'                   │
     * │  7. Registrar movimientos en 'inventory_movements'          │
     * │  8. Cerrar carrito (status = 'converted')                   │
     * │  9. COMMIT - Todo se guarda                                 │
     * │                                                             │
     * │  Si CUALQUIER PASO FALLA → ROLLBACK automático              │
     * └─────────────────────────────────────────────────────────────┘
     *                            ↓
     *         10. Publicar evento a RabbitMQ (fuera de transacción)
     *                            ↓
     *         11. Retornar datos completos de la orden
     * 
     * @param int $cartId ID del carrito a convertir en orden
     * @param array $orderParams Parámetros adicionales (tax, shipping, notes, etc.)
     * @return array Datos completos de la orden creada
     * @throws Exception Si el carrito no es válido, no hay stock, etc.
     */
    public function placeOrder(int $cartId, array $orderParams): array {
        
        // ====================================================================
        // INICIO DE TRANSACCIÓN
        // ====================================================================
        // Abrimos una transacción para garantizar atomicidad (todo o nada).
        // Si algo falla, se revierte todo automáticamente.
        $this->pdo->beginTransaction();
        
        try {
            // ================================================================
            // PASO 1: VALIDAR CARRITO
            // ================================================================
            // Obtenemos los datos del carrito y sus items.
            // Si el carrito no existe, getCart() lanzará una excepción.
            $cart = $this->cart->getCart($cartId);
            $items = $this->cart->getCartItems($cartId);
            
            // Validamos que el carrito esté listo para checkout:
            // - Debe estar en estado 'open'
            // - Debe tener un customer_id asignado
            // - Debe tener al menos un item
            // Si falla cualquier validación, lanza Exception.
            $this->cart->validateForCheckout($cart, $items);

            // ================================================================
            // PASO 2: VERIFICAR Y RESERVAR STOCK
            // ================================================================
            // Para cada producto en el carrito:
            // 1. Verificamos que haya stock suficiente (SELECT con FOR UPDATE)
            // 2. Restamos la cantidad del stock (UPDATE products SET stock = stock - qty)
            // 
            // Si NO hay stock suficiente, lanza Exception y hace rollback.
            $subtotal = 0.0;
            foreach ($items as $item) {
                // checkAndReserveStock() hace SELECT FOR UPDATE para bloquear el producto
                // y evitar condiciones de carrera, luego resta el stock
                $this->inventory->checkAndReserveStock(
                    (int)$item['product_id'], 
                    (int)$item['quantity'], 
                    $item['name']  // Para mensajes de error legibles
                );
                
                // Acumulamos el subtotal para la orden
                $subtotal += (float)$item['line_total'];
            }

            // ================================================================
            // PASO 3: PREPARAR DATOS DE LA ORDEN
            // ================================================================
            // Extraemos valores opcionales con defaults
            $tax = (float)($orderParams['tax'] ?? 0.0);
            $shipping = (float)($orderParams['shipping_cost'] ?? 0.0);
            $total = $subtotal + $tax + $shipping;
            
            // Generamos un número de orden único con formato: ORD-YYYYMMDDHHMMSS-XXX
            // El método verifica que no exista duplicado en la BD
            $orderNumber = $this->order->generateOrderNumber();

            // Armamos el array con todos los datos de la orden
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

            // ================================================================
            // PASO 4: CREAR ORDEN EN BASE DE DATOS
            // ================================================================
            // INSERT INTO orders (...) VALUES (...)
            // Retorna el order_id generado (AUTO_INCREMENT)
            $orderId = $this->order->createOrder($orderData);

            // ================================================================
            // PASO 5: CREAR ITEMS Y REGISTRAR MOVIMIENTOS DE INVENTARIO
            // ================================================================
            foreach ($items as $item) {
                // Insertamos cada item en la tabla order_items
                // Guardamos el precio unitario del momento de la compra
                $this->order->addOrderItem($orderId, [
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price'],
                    'line_total' => $item['line_total']
                ]);

                // Registramos el movimiento de inventario para auditoría
                // Tipo: 'sale', cantidad: negativa (resta)
                $this->inventory->logMovement(
                    (int)$item['product_id'], 
                    (int)$item['quantity'], 
                    $orderNumber  // Referencia para trazabilidad
                );
            }

            // ================================================================
            // PASO 6: CERRAR CARRITO
            // ================================================================
            // Cambiamos el estado del carrito de 'open' a 'converted'
            // Esto evita que se use el mismo carrito para otra orden
            $this->cart->closeCart($cartId);

            // ================================================================
            // PASO 7: CONFIRMAR TRANSACCIÓN
            // ================================================================
            // Si llegamos aquí sin errores, TODOS los cambios se confirman:
            // - Stock restado
            // - Orden creada
            // - Items insertados
            // - Movimientos registrados
            // - Carrito cerrado
            $this->pdo->commit();

            // ================================================================
            // PASO 8: RECUPERAR ORDEN COMPLETA
            // ================================================================
            // Obtenemos todos los datos de la orden para la respuesta
            // Incluye: datos del cliente, items con nombres de productos, etc.
            $fullOrder = $this->order->getOrderDetails($orderId);
            
            // ================================================================
            // PASO 9: NOTIFICAR A RABBITMQ (FUERA DE TRANSACCIÓN)
            // ================================================================
            // IMPORTANTE: Esta notificación está FUERA de la transacción de BD.
            // 
            // ¿Por qué? Si RabbitMQ falla, NO queremos perder la orden.
            // La orden ya está guardada (commit hecho), el evento es secundario.
            // 
            // Estrategia "Non-Blocking":
            // - Si funciona → El Worker recibe el evento
            // - Si falla → Solo logueamos el error, la orden sigue válida
            try {
                $this->notification->publishOrderCreated($fullOrder);
            } catch (Throwable $e) {
                // Solo logueamos, NO relanzamos la excepción
                error_log("OrderMediator: Error enviando notificación RabbitMQ: " . $e->getMessage());
            }

            // Retornamos los datos completos de la orden al controller
            return $fullOrder;

        } catch (Throwable $tx) {
            // ================================================================
            // MANEJO DE ERRORES - ROLLBACK
            // ================================================================
            // Si CUALQUIER paso anterior falló, revertimos TODO:
            // - Stock NO se resta
            // - Orden NO se crea
            // - Carrito sigue 'open'
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            // Re-lanzamos la excepción para que el controller (orders.php)
            // responda con el código HTTP apropiado (400, 409, 500, etc.)
            throw $tx;
        }
    }

    /**
     * ========================================================================
     * MÉTODO cancelOrder() - REVERSIÓN DE UNA ORDEN
     * ========================================================================
     * 
     * Revierte completamente una orden existente:
     * 1. Devuelve el stock de cada producto
     * 2. Registra movimientos de ajuste
     * 3. Elimina la orden de la BD
     * 4. Reabre el carrito original (si existe)
     * 
     * TODO: Para seguir mejor el principio SRP, este método debería
     * delegar la restauración de stock a InventoryService.restoreStock()
     * en lugar de ejecutar SQL directamente.
     * 
     * @param int $orderId ID de la orden a cancelar
     * @throws Exception Si la orden no existe
     */
    public function cancelOrder(int $orderId): void {
        
        // Iniciamos transacción para garantizar atomicidad
        $this->pdo->beginTransaction();
        
        try {
            // ================================================================
            // PASO 1: OBTENER Y BLOQUEAR LA ORDEN
            // ================================================================
            // Usamos FOR UPDATE para bloquear el registro y evitar que
            // otra petición concurrente modifique la misma orden
            $stmtLock = $this->pdo->prepare(
                "SELECT order_id, cart_id, order_number 
                 FROM orders 
                 WHERE order_id = :id 
                 FOR UPDATE"
            );
            $stmtLock->execute([':id' => $orderId]);
            $order = $stmtLock->fetch();

            // Si la orden no existe, lanzamos excepción
            if (!$order) {
                throw new Exception("Orden ID {$orderId} no encontrada.");
            }

            // ================================================================
            // PASO 2: OBTENER ITEMS DE LA ORDEN
            // ================================================================
            $stmtItems = $this->pdo->prepare(
                "SELECT product_id, quantity 
                 FROM order_items 
                 WHERE order_id = :id"
            );
            $stmtItems->execute([':id' => $orderId]);
            $items = $stmtItems->fetchAll();

            // ================================================================
            // PASO 3: REVERTIR STOCK Y REGISTRAR MOVIMIENTOS
            // ================================================================
            foreach ($items as $item) {
                // Devolvemos el stock al producto (sumamos)
                // NOTA: Idealmente esto estaría en InventoryService.restoreStock()
                $this->pdo->prepare(
                    "UPDATE products 
                     SET stock = stock + :qty 
                     WHERE product_id = :pid"
                )->execute([
                    ':qty' => $item['quantity'], 
                    ':pid' => $item['product_id']
                ]);

                // Registramos el movimiento de ajuste para auditoría
                // Tipo: 'adjustment', cantidad: positiva (suma)
                $this->pdo->prepare(
                    "INSERT INTO inventory_movements 
                     (product_id, quantity_change, movement_type, reference, note)
                     VALUES (:pid, :qty, 'adjustment', :ref, :note)"
                )->execute([
                    ':pid' => $item['product_id'],
                    ':qty' => $item['quantity'],  // Positivo porque es devolución
                    ':ref' => $order['order_number'],
                    ':note' => 'Reverso por cancelación de orden'
                ]);
            }

            // ================================================================
            // PASO 4: ELIMINAR LA ORDEN
            // ================================================================
            // Gracias a ON DELETE CASCADE en order_items, los items
            // se eliminan automáticamente al eliminar la orden
            $this->pdo->prepare(
                "DELETE FROM orders WHERE order_id = :id"
            )->execute([':id' => $orderId]);

            // ================================================================
            // PASO 5: REABRIR EL CARRITO (SI EXISTE)
            // ================================================================
            // Permitimos que el cliente vuelva a usar su carrito
            if ($order['cart_id']) {
                $this->pdo->prepare(
                    "UPDATE carts 
                     SET status = 'open' 
                     WHERE cart_id = :id"
                )->execute([':id' => $order['cart_id']]);
            }

            // ================================================================
            // CONFIRMAR TRANSACCIÓN
            // ================================================================
            $this->pdo->commit();
            
        } catch (Throwable $e) {
            // Si algo falla, revertimos todo
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
