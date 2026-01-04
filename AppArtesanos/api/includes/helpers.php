<?php
declare(strict_types=1);

function q(string $name, mixed $default = null): mixed {
  return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

function body_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') {
    return [];
  }
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function out(array $data, int $code = 200): void {
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function fail(string $message, int $code = 400, array $extra = []): void {
  out(array_merge(['ok' => false, 'error' => $message], $extra), $code);
}

function paginateParams(): array {
  $page = max(1, (int)q('page', 1));
  $limit = min(100, max(1, (int)q('limit', 20)));
  $offset = ($page - 1) * $limit;
  return [$limit, $offset, $page];
}

function require_param(string $name): string {
  $value = q($name);
  if ($value === null || $value === '') {
    fail("Parámetro requerido: {$name}");
  }
  return $value;
}

function require_positive_int(mixed $value, string $message = 'Identificador inválido'): int {
  if (!is_numeric($value)) {
    fail($message);
  }
  $int = (int)$value;
  if ($int <= 0) {
    fail($message);
  }
  return $int;
}

function ensure_id_param(string $name = 'id'): int {
  return require_positive_int(require_param($name), "Parámetro {$name} inválido");
}

function fetch_single(PDO $pdo, string $sql, array $bind, string $notFoundMessage): array {
  $stmt = $pdo->prepare($sql);
  $stmt->execute($bind);
  $row = $stmt->fetch();
  if (!$row) {
    fail($notFoundMessage, 404);
  }
  return $row;
}

function listWithPagination(PDO $pdo, string $sql, string $sqlTotal, array $bind = []): array {
  [$limit, $offset, $page] = paginateParams();
  $stmtTotal = $pdo->prepare($sqlTotal);
  $stmtTotal->execute($bind);
  $total = (int)$stmtTotal->fetchColumn();

  $stmt = $pdo->prepare($sql . ' LIMIT :limit OFFSET :offset');
  foreach ($bind as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();

  return [
    'ok' => true,
    'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total],
    'data' => $stmt->fetchAll(),
  ];
}
