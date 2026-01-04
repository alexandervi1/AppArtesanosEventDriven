<?php
declare(strict_types=1);

// Helpers locales para Products
function resolve_category_id(PDO $pdo, array $body): ?int {
  if (array_key_exists('category_id', $body)) {
    return require_positive_int($body['category_id'], 'category_id inválido');
  }
  if (array_key_exists('category_slug', $body)) {
    $cat = fetch_single(
      $pdo,
      "SELECT category_id FROM categories WHERE slug = :slug",
      [':slug' => $body['category_slug']],
      'La categoría indicada no existe'
    );
    return (int)$cat['category_id'];
  }
  return null;
}

function resolve_artisan_id(PDO $pdo, array $body): ?int {
  if (array_key_exists('artisan_id', $body)) {
    return require_positive_int($body['artisan_id'], 'artisan_id inválido');
  }
  if (array_key_exists('artisan_workshop', $body)) {
    $art = fetch_single(
      $pdo,
      "SELECT artisan_id FROM artisans WHERE workshop_name = :name",
      [':name' => $body['artisan_workshop']],
      'El taller/artesano indicado no existe'
    );
    return (int)$art['artisan_id'];
  }
  return null;
}

// Handler Logic
if ($method === 'GET') {
  $id            = q('id');
  $category_slug = q('category_slug');
  $artisan_name  = q('artisan');
  $search        = q('q');

  if ($id) {
    $product = fetch_single(
      $pdo,
      "SELECT p.*, c.name AS category, a.workshop_name AS artisan
       FROM products p
       JOIN categories c ON c.category_id = p.category_id
       JOIN artisans a   ON a.artisan_id   = p.artisan_id
       WHERE p.product_id = :id",
      [':id' => require_positive_int($id)],
      'Producto no encontrado'
    );
    out(['ok' => true, 'data' => $product]);
  }

  [$limit, $offset, $page] = paginateParams();
  $where = ["1=1"];
  $bind  = [];

  if ($category_slug) {
    $where[] = "c.slug = :slug";
    $bind[':slug'] = $category_slug;
  }
  if ($artisan_name) {
    $where[] = "a.workshop_name LIKE :art";
    $bind[':art'] = '%' . $artisan_name . '%';
  }
  if ($search) {
    $where[] = "(p.name LIKE :s OR p.sku LIKE :s)";
    $bind[':s'] = '%' . $search . '%';
  }

  $w = implode(' AND ', $where);

  $sqlTotal = "SELECT COUNT(*) AS total
               FROM products p
               JOIN categories c ON c.category_id = p.category_id
               JOIN artisans a   ON a.artisan_id   = p.artisan_id
               WHERE {$w}";
  $stT = $pdo->prepare($sqlTotal);
  $stT->execute($bind);
  $total = (int)$stT->fetchColumn();

  $sql = "SELECT p.product_id, p.sku, p.name, c.name AS category, a.workshop_name AS artisan,
                 p.price, p.stock, IFNULL(p.badge_label,'') AS badge_label, p.description, p.image_url, p.is_active,
                 p.created_at, p.updated_at
          FROM products p
          JOIN categories c ON c.category_id = p.category_id
          JOIN artisans a   ON a.artisan_id   = p.artisan_id
          WHERE {$w}
          ORDER BY p.created_at DESC
          LIMIT :limit OFFSET :offset";
  $stmt = $pdo->prepare($sql);
  foreach ($bind as $k => $v) {
    $stmt->bindValue($k, $v);
  }
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();

  out([
    'ok' => true,
    'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
    'data' => $stmt->fetchAll(),
  ]);
}

if ($method === 'POST') {
  $b = body_json();
  $required = ['sku','name','category_slug','artisan_workshop','price','stock'];
  foreach ($required as $k) {
    if (!isset($b[$k]) || $b[$k] === '') {
      fail("Falta campo: {$k}");
    }
  }

  $stmt = $pdo->prepare("CALL sp_register_product(:sku,:name,:cat,:art,:price,:stock,:badge,:descr,:img)");
  $stmt->execute([
    ':sku'   => $b['sku'],
    ':name'  => $b['name'],
    ':cat'   => $b['category_slug'],
    ':art'   => $b['artisan_workshop'],
    ':price' => (float)$b['price'],
    ':stock' => (int)$b['stock'],
    ':badge' => array_key_exists('badge_label', $b) ? $b['badge_label'] : null,
    ':descr' => array_key_exists('description', $b) ? $b['description'] : null,
    ':img'   => array_key_exists('image_url', $b) ? $b['image_url'] : null,
  ]);

  $q = $pdo->prepare(
    "SELECT p.product_id, p.sku, p.name, c.name AS category, a.workshop_name AS artisan,
            p.price, p.stock, IFNULL(p.badge_label,'') AS badge_label, p.description, p.image_url, p.is_active,
            p.created_at, p.updated_at
     FROM products p
     JOIN categories c ON c.category_id = p.category_id
     JOIN artisans a   ON a.artisan_id   = p.artisan_id
     WHERE p.sku = :sku
     ORDER BY p.product_id DESC
     LIMIT 1"
  );
  $q->execute([':sku' => $b['sku']]);
  $created = $q->fetch();

  out(['ok' => true, 'message' => 'Producto creado', 'data' => $created], 201);
}

if ($method === 'PUT') {
  $id = ensure_id_param();
  $body = body_json();
  if (!$body) {
    fail('No hay datos para actualizar');
  }

  $fields = [];
  $bind = [':id' => $id];

  foreach (['sku','name','price','stock','badge_label','description','image_url','is_active'] as $field) {
    if (array_key_exists($field, $body)) {
      $fields[] = "{$field} = :{$field}";
      if ($field === 'is_active') {
        $bind[":{$field}"] = (int)$body[$field] ? 1 : 0;
      } elseif ($field === 'price') {
        $bind[":{$field}"] = (float)$body[$field];
      } elseif ($field === 'stock') {
        $bind[":{$field}"] = (int)$body[$field];
      } else {
        $bind[":{$field}"] = $body[$field] !== '' ? $body[$field] : null;
      }
    }
  }

  $categoryId = resolve_category_id($pdo, $body);
  if ($categoryId !== null) {
    $fields[] = "category_id = :category_id";
    $bind[':category_id'] = $categoryId;
  }

  $artisanId = resolve_artisan_id($pdo, $body);
  if ($artisanId !== null) {
    $fields[] = "artisan_id = :artisan_id";
    $bind[':artisan_id'] = $artisanId;
  }

  if (!$fields) {
    fail('No hay campos válidos para actualizar');
  }

  $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE product_id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($bind);

  $updated = fetch_single(
    $pdo,
    "SELECT p.*, c.name AS category, a.workshop_name AS artisan
     FROM products p
     JOIN categories c ON c.category_id = p.category_id
     JOIN artisans a   ON a.artisan_id   = p.artisan_id
     WHERE p.product_id = :id",
    [':id' => $id],
    'Producto no encontrado'
  );
  out(['ok' => true, 'data' => $updated]);
}

if ($method === 'DELETE') {
  $id = ensure_id_param();
  $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = :id");
  $stmt->execute([':id' => $id]);
  if (!$stmt->rowCount()) {
    fail('Producto no encontrado', 404);
  }
  out(['ok' => true, 'message' => 'Producto eliminado']);
}
