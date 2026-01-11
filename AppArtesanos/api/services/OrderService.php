<?php
declare(strict_types=1);

namespace Api\Services;

use PDO;
use Exception;

class OrderService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function generateOrderNumber(): string {
        do {
            $candidate = 'ORD-' . date('YmdHis') . '-' . random_int(100, 999);
            $stmt = $this->pdo->prepare("SELECT 1 FROM orders WHERE order_number = :num LIMIT 1");
            $stmt->execute([':num' => $candidate]);
        } while ($stmt->fetchColumn());

        return $candidate;
    }

    public function createOrder(array $data): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO orders (customer_id, cart_id, order_number, status, payment_status,
                                 subtotal, tax, shipping_cost, total, currency, notes)
             VALUES (:customer_id, :cart_id, :order_number, :status, :payment_status,
                     :subtotal, :tax, :shipping_cost, :total, :currency, :notes)"
        );
        
        $stmt->execute([
            ':customer_id'   => $data['customer_id'],
            ':cart_id'       => $data['cart_id'],
            ':order_number'  => $data['order_number'],
            ':status'        => $data['status'],
            ':payment_status'=> $data['payment_status'],
            ':subtotal'      => $data['subtotal'],
            ':tax'           => $data['tax'],
            ':shipping_cost' => $data['shipping_cost'],
            ':total'         => $data['total'],
            ':currency'      => $data['currency'],
            ':notes'         => $data['notes'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function addOrderItem(int $orderId, array $item): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
             VALUES (:order_id, :product_id, :quantity, :unit_price, :line_total)"
        );
        $stmt->execute([
            ':order_id'    => $orderId,
            ':product_id'  => $item['product_id'],
            ':quantity'    => $item['quantity'],
            ':unit_price'  => $item['price'],
            ':line_total'  => $item['line_total'],
        ]);
    }

    public function getOrderDetails(int $orderId): array {
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

        // Items
        $stmtItems = $this->pdo->prepare(
            "SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.unit_price, oi.line_total,
                    p.sku, p.name, p.price as current_price
             FROM order_items oi
             JOIN products p ON p.product_id = oi.product_id
             WHERE oi.order_id = :id"
        );
        $stmtItems->execute([':id' => $orderId]);
        $order['items'] = $stmtItems->fetchAll();

        return $order;
    }
}
