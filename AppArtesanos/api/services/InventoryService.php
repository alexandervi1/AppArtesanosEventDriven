<?php
declare(strict_types=1);

namespace Api\Services;

use PDO;
use Exception;

class InventoryService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function checkAndReserveStock(int $productId, int $quantity, string $productName): void {
        $stmt = $this->pdo->prepare(
            "SELECT stock FROM products WHERE product_id = :id FOR UPDATE"
        );
        $stmt->execute([':id' => $productId]);
        $stock = $stmt->fetchColumn();

        if ($stock === false) {
             throw new Exception("Producto ID {$productId} no encontrado.");
        }

        if ((int)$stock < $quantity) {
            throw new Exception("Stock insuficiente para el producto {$productName}. Stock actual: {$stock}, Solicitado: {$quantity}");
        }

        // Si hay stock, lo descontamos
        $this->updateStock($productId, $quantity);
    }

    private function updateStock(int $productId, int $quantity): void {
        $stmt = $this->pdo->prepare(
            "UPDATE products SET stock = stock - :quantity WHERE product_id = :id"
        );
        $stmt->execute([':quantity' => $quantity, ':id' => $productId]);
    }

    public function logMovement(int $productId, int $quantity, string $orderNumber): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO inventory_movements (product_id, quantity_change, movement_type, reference, note)
             VALUES (:product_id, :quantity_change, 'sale', :reference, :note)"
        );
        $stmt->execute([
            ':product_id' => $productId,
            ':quantity_change' => -($quantity), // Venta = Resta
            ':reference' => $orderNumber,
            ':note' => 'Venta generada desde carrito'
        ]);
    }
}
