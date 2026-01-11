<?php
declare(strict_types=1);

if ($resource === 'catalog') {
  $stmt = $pdo->query("SELECT * FROM vw_product_catalog");
  out(['ok' => true, 'data' => $stmt->fetchAll()]);
}

if ($resource === 'low_stock') {
  $stmt = $pdo->query("SELECT * FROM vw_low_stock_products");
  out(['ok' => true, 'data' => $stmt->fetchAll()]);
}

if ($resource === 'inventory_overview') {
  $stmt = $pdo->query("SELECT * FROM vw_inventory_overview");
  out(['ok' => true, 'data' => $stmt->fetch()]);
}

if ($resource === 'category_totals') {
  $stmt = $pdo->query("SELECT * FROM vw_category_totals");
  out(['ok' => true, 'data' => $stmt->fetchAll()]);
}
