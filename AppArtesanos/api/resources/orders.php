<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/events.php';

// Reusing cart helpers since orders start from carts
// In a stricter refactor, these should be in a shared helper file.
// For now, I'll redefine generate_order_number here to be self-contained or
// it could be moved to helpers.php if widely used.
function generate_order_number(PDO $pdo): string {
  do {
    $candidate = 'ORD-' . date('YmdHis') . '-' . random_int(100, 999);
    $stmt = $pdo->prepare("SELECT 1 FROM orders WHERE order_number = :num LIMIT 1");
    $stmt->execute([':num' => $candidate]);
  } while ($stmt->fetchColumn());

  return $candidate;
}

// Ensure fetch_cart_summary and ensure_cart_open are available
// Since this file is included in api.php, if carts.php isn't included, we might miss them.
// To avoid duplication/redeclaration errors if both are included (unlikely given the router switch),
// it's safer to check existence or rely on a shared structure.
// Given strict refactoring, I will assume the router includes ONLY the relevant resource file.
// But Orders DEPEND on Carts logic. Ideally, Carts logic should be in a Service or Helper.
// For this quick refactor, I will COPY the necessary cart functions if function_exists check fails.

if (!function_exists('fetch_cart_summary')) {
    function fetch_cart_summary(PDO $pdo, int $cartId): array {
        $cart = fetch_single(
          $pdo,
          "SELECT c.cart_id, c.customer_id, c.status, c.created_at, c.updated_at,
                  cu.first_name, cu.last_name, cu.email
           FROM carts c
           LEFT JOIN customers cu ON cu.customer_id = c.customer_id
           WHERE c.cart_id = :id",
          [':id' => $cartId],
          'Carrito no encontrado'
        );

        $stmtItems = $pdo->prepare(
          "SELECT ci.cart_item_id, ci.product_id, ci.quantity,
                  p.sku, p.name, p.price,
                  ROUND(ci.quantity * p.price, 2) AS line_total
           FROM cart_items ci
           JOIN products p ON p.product_id = ci.product_id
           WHERE ci.cart_id = :id
           ORDER BY ci.cart_item_id"
        );
        $stmtItems->execute([':id' => $cartId]);
        $items = $stmtItems->fetchAll();

        $subtotal = 0.0;
        $totalItems = 0;
        foreach ($items as $item) {
          $subtotal += (float)$item['line_total'];
          $totalItems += (int)$item['quantity'];
        }

        $cart['items'] = $items;
        $cart['subtotal'] = round($subtotal, 2);
        $cart['total_items'] = $totalItems;

        return $cart;
    }
}

if (!function_exists('ensure_cart_open')) {
    function ensure_cart_open(array $cart): void {
        if (!in_array($cart['status'], ['open'], true)) {
          fail('El carrito no está abierto para modificaciones', 409);
        }
    }
}

if ($method === 'GET') {
  $id = q('id');
  if ($id) {
    $order = fetch_single(
      $pdo,
      "SELECT o.*, cu.first_name, cu.last_name, cu.email
       FROM orders o
       JOIN customers cu ON cu.customer_id = o.customer_id
       WHERE o.order_id = :id",
      [':id' => require_positive_int($id)],
      'Orden no encontrada'
    );

    $stmtItems = $pdo->prepare(
      "SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.unit_price, oi.line_total,
              p.sku, p.name
       FROM order_items oi
       JOIN products p ON p.product_id = oi.product_id
       WHERE oi.order_id = :id"
    );
    $stmtItems->execute([':id' => $order['order_id']]);
    $order['items'] = $stmtItems->fetchAll();

    out(['ok' => true, 'data' => $order]);
  }

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

if ($method === 'POST') {
  $body = body_json();
  $cartId = require_positive_int($body['cart_id'] ?? null, 'cart_id inválido');
  $tax = isset($body['tax']) ? (float)$body['tax'] : 0.0;
  $shipping = isset($body['shipping_cost']) ? (float)$body['shipping_cost'] : 0.0;
  $currency = $body['currency'] ?? 'USD';
  $status = $body['status'] ?? 'pending';
  $paymentStatus = $body['payment_status'] ?? 'pending';

  $cart = fetch_cart_summary($pdo, $cartId);
  if (!$cart['customer_id']) {
    fail('El carrito debe pertenecer a un cliente para generar una orden', 409);
  }
  ensure_cart_open($cart);
  if (empty($cart['items'])) {
    fail('El carrito no tiene productos', 409);
  }

  $pdo->beginTransaction();
  try {
    $subtotal = 0.0;
    foreach ($cart['items'] as $item) {
      $product = fetch_single(
        $pdo,
        "SELECT product_id, stock, price FROM products WHERE product_id = :id FOR UPDATE",
        [':id' => $item['product_id']],
        'Producto no encontrado'
      );
      if ((int)$product['stock'] < (int)$item['quantity']) {
        $pdo->rollBack();
        fail("Stock insuficiente para el producto {$item['name']}", 409);
      }
      $subtotal += (float)$item['line_total'];
    }

    $total = $subtotal + $tax + $shipping;
    $orderNumber = generate_order_number($pdo);

    $stmtOrder = $pdo->prepare(
      "INSERT INTO orders (customer_id, cart_id, order_number, status, payment_status,
                           subtotal, tax, shipping_cost, total, currency, notes)
       VALUES (:customer_id, :cart_id, :order_number, :status, :payment_status,
               :subtotal, :tax, :shipping_cost, :total, :currency, :notes)"
    );
    $stmtOrder->execute([
      ':customer_id' => $cart['customer_id'],
      ':cart_id' => $cartId,
      ':order_number' => $orderNumber,
      ':status' => $status,
      ':payment_status' => $paymentStatus,
      ':subtotal' => $subtotal,
      ':tax' => $tax,
      ':shipping_cost' => $shipping,
      ':total' => $total,
      ':currency' => $currency,
      ':notes' => $body['notes'] ?? null,
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $stmtItem = $pdo->prepare(
      "INSERT INTO order_items (order_id, product_id, quantity, unit_price, line_total)
       VALUES (:order_id, :product_id, :quantity, :unit_price, :line_total)"
    );
    $stmtInventory = $pdo->prepare(
      "INSERT INTO inventory_movements (product_id, quantity_change, movement_type, reference, note)
       VALUES (:product_id, :quantity_change, 'sale', :reference, :note)"
    );
    $stmtStock = $pdo->prepare(
      "UPDATE products SET stock = stock - :quantity WHERE product_id = :product_id"
    );

    foreach ($cart['items'] as $item) {
      $stmtItem->execute([
        ':order_id' => $orderId,
        ':product_id' => $item['product_id'],
        ':quantity' => (int)$item['quantity'],
        ':unit_price' => (float)$item['price'],
        ':line_total' => (float)$item['line_total'],
      ]);

      $stmtInventory->execute([
        ':product_id' => $item['product_id'],
        ':quantity_change' => -((int)$item['quantity']),
        ':reference' => $orderNumber,
        ':note' => 'Venta generada desde carrito',
      ]);

      $stmtStock->execute([
        ':quantity' => (int)$item['quantity'],
        ':product_id' => $item['product_id'],
      ]);
    }

    $stmtCart = $pdo->prepare("UPDATE carts SET status = 'converted' WHERE cart_id = :id");
    $stmtCart->execute([':id' => $cartId]);

    $pdo->commit();

    $order = fetch_single(
      $pdo,
      "SELECT o.*, cu.first_name, cu.last_name, cu.email
       FROM orders o
       JOIN customers cu ON cu.customer_id = o.customer_id
       WHERE o.order_id = :id",
      [':id' => $orderId],
      'Orden no encontrada'
    );

    $stmtItems = $pdo->prepare(
      "SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.unit_price, oi.line_total,
              p.sku, p.name
       FROM order_items oi
       JOIN products p ON p.product_id = oi.product_id
       WHERE oi.order_id = :id"
    );
    $stmtItems->execute([':id' => $orderId]);
    $order['items'] = $stmtItems->fetchAll();

    publicar_evento_pedido_creado($order);

    out(['ok' => true, 'data' => $order], 201);
  } catch (Throwable $tx) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $tx;
  }
}

if ($method === 'DELETE') {
  $id = ensure_id_param();

  $pdo->beginTransaction();
  try {
    $order = fetch_single(
      $pdo,
      "SELECT order_id, cart_id, order_number FROM orders WHERE order_id = :id FOR UPDATE",
      [':id' => $id],
      'Orden no encontrada'
    );

    $stmtItems = $pdo->prepare(
      "SELECT product_id, quantity FROM order_items WHERE order_id = :id"
    );
    $stmtItems->execute([':id' => $id]);
    $items = $stmtItems->fetchAll();

    foreach ($items as $item) {
      $pdo->prepare(
        "UPDATE products SET stock = stock + :quantity WHERE product_id = :product_id"
      )->execute([
        ':quantity' => (int)$item['quantity'],
        ':product_id' => $item['product_id'],
      ]);

      $pdo->prepare(
        "INSERT INTO inventory_movements (product_id, quantity_change, movement_type, reference, note)
         VALUES (:product_id, :quantity_change, 'adjustment', :reference, :note)"
      )->execute([
        ':product_id' => $item['product_id'],
        ':quantity_change' => (int)$item['quantity'],
        ':reference' => $order['order_number'],
        ':note' => 'Reverso por eliminación de orden',
      ]);
    }

    $pdo->prepare("DELETE FROM orders WHERE order_id = :id")->execute([':id' => $id]);

    if ($order['cart_id']) {
      $pdo->prepare("UPDATE carts SET status = 'open' WHERE cart_id = :id")
          ->execute([':id' => $order['cart_id']]);
    }

    $pdo->commit();
    out(['ok' => true, 'message' => 'Orden eliminada']);
  } catch (Throwable $tx) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $tx;
  }
}
