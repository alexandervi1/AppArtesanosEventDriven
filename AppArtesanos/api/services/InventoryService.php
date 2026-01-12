<?php
/**
 * ============================================================================
 * ARCHIVO: InventoryService.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Servicio especializado en la gestión de inventario y stock de productos.
 * Es un "Colleague" en el Patrón Mediator - el Mediator lo coordina.
 * 
 * RESPONSABILIDADES (Single Responsibility Principle):
 * 1. Verificar disponibilidad de stock
 * 2. Reservar (restar) stock al crear órdenes
 * 3. Registrar movimientos de inventario para auditoría
 * 
 * NOTA IMPORTANTE SOBRE TRANSACCIONES:
 * Este servicio NO maneja transacciones propias.
 * El Mediator (OrderMediator) es quien controla la transacción.
 * Esto permite que si falla la reserva de stock, TODA la operación se revierta.
 */
declare(strict_types=1);

namespace Api\Services;

use PDO;
use Exception;

/**
 * Servicio de Inventario
 * 
 * Gestiona el stock de productos y registra movimientos para auditoría.
 * Trabaja dentro de transacciones controladas por OrderMediator.
 */
class InventoryService {
    
    /** @var PDO Conexión a base de datos (inyectada por el Mediator) */
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
     * MÉTODO: checkAndReserveStock() - Verificar y Reservar Stock
     * ========================================================================
     * 
     * Verifica que haya suficiente stock de un producto y lo reserva (resta).
     * 
     * PROCESO:
     * 1. SELECT stock FROM products ... FOR UPDATE
     *    - FOR UPDATE bloquea el registro para evitar condiciones de carrera
     *    - Otras transacciones deben esperar hasta que esta termine
     * 2. Si no hay suficiente stock → lanza Exception → Rollback
     * 3. Si hay stock → UPDATE para restar la cantidad
     * 
     * ¿POR QUÉ FOR UPDATE?
     * Sin bloqueo, dos usuarios podrían:
     * - Usuario A lee stock = 5
     * - Usuario B lee stock = 5
     * - Usuario A resta 3 (queda 2)
     * - Usuario B resta 4 (¡ERROR! No hay suficiente)
     * 
     * Con FOR UPDATE:
     * - Usuario A bloquea y lee stock = 5
     * - Usuario B ESPERA
     * - Usuario A resta 3, commit
     * - Usuario B lee stock = 2 (valor actualizado)
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad a reservar
     * @param string $productName Nombre del producto (para mensajes de error legibles)
     * @throws Exception Si el producto no existe o no hay suficiente stock
     */
    public function checkAndReserveStock(int $productId, int $quantity, string $productName): void {
        // Consultamos el stock actual CON BLOQUEO
        $stmt = $this->pdo->prepare(
            "SELECT stock FROM products WHERE product_id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $productId]);
        $stock = $stmt->fetchColumn();

        // Si el producto no existe en la BD
        if ($stock === false) {
             throw new Exception("Producto ID {$productId} no encontrado.");
        }

        // Si no hay suficiente stock
        if ((int)$stock < $quantity) {
            throw new Exception(
                "Stock insuficiente para el producto {$productName}. " .
                "Stock actual: {$stock}, Solicitado: {$quantity}"
            );
        }

        // Si hay stock suficiente, lo descontamos
        $this->updateStock($productId, $quantity);
    }

    /**
     * ========================================================================
     * MÉTODO: updateStock() - Actualizar Stock (Privado)
     * ========================================================================
     * 
     * Resta la cantidad especificada del stock del producto.
     * Es privado porque solo debe llamarse después de verificar disponibilidad.
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad a restar
     */
    private function updateStock(int $productId, int $quantity): void {
        $stmt = $this->pdo->prepare(
            "UPDATE products SET stock = stock - :quantity WHERE product_id = :id"
        );
        $stmt->execute([':quantity' => $quantity, ':id' => $productId]);
    }

    /**
     * ========================================================================
     * MÉTODO: logMovement() - Registrar Movimiento de Inventario
     * ========================================================================
     * 
     * Inserta un registro en la tabla inventory_movements para auditoría.
     * Cada venta, ajuste o devolución queda registrada.
     * 
     * TIPOS DE MOVIMIENTO:
     * - 'sale': Venta (quantity_change negativo)
     * - 'purchase': Compra/Ingreso (quantity_change positivo)
     * - 'adjustment': Ajuste manual (puede ser + o -)
     * 
     * @param int $productId ID del producto
     * @param int $quantity Cantidad vendida (se guarda como negativo)
     * @param string $orderNumber Número de orden como referencia
     * 
     * @example
     * // Al vender 3 unidades del producto 5 en orden ORD-123
     * $this->logMovement(5, 3, 'ORD-123');
     * // Se inserta: product_id=5, quantity_change=-3, movement_type='sale'
     */
    public function logMovement(int $productId, int $quantity, string $orderNumber): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO inventory_movements 
             (product_id, quantity_change, movement_type, reference, note)
             VALUES (:product_id, :quantity_change, 'sale', :reference, :note)"
        );
        $stmt->execute([
            ':product_id'      => $productId,
            ':quantity_change' => -($quantity), // Negativo porque es una VENTA (resta)
            ':reference'       => $orderNumber,
            ':note'            => 'Venta generada desde carrito'
        ]);
    }
}
