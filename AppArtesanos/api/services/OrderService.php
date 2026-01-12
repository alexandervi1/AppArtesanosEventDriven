<?php
/**
 * ============================================================================
 * ARCHIVO: OrderService.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Servicio especializado en la persistencia de órdenes en la base de datos.
 * Es un "Colleague" en el Patrón Mediator - el Mediator lo coordina.
 * 
 * RESPONSABILIDADES (Single Responsibility Principle):
 * 1. Generar números de orden únicos
 * 2. Insertar órdenes en la tabla 'orders'
 * 3. Insertar items en la tabla 'order_items'
 * 4. Recuperar detalles completos de una orden
 * 
 * NOTA SOBRE PRECIOS:
 * Los precios se guardan en order_items al momento de la compra.
 * Esto preserva el precio histórico aunque el producto cambie después.
 */
declare(strict_types=1);

namespace Api\Services;

use PDO;
use Exception;

/**
 * Servicio de Órdenes
 * 
 * Maneja toda la persistencia relacionada con órdenes de compra.
 * Genera números únicos y guarda el historial de precios.
 */
class OrderService {
    
    /** @var PDO Conexión a base de datos */
    private PDO $pdo;

    /**
     * Constructor con Inyección de Dependencias
     * 
     * @param PDO $pdo Conexión a base de datos
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * ========================================================================
     * MÉTODO: generateOrderNumber() - Generar Número de Orden Único
     * ========================================================================
     * 
     * Genera un número de orden con formato: ORD-YYYYMMDDHHMMSS-XXX
     * Ejemplo: ORD-20260111193500-742
     * 
     * ALGORITMO:
     * 1. Genera un candidato con timestamp + número aleatorio
     * 2. Verifica que no exista en la BD
     * 3. Si existe, repite el proceso
     * 4. Retorna el número único
     * 
     * ¿POR QUÉ NO USAR AUTO_INCREMENT?
     * - El order_id es interno (1, 2, 3...)
     * - El order_number es visible al cliente y más legible
     * - Incluye fecha para referencia rápida
     * 
     * @return string Número de orden único
     * 
     * @example
     * $orderNumber = $this->generateOrderNumber();
     * // 'ORD-20260111193500-742'
     */
    public function generateOrderNumber(): string {
        do {
            // Formato: ORD-AÑOMESDÍAHoraMinSeg-RANDOM
            $candidate = 'ORD-' . date('YmdHis') . '-' . random_int(100, 999);
            
            // Verificamos que no exista (muy poco probable, pero por seguridad)
            $stmt = $this->pdo->prepare(
                "SELECT 1 FROM orders WHERE order_number = :num LIMIT 1"
            );
            $stmt->execute([':num' => $candidate]);
            
        } while ($stmt->fetchColumn()); // Repite si ya existe

        return $candidate;
    }

    /**
     * ========================================================================
     * MÉTODO: createOrder() - Crear Orden
     * ========================================================================
     * 
     * Inserta una nueva orden en la tabla 'orders'.
     * 
     * CAMPOS:
     * - customer_id: Cliente que hace la compra
     * - cart_id: Carrito de origen (para trazabilidad)
     * - order_number: Número visible al cliente
     * - status: Estado de la orden (pending, processing, shipped, delivered)
     * - payment_status: Estado del pago (pending, paid, refunded)
     * - subtotal, tax, shipping_cost, total: Montos
     * - currency: Moneda (USD, MXN, etc.)
     * - notes: Notas del cliente
     * 
     * @param array $data Datos de la orden
     * @return int ID de la orden creada (AUTO_INCREMENT)
     * 
     * @example
     * $orderId = $this->createOrder([
     *     'customer_id' => 5,
     *     'cart_id' => 10,
     *     'order_number' => 'ORD-20260111-001',
     *     'status' => 'pending',
     *     'payment_status' => 'pending',
     *     'subtotal' => 100.00,
     *     'tax' => 16.00,
     *     'shipping_cost' => 10.00,
     *     'total' => 126.00,
     *     'currency' => 'USD',
     *     'notes' => 'Entregar en la tarde'
     * ]);
     */
    public function createOrder(array $data): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders 
             (customer_id, cart_id, order_number, status, payment_status,
              subtotal, tax, shipping_cost, total, currency, notes)
             VALUES 
             (:customer_id, :cart_id, :order_number, :status, :payment_status,
              :subtotal, :tax, :shipping_cost, :total, :currency, :notes)"
        );
        
        $stmt->execute([
            ':customer_id'    => $data['customer_id'],
            ':cart_id'        => $data['cart_id'],
            ':order_number'   => $data['order_number'],
            ':status'         => $data['status'],
            ':payment_status' => $data['payment_status'],
            ':subtotal'       => $data['subtotal'],
            ':tax'            => $data['tax'],
            ':shipping_cost'  => $data['shipping_cost'],
            ':total'          => $data['total'],
            ':currency'       => $data['currency'],
            ':notes'          => $data['notes'] ?? null,
        ]);

        // Retornamos el ID generado automáticamente
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * ========================================================================
     * MÉTODO: addOrderItem() - Agregar Item a la Orden
     * ========================================================================
     * 
     * Inserta un producto en la tabla 'order_items'.
     * 
     * IMPORTANTE:
     * Guardamos el precio al momento de la compra (unit_price).
     * Esto preserva el precio histórico aunque el producto cambie después.
     * 
     * @param int $orderId ID de la orden padre
     * @param array $item Datos del item [product_id, quantity, price, line_total]
     * 
     * @example
     * $this->addOrderItem(7, [
     *     'product_id' => 1,
     *     'quantity' => 2,
     *     'price' => 50.00,      // Precio al momento de compra
     *     'line_total' => 100.00
     * ]);
     */
    public function addOrderItem(int $orderId, array $item): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items 
             (order_id, product_id, quantity, unit_price, line_total)
             VALUES 
             (:order_id, :product_id, :quantity, :unit_price, :line_total)"
        );
        $stmt->execute([
            ':order_id'    => $orderId,
            ':product_id'  => $item['product_id'],
            ':quantity'    => $item['quantity'],
            ':unit_price'  => $item['price'],      // Precio histórico
            ':line_total'  => $item['line_total'],
        ]);
    }

    /**
     * ========================================================================
     * MÉTODO: getOrderDetails() - Obtener Detalles Completos
     * ========================================================================
     * 
     * Recupera todos los datos de una orden, incluyendo:
     * - Datos de la orden (totales, estados, fechas)
     * - Datos del cliente (nombre, email)
     * - Lista de items con productos
     * 
     * USADO POR:
     * - El Mediator para retornar la respuesta al cliente
     * - El NotificationService para enviar datos a RabbitMQ
     * 
     * @param int $orderId ID de la orden
     * @return array Orden completa con items
     * @throws Exception Si la orden no existe
     * 
     * @example
     * $order = $this->getOrderDetails(7);
     * // [
     * //   'order_id' => 7,
     * //   'order_number' => 'ORD-20260111-001',
     * //   'total' => 126.00,
     * //   'first_name' => 'Juan',
     * //   'email' => 'juan@email.com',
     * //   'items' => [
     * //     ['product_id' => 1, 'name' => 'Jarrón', 'quantity' => 2, ...]
     * //   ]
     * // ]
     */
    public function getOrderDetails(int $orderId): array {
        // Obtenemos la orden con datos del cliente
        $stmt = $this->pdo->prepare(
            "SELECT o.*, cu.first_name, cu.last_name, cu.email
             FROM orders o
             JOIN customers cu ON cu.customer_id = o.customer_id
             WHERE o.order_id = :id"
        );
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Error recuperando la orden ID {$orderId}.");
        }

        // Obtenemos los items con datos del producto
        $stmtItems = $this->pdo->prepare(
            "SELECT oi.order_item_id, oi.product_id, oi.quantity, 
                    oi.unit_price, oi.line_total,
                    p.sku, p.name, p.price as current_price
             FROM order_items oi
             JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = :id"
        );
        $stmtItems->execute([':id' => $orderId]);
        
        // Agregamos los items al array de la orden
        $order['items'] = $stmtItems->fetchAll();

        return $order;
    }
}
