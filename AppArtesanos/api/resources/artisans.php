<?php
declare(strict_types=1);

if ($method === 'GET') {
  $id = q('id');
  if ($id) {
    $artisan = fetch_single(
      $pdo,
      "SELECT artisan_id, workshop_name, contact_name, email, phone, region, bio, instagram, created_at, updated_at
       FROM artisans WHERE artisan_id = :id",
      [':id' => require_positive_int($id)],
      'Artesano no encontrado'
    );
    out(['ok' => true, 'data' => $artisan]);
  }
  $stmt = $pdo->query(
    "SELECT artisan_id, workshop_name, contact_name, email, phone, region, bio, instagram, created_at, updated_at
     FROM artisans
     ORDER BY workshop_name"
  );
  out(['ok' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
  $body = body_json();
  if (!isset($body['workshop_name']) || $body['workshop_name'] === '') {
    fail('Falta campo: workshop_name');
  }
  $stmt = $pdo->prepare(
    "INSERT INTO artisans (workshop_name, contact_name, email, phone, region, bio, instagram)
     VALUES (:workshop_name, :contact_name, :email, :phone, :region, :bio, :instagram)"
  );
  $stmt->execute([
    ':workshop_name' => $body['workshop_name'],
    ':contact_name'  => $body['contact_name'] ?? null,
    ':email'         => $body['email'] ?? null,
    ':phone'         => $body['phone'] ?? null,
    ':region'        => $body['region'] ?? null,
    ':bio'           => $body['bio'] ?? null,
    ':instagram'     => $body['instagram'] ?? null,
  ]);
  $created = fetch_single(
    $pdo,
    "SELECT artisan_id, workshop_name, contact_name, email, phone, region, bio, instagram, created_at, updated_at
     FROM artisans WHERE artisan_id = :id",
    [':id' => (int)$pdo->lastInsertId()],
    'Artesano no encontrado'
  );
  out(['ok' => true, 'data' => $created], 201);
}

if ($method === 'PUT') {
  $id = ensure_id_param();
  $body = body_json();
  $fields = [];
  $bind = [':id' => $id];
  foreach (['workshop_name', 'contact_name', 'email', 'phone', 'region', 'bio', 'instagram'] as $field) {
    if (array_key_exists($field, $body)) {
      $fields[] = "{$field} = :{$field}";
      $bind[":{$field}"] = $body[$field] !== '' ? $body[$field] : null;
    }
  }
  if (!$fields) {
    fail('No hay campos para actualizar');
  }
  $sql = "UPDATE artisans SET " . implode(', ', $fields) . " WHERE artisan_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($bind);

  $updated = fetch_single(
    $pdo,
    "SELECT artisan_id, workshop_name, contact_name, email, phone, region, bio, instagram, created_at, updated_at
     FROM artisans WHERE artisan_id = :id",
    [':id' => $id],
    'Artesano no encontrado'
  );
  out(['ok' => true, 'data' => $updated]);
}

if ($method === 'DELETE') {
  $id = ensure_id_param();
  $stmt = $pdo->prepare("DELETE FROM artisans WHERE artisan_id = :id");
  $stmt->execute([':id' => $id]);
  if (!$stmt->rowCount()) {
    fail('Artesano no encontrado', 404);
  }
  out(['ok' => true, 'message' => 'Artesano eliminado']);
}
