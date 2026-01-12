<?php
/**
 * ============================================================================
 * ARCHIVO: CartService.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Servicio especializado en la gestión de carritos de compra.
 * Es un "Colleague" en el Patrón Mediator - el Mediator lo coordina.
 * 
 * RESPONSABILIDADES (Single Responsibility Principle):
 * 1. Obtener datos de un carrito
 * 2. Obtener items de un carrito con precios actuales
 * 3. Validar que un carrito esté listo para checkout
 * 4. Cerrar un carrito después de convertirlo en orden
 * 
 * CICLO DE VIDA DE UN CARRITO:
 * - 'open': Carrito activo, el usuario puede agregar/quitar items
 * - 'converted': Carrito convertido en orden, ya no se puede modificar
 * - 'abandoned': Carrito abandonado (no se completó la compra)
 */
declare(strict_types=1);

namespace Api\Services;

use PDO;
use Exception;

/**
 * Servicio de Carrito
 * 
 * Gestiona el ciclo de vida de los carritos de compra.
 * Valida condiciones previas al checkout.
 */
class CartService {
    
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
     * MÉTODO: getCart() - Obtener Datos del Carrito
     * ========================================================================
     * 
     * Recupera los datos básicos de un carrito por su ID.
     * 
     * @param int $cartId ID del carrito
     * @return array Datos del carrito [cart_id, customer_id, status]
     * @throws Exception Si el carrito no existe
     * 
     * @example
     * $cart = $this->getCart(5);
     * // ['cart_id' => 5, 'customer_id' => 12, 'status' => 'open']
     */
    public function getCart(int $cartId): array {
        $stmt = $this->pdo->prepare(
            "SELECT c.cart_id, c.customer_id, c.status
             FROM carts c
             WHERE c.cart_id = :id"
        );
        $stmt->execute([':id' => $cartId]);
        $cart = $stmt->fetch();

        if (!$cart) {
            throw new Exception("Carrito ID {$cartId} no encontrado.");
        }
        
        return $cart;
    }

    /**
     * ========================================================================
     * MÉTODO: getCartItems() - Obtener Items del Carrito
     * ========================================================================
     * 
     * Recupera todos los items de un carrito con información del producto
     * y el total calculado por línea.
     * 
     * NOTA IMPORTANTE:
     * El precio se toma de la tabla products (precio actual).
     * El line_total se calcula al vuelo: quantity * price
     * 
     * @param int $cartId ID del carrito
     * @return array Lista de items con: product_id, quantity, name, price, line_total
     * 
     * @example
     * $items = $this->getCartItems(5);
     * // [
     * //   ['product_id' => 1, 'quantity' => 2, 'name' => 'Jarrón', 'price' => 50.00, 'line_total' => 100.00],
     * //   ['product_id' => 3, 'quantity' => 1, 'name' => 'Collar', 'price' => 25.00, 'line_total' => 25.00]
     * // ]
     */
    public function getCartItems(int $cartId): array {
        $stmt = $this->pdo->prepare(
            "SELECT ci.product_id, ci.quantity, p.name, p.price,
                    ROUND(ci.quantity * p.price, 2) AS line_total
             FROM cart_items ci
             JOIN products p ON p.product_id = ci.product_id
             WHERE ci.cart_id = :id"
        );
        $stmt->execute([':id' => $cartId]);
        return $stmt->fetchAll();
    }

    /**
     * ========================================================================
     * MÉTODO: validateForCheckout() - Validar para Checkout
     * ========================================================================
     * 
     * Verifica que un carrito cumpla todas las condiciones para convertirse
     * en una orden. Lanza excepciones descriptivas si falla alguna.
     * 
     * VALIDACIONES:
     * 1. El carrito debe estar en estado 'open' (no convertido ni abandonado)
     * 2. El carrito debe tener un cliente asignado (customer_id)
     * 3. El carrito debe tener al menos un item
     * 
     * @param array $cart Datos del carrito (de getCart())
     * @param array $items Items del carrito (de getCartItems())
     * @throws Exception Si alguna validación falla
     * 
     * @example
     * $cart = $this->getCart(5);
     * $items = $this->getCartItems(5);
     * $this->validateForCheckout($cart, $items); // Lanza si no es válido
     */
    public function validateForCheckout(array $cart, array $items): void {
        // Validación 1: Estado del carrito
        if ($cart['status'] !== 'open') {
            throw new Exception("El carrito no está en estado 'open'.");
        }
        
        // Validación 2: Cliente asignado
        if (!$cart['customer_id']) {
            throw new Exception("El carrito no tiene un cliente asignado.");
        }
        
        // Validación 3: Items en el carrito
        if (empty($items)) {
            throw new Exception("El carrito está vacío.");
        }
    }

    /**
     * ========================================================================
     * MÉTODO: closeCart() - Cerrar Carrito
     * ========================================================================
     * 
     * Cambia el estado del carrito a 'converted' después de crear la orden.
     * Esto evita que el mismo carrito se use para crear otra orden.
     * 
     * @param int $cartId ID del carrito a cerrar
     * 
     * @example
     * // Después de crear la orden exitosamente:
     * $this->closeCart(5);
     * // El carrito 5 ahora tiene status = 'converted'
     */
    public function closeCart(int $cartId): void {
        $stmt = $this->pdo->prepare(
            "UPDATE carts SET status = 'converted' WHERE cart_id = :id"
        );
        $stmt->execute([':id' => $cartId]);
    }
}
