<?php
declare(strict_types=1);

if ($method === 'GET') {
  $id = q('id');
  $email = q('email');
  if ($id) {
    $customer = fetch_single(
      $pdo,
      "SELECT * FROM customers WHERE customer_id = :id",
      [':id' => require_positive_int($id)],
      'Cliente no encontrado'
    );
    out(['ok' => true, 'data' => $customer]);
  }
  if ($email) {
    $customer = fetch_single(
      $pdo,
      "SELECT * FROM customers WHERE email = :email",
      [':email' => $email],
      'Cliente no encontrado'
    );
    out(['ok' => true, 'data' => $customer]);
  }

  $stmt = $pdo->prepare(
    "SELECT * FROM customers ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
  );
  [$limit, $offset, $page] = paginateParams();
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();

  out(['ok' => true, 'data' => $stmt->fetchAll(), 'meta' => ['page' => $page, 'limit' => $limit]]);
}

if ($method === 'POST') {
  $body = body_json();
  foreach (['first_name','last_name','email'] as $field) {
    if (!isset($body[$field]) || $body[$field] === '') {
      fail("Falta campo: {$field}");
    }
  }
  $stmt = $pdo->prepare(
    "INSERT INTO customers (first_name, last_name, email, phone, province, city, address_line)
     VALUES (:first_name, :last_name, :email, :phone, :province, :city, :address)"
  );
  $stmt->execute([
    ':first_name' => $body['first_name'],
    ':last_name'  => $body['last_name'],
    ':email'      => $body['email'],
    ':phone'      => $body['phone'] ?? null,
    ':province'   => $body['province'] ?? null,
    ':city'       => $body['city'] ?? null,
    ':address'    => $body['address_line'] ?? null,
  ]);
  $created = fetch_single(
    $pdo,
    "SELECT * FROM customers WHERE customer_id = :id",
    [':id' => (int)$pdo->lastInsertId()],
    'Cliente no encontrado'
  );
  out(['ok' => true, 'data' => $created], 201);
}

if ($method === 'PUT') {
  $id = ensure_id_param();
  $body = body_json();
  $fields = [];
  $bind = [':id' => $id];
  foreach (['first_name','last_name','email','phone','province','city','address_line'] as $field) {
    if (array_key_exists($field, $body)) {
      $fields[] = "{$field} = :{$field}";
      $bind[":{$field}"] = $body[$field] !== '' ? $body[$field] : null;
    }
  }
  if (!$fields) {
    fail('No hay campos para actualizar');
  }
  $sql = "UPDATE customers SET " . implode(', ', $fields) . " WHERE customer_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($bind);

  $updated = fetch_single(
    $pdo,
    "SELECT * FROM customers WHERE customer_id = :id",
    [':id' => $id],
    'Cliente no encontrado'
  );
  out(['ok' => true, 'data' => $updated]);
}

if ($method === 'DELETE') {
  $id = ensure_id_param();
  $stmt = $pdo->prepare("DELETE FROM customers WHERE customer_id = :id");
  $stmt->execute([':id' => $id]);
  if (!$stmt->rowCount()) {
    fail('Cliente no encontrado', 404);
  }
  out(['ok' => true, 'message' => 'Cliente eliminado']);
}
