<?php
declare(strict_types=1);

namespace Api\Services;

use PDO;
use Exception;

class CartService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getCart(int $cartId): array {
        // Reutilizamos la lógica de fetch_cart_summary o la reimplementamos limpia aqui.
        // Por consistencia con "Clean Code", reimplemento una consulta especifica eficiente.
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

    public function validateForCheckout(array $cart, array $items): void {
        if ($cart['status'] !== 'open') {
            throw new Exception("El carrito no está en estado 'open'.");
        }
        if (!$cart['customer_id']) {
            throw new Exception("El carrito no tiene un cliente asignado.");
        }
        if (empty($items)) {
            throw new Exception("El carrito está vacío.");
        }
    }

    public function closeCart(int $cartId): void {
        $stmt = $this->pdo->prepare("UPDATE carts SET status = 'converted' WHERE cart_id = :id");
        $stmt->execute([':id' => $cartId]);
    }
}
