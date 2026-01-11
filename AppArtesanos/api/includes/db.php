<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php'; // Para usar fail() si es necesario en logs

function get_db_connection(): PDO {
  try {
    return new PDO(
      "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
      DB_USER,
      DB_PASS,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  } catch (Throwable $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connection error']);
    exit;
  }
}
