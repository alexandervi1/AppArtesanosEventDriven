<?php
declare(strict_types=1);

// Includes manuales
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../patterns/OrderMediator.php';
require_once __DIR__ . '/../services/InventoryService.php';
require_once __DIR__ . '/../services/CartService.php';
require_once __DIR__ . '/../services/OrderService.php';
require_once __DIR__ . '/../services/NotificationService.php';

use Api\Patterns\OrderMediator;
use Api\Services\InventoryService;
use Api\Services\CartService;
use Api\Services\OrderService;
use Api\Services\NotificationService;

try {
    echo "1. Conectando a BD...\n";
    $pdo = get_db_connection();
    
    echo "2. Inicializando Servicios...\n";
    $inv = new InventoryService($pdo);
    $cart = new CartService($pdo);
    $order = new OrderService($pdo);
    $notif = new NotificationService();
    
    echo "3. Inicializando Mediador...\n";
    $mediator = new OrderMediator($pdo, $inv, $cart, $order, $notif);

    // PASO 4: Preparar datos de prueba
    // Necesitamos un carrito válido. Vamos a crear uno "al vuelo" para el test si es posible,
    // o pedimos al usuario que use uno existente.
    // Para ser autónomos, insertaremos un carrito y un item.
    
    echo "4. Preparando datos de prueba (Carrito e Item)...\n";
    $pdo->exec("INSERT INTO customers (first_name, last_name, email) VALUES ('Test', 'Mediator', 'test@mediator.com') ON DUPLICATE KEY UPDATE first_name=first_name");
    $stmtCust = $pdo->prepare("SELECT customer_id FROM customers WHERE email = 'test@mediator.com'");
    $stmtCust->execute();
    $custId = $stmtCust->fetchColumn();

    $pdo->exec("INSERT INTO carts (customer_id, status) VALUES ($custId, 'open')");
    $cartId = (int)$pdo->lastInsertId();

    // Asegurar que existe el producto 1 con stock
    $pdo->exec("INSERT INTO products (product_id, name, sku, price, stock) VALUES (1, 'Test Product', 'TEST-001', 10.00, 100) ON DUPLICATE KEY UPDATE stock=100");

    // Asumimos producto ID 1 existe.
    $pdo->exec("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES ($cartId, 1, 1)");

    echo "   -> Carrito ID: $cartId creado.\n";

    echo "5. Ejecutando placeOrder()...\n";
    $orderData = [
        'status' => 'pending',
        'payment_status' => 'paid',
        'notes' => 'Test from PHP Script'
    ];

    $result = $mediator->placeOrder($cartId, $orderData);

    echo "6. ¡ÉXITO! Orden creada: " . $result['order_number'] . "\n";
    print_r($result);

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
