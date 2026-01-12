<?php
declare(strict_types=1);

if ($method === 'GET') {
  $id = q('id');
  if ($id) {
    $category = fetch_single(
      $pdo,
      "SELECT category_id, name, slug, description, created_at, updated_at
       FROM categories WHERE category_id = :id",
      [':id' => require_positive_int($id)],
      'Categoría no encontrada'
    );
    out(['ok' => true, 'data' => $category]);
  }
  $stmt = $pdo->query(
    "SELECT category_id, name, slug, description, created_at, updated_at
     FROM categories
     ORDER BY name"
  );
  out(['ok' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
  $body = body_json();
  foreach (['name', 'slug'] as $field) {
    if (!isset($body[$field]) || $body[$field] === '') {
      fail("Falta campo: {$field}");
    }
  }
  $stmt = $pdo->prepare(
    "INSERT INTO categories (name, slug, description)
     VALUES (:name, :slug, :description)"
  );
  $stmt->execute([
    ':name' => $body['name'],
    ':slug' => $body['slug'],
    ':description' => $body['description'] ?? null,
  ]);
  $created = fetch_single(
    $pdo,
    "SELECT category_id, name, slug, description, created_at, updated_at
     FROM categories WHERE category_id = :id",
    [':id' => (int)$pdo->lastInsertId()],
    'Categoría no encontrada'
  );
  out(['ok' => true, 'data' => $created], 201);
}

if ($method === 'PUT') {
  $id = ensure_id_param();
  $body = body_json();
  $fields = [];
  $bind = [':id' => $id];

  foreach (['name', 'slug', 'description'] as $field) {
    if (array_key_exists($field, $body)) {
      $fields[] = "{$field} = :{$field}";
      $bind[":{$field}"] = $body[$field] !== '' ? $body[$field] : null;
    }
  }

  if (!$fields) {
    fail('No hay campos para actualizar');
  }

  $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE category_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($bind);

  $updated = fetch_single(
    $pdo,
    "SELECT category_id, name, slug, description, created_at, updated_at
     FROM categories WHERE category_id = :id",
    [':id' => $id],
    'Categoría no encontrada'
  );
  out(['ok' => true, 'data' => $updated]);
}

if ($method === 'DELETE') {
  $id = ensure_id_param();
  $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = :id");
  $stmt->execute([':id' => $id]);
  if (!$stmt->rowCount()) {
    fail('Categoría no encontrada', 404);
  }
  out(['ok' => true, 'message' => 'Categoría eliminada']);
}
