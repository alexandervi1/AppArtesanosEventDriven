<?php
declare(strict_types=1);

// Helpers locales para Carts
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

function ensure_cart_open(array $cart): void {
  if (!in_array($cart['status'], ['open'], true)) {
    fail('El carrito no está abierto para modificaciones', 409);
  }
}

function fetch_customer_by_identifier(PDO $pdo, array $payload): array {
  if (isset($payload['customer_id'])) {
    return fetch_single(
      $pdo,
      "SELECT * FROM customers WHERE customer_id = :id",
      [':id' => require_positive_int($payload['customer_id'], 'Cliente inválido')],
      'Cliente no encontrado'
    );
  }
  if (isset($payload['customer_email'])) {
    return fetch_single(
      $pdo,
      "SELECT * FROM customers WHERE email = :email",
      [':email' => $payload['customer_email']],
      'Cliente no encontrado'
    );
  }
  fail('Debe especificar customer_id o customer_email');
}

function fetch_product_by_identifier(PDO $pdo, array $payload): array {
  if (isset($payload['product_id'])) {
    return fetch_single(
      $pdo,
      "SELECT * FROM products WHERE product_id = :id",
      [':id' => require_positive_int($payload['product_id'], 'Producto inválido')],
      'Producto no encontrado'
    );
  }
  if (isset($payload['sku'])) {
    return fetch_single(
      $pdo,
      "SELECT * FROM products WHERE sku = :sku",
      [':sku' => strtoupper((string)$payload['sku'])],
      'Producto no encontrado'
    );
  }
  fail('Debe especificar product_id o sku');
}

function ensure_quantity(int $quantity): int {
    if ($quantity <= 0) {
      fail('La cantidad debe ser mayor a cero');
    }
    return $quantity;
  }

// Handler Logic - Carts
if ($resource === 'carts') {
  if ($method === 'GET') {
    $id = q('id');
    if ($id) {
      $cart = fetch_cart_summary($pdo, require_positive_int($id));
      out(['ok' => true, 'data' => $cart]);
    }

    $response = listWithPagination(
      $pdo,
      "SELECT c.cart_id, c.customer_id, c.status, c.created_at, c.updated_at,
              cu.email AS customer_email
       FROM carts c
       LEFT JOIN customers cu ON cu.customer_id = c.customer_id
       ORDER BY c.created_at DESC",
      "SELECT COUNT(*) FROM carts"
    );
    out($response);
  }

  if ($method === 'POST') {
    $body = body_json();
    $customerId = null;

    if (isset($body['customer_id']) || isset($body['customer_email'])) {
      $customer = fetch_customer_by_identifier($pdo, $body);
      $customerId = (int)$customer['customer_id'];
    }

    $status = $body['status'] ?? 'open';
    if (!in_array($status, ['open','converted','abandoned'], true)) {
      fail('Estado de carrito inválido');
    }

    $stmt = $pdo->prepare("INSERT INTO carts (customer_id, status) VALUES (:customer_id, :status)");
    $stmt->execute([
      ':customer_id' => $customerId,
      ':status' => $status,
    ]);

    $cart = fetch_cart_summary($pdo, (int)$pdo->lastInsertId());
    out(['ok' => true, 'data' => $cart], 201);
  }

  if ($method === 'PUT') {
    $id = ensure_id_param();
    $body = body_json();
    if (!$body) {
      fail('No hay datos para actualizar');
    }

    $cart = fetch_cart_summary($pdo, $id);
    $fields = [];
    $bind = [':id' => $id];

    if (array_key_exists('status', $body)) {
      $status = $body['status'];
      if (!in_array($status, ['open','converted','abandoned'], true)) {
        fail('Estado de carrito inválido');
      }
      $fields[] = "status = :status";
      $bind[':status'] = $status;
    }

    if (array_key_exists('customer_id', $body) || array_key_exists('customer_email', $body)) {
      $customer = fetch_customer_by_identifier($pdo, $body);
      $fields[] = "customer_id = :customer_id";
      $bind[':customer_id'] = (int)$customer['customer_id'];
    }

    if (!$fields) {
      fail('No hay campos válidos para actualizar');
    }

    $stmt = $pdo->prepare("UPDATE carts SET " . implode(', ', $fields) . " WHERE cart_id = :id");
    $stmt->execute($bind);

    $updated = fetch_cart_summary($pdo, $id);
    out(['ok' => true, 'data' => $updated]);
  }

  if ($method === 'DELETE') {
    $id = ensure_id_param();
    $stmt = $pdo->prepare("DELETE FROM carts WHERE cart_id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->rowCount()) {
      fail('Carrito no encontrado', 404);
    }
    out(['ok' => true, 'message' => 'Carrito eliminado']);
  }
}

// Handler Logic - Cart Items
if ($resource === 'cart_items') {
    if ($method === 'POST') {
        $body = body_json();
        $cartId = require_positive_int($body['cart_id'] ?? null, 'cart_id inválido');
        $quantity = ensure_quantity((int)($body['quantity'] ?? 0));
        $product = fetch_product_by_identifier($pdo, $body);

        $cart = fetch_cart_summary($pdo, $cartId);
        ensure_cart_open($cart);

        if ((int)$product['stock'] < $quantity) {
          fail('Stock insuficiente para el producto solicitado', 409);
        }

        $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id");
        $stmt->execute([':cart_id' => $cartId, ':product_id' => $product['product_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
          $newQuantity = (int)$existing['quantity'] + $quantity;
          $upd = $pdo->prepare("UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :id");
          $upd->execute([':quantity' => $newQuantity, ':id' => $existing['cart_item_id']]);
        } else {
          $ins = $pdo->prepare(
            "INSERT INTO cart_items (cart_id, product_id, quantity)
             VALUES (:cart_id, :product_id, :quantity)"
          );
          $ins->execute([
            ':cart_id' => $cartId,
            ':product_id' => $product['product_id'],
            ':quantity' => $quantity,
          ]);
        }

        $updated = fetch_cart_summary($pdo, $cartId);
        out(['ok' => true, 'data' => $updated]);
      }

      if ($method === 'PUT') {
        $body = body_json();
        $itemId = require_positive_int($body['cart_item_id'] ?? null, 'cart_item_id inválido');
        $quantity = (int)($body['quantity'] ?? 0);

        $item = fetch_single(
          $pdo,
          "SELECT cart_item_id, cart_id, product_id FROM cart_items WHERE cart_item_id = :id",
          [':id' => $itemId],
          'Ítem de carrito no encontrado'
        );

        $cart = fetch_cart_summary($pdo, (int)$item['cart_id']);
        ensure_cart_open($cart);

        if ($quantity <= 0) {
          $del = $pdo->prepare("DELETE FROM cart_items WHERE cart_item_id = :id");
          $del->execute([':id' => $itemId]);
        } else {
          $product = fetch_single(
            $pdo,
            "SELECT product_id, stock FROM products WHERE product_id = :id",
            [':id' => $item['product_id']],
            'Producto no encontrado'
          );
          if ((int)$product['stock'] < $quantity) {
            fail('Stock insuficiente para el producto solicitado', 409);
          }
          $upd = $pdo->prepare("UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :id");
          $upd->execute([':quantity' => $quantity, ':id' => $itemId]);
        }

        $updated = fetch_cart_summary($pdo, (int)$item['cart_id']);
        out(['ok' => true, 'data' => $updated]);
      }

      if ($method === 'DELETE') {
        $itemId = ensure_id_param();
        $item = fetch_single(
          $pdo,
          "SELECT cart_item_id, cart_id FROM cart_items WHERE cart_item_id = :id",
          [':id' => $itemId],
          'Ítem de carrito no encontrado'
        );
        $cartId = (int)$item['cart_id'];

        $cart = fetch_cart_summary($pdo, $cartId);
        ensure_cart_open($cart);

        $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_item_id = :id");
        $stmt->execute([':id' => $itemId]);

        $updated = fetch_cart_summary($pdo, $cartId);
        out(['ok' => true, 'data' => $updated]);
      }
}
