<?php
/**
 * ============================================================================
 * ARCHIVO: helpers.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Contiene funciones utilitarias reutilizables en toda la API.
 * Simplifica tareas comunes como:
 * - Leer parámetros de query string
 * - Parsear body JSON
 * - Enviar respuestas estandarizadas
 * - Validar datos de entrada
 * - Paginación
 * 
 * FILOSOFÍA:
 * Estas funciones siguen el principio DRY (Don't Repeat Yourself).
 * En lugar de repetir código de validación en cada resource,
 * lo centralizamos aquí.
 */
declare(strict_types=1);

/**
 * ============================================================================
 * FUNCIÓN: q() - Query Parameter
 * ============================================================================
 * 
 * Lee un parámetro del query string ($_GET) de forma segura.
 * 
 * @param string $name Nombre del parámetro a leer
 * @param mixed $default Valor por defecto si no existe (default: null)
 * @return mixed El valor del parámetro o el default
 * 
 * @example
 * // URL: /api?resource=orders&id=5
 * q('resource');     // 'orders'
 * q('id');           // '5'
 * q('missing', 0);   // 0 (usa el default)
 */
function q(string $name, mixed $default = null): mixed {
    return isset($_GET[$name]) ? trim((string)$_GET[$name]) : $default;
}

/**
 * ============================================================================
 * FUNCIÓN: body_json() - Leer Body JSON
 * ============================================================================
 * 
 * Lee y parsea el cuerpo de la petición como JSON.
 * Útil para peticiones POST, PUT, PATCH que envían datos en el body.
 * 
 * @return array Array asociativo con los datos del body, o array vacío si falla
 * 
 * @example
 * // Body: {"cart_id": 5, "notes": "Entregar rápido"}
 * $data = body_json();
 * $cartId = $data['cart_id'];  // 5
 */
function body_json(): array {
    // Leemos el contenido raw del body de la petición
    $raw = file_get_contents('php://input');
    
    // Si está vacío, retornamos array vacío
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    
    // Decodificamos JSON a array asociativo
    $data = json_decode($raw, true);
    
    // Si falló el decode (JSON inválido), retornamos array vacío
    return is_array($data) ? $data : [];
}

/**
 * ============================================================================
 * FUNCIÓN: out() - Enviar Respuesta JSON
 * ============================================================================
 * 
 * Envía una respuesta JSON y termina la ejecución.
 * Estandariza todas las respuestas de la API.
 * 
 * @param array $data Datos a enviar como JSON
 * @param int $code Código HTTP de respuesta (default: 200)
 * @return void (termina la ejecución con exit)
 * 
 * @example
 * out(['ok' => true, 'data' => $products]);           // 200 OK
 * out(['ok' => true, 'data' => $newOrder], 201);      // 201 Created
 */
function out(array $data, int $code = 200): void {
    http_response_code($code);
    // JSON_UNESCAPED_UNICODE preserva caracteres como ñ, á, etc.
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * ============================================================================
 * FUNCIÓN: fail() - Enviar Respuesta de Error
 * ============================================================================
 * 
 * Envía una respuesta de error estandarizada y termina la ejecución.
 * Todas las respuestas de error incluyen {ok: false, error: "mensaje"}.
 * 
 * @param string $message Mensaje de error para el cliente
 * @param int $code Código HTTP de error (default: 400 Bad Request)
 * @param array $extra Campos adicionales para incluir en la respuesta
 * @return void (termina la ejecución con exit)
 * 
 * @example
 * fail('Carrito no encontrado', 404);
 * fail('Stock insuficiente', 409, ['available' => 5]);
 */
function fail(string $message, int $code = 400, array $extra = []): void {
    out(array_merge(['ok' => false, 'error' => $message], $extra), $code);
}

/**
 * ============================================================================
 * FUNCIÓN: paginateParams() - Obtener Parámetros de Paginación
 * ============================================================================
 * 
 * Lee y valida los parámetros de paginación del query string.
 * Aplica límites para evitar consultas demasiado grandes.
 * 
 * @return array [limit, offset, page]
 * 
 * @example
 * // URL: /api?resource=products&page=2&limit=10
 * [$limit, $offset, $page] = paginateParams();
 * // $limit = 10, $offset = 10, $page = 2
 */
function paginateParams(): array {
    // Página mínima es 1
    $page = max(1, (int)q('page', 1));
    
    // Límite entre 1 y 100 registros por página
    $limit = min(100, max(1, (int)q('limit', 20)));
    
    // Calculamos offset para la consulta SQL
    $offset = ($page - 1) * $limit;
    
    return [$limit, $offset, $page];
}

/**
 * ============================================================================
 * FUNCIÓN: require_param() - Parámetro Requerido
 * ============================================================================
 * 
 * Lee un parámetro del query string y falla si no existe.
 * 
 * @param string $name Nombre del parámetro requerido
 * @return string Valor del parámetro
 * @throws (via fail) Si el parámetro no existe
 * 
 * @example
 * $resource = require_param('resource');  // Falla con 400 si no existe
 */
function require_param(string $name): string {
    $value = q($name);
    if ($value === null || $value === '') {
        fail("Parámetro requerido: {$name}");
    }
    return $value;
}

/**
 * ============================================================================
 * FUNCIÓN: require_positive_int() - Validar Entero Positivo
 * ============================================================================
 * 
 * Valida que un valor sea un entero positivo (> 0).
 * Útil para validar IDs, cantidades, etc.
 * 
 * @param mixed $value Valor a validar
 * @param string $message Mensaje de error si falla
 * @return int El valor como entero
 * @throws (via fail) Si no es un entero positivo
 * 
 * @example
 * $cartId = require_positive_int($body['cart_id'], 'cart_id inválido');
 */
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

/**
 * ============================================================================
 * FUNCIÓN: ensure_id_param() - Asegurar Parámetro ID
 * ============================================================================
 * 
 * Combina require_param() y require_positive_int() para IDs.
 * Atajo común para rutas como GET /resource?id=5
 * 
 * @param string $name Nombre del parámetro (default: 'id')
 * @return int ID validado
 * 
 * @example
 * $orderId = ensure_id_param();        // Lee y valida $_GET['id']
 * $productId = ensure_id_param('pid'); // Lee y valida $_GET['pid']
 */
function ensure_id_param(string $name = 'id'): int {
    return require_positive_int(require_param($name), "Parámetro {$name} inválido");
}

/**
 * ============================================================================
 * FUNCIÓN: fetch_single() - Obtener Un Solo Registro
 * ============================================================================
 * 
 * Ejecuta una consulta y retorna un único registro.
 * Si no encuentra el registro, responde con 404.
 * 
 * @param PDO $pdo Conexión a base de datos
 * @param string $sql Consulta SQL con placeholders
 * @param array $bind Valores para los placeholders
 * @param string $notFoundMessage Mensaje de error si no encuentra
 * @return array El registro encontrado
 * 
 * @example
 * $order = fetch_single($pdo, 
 *     "SELECT * FROM orders WHERE order_id = :id", 
 *     [':id' => $orderId],
 *     'Orden no encontrada'
 * );
 */
function fetch_single(PDO $pdo, string $sql, array $bind, string $notFoundMessage): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    $row = $stmt->fetch();
    
    if (!$row) {
        fail($notFoundMessage, 404);
    }
    
    return $row;
}

/**
 * ============================================================================
 * FUNCIÓN: listWithPagination() - Listado con Paginación
 * ============================================================================
 * 
 * Ejecuta una consulta paginada y retorna los resultados con metadatos.
 * Ideal para endpoints de listado (GET /products, GET /orders, etc.)
 * 
 * @param PDO $pdo Conexión a base de datos
 * @param string $sql Consulta SQL principal (sin LIMIT/OFFSET)
 * @param string $sqlTotal Consulta para contar total de registros
 * @param array $bind Valores para los placeholders
 * @return array Estructura: {ok, meta: {page, limit, total}, data: [...]}
 * 
 * @example
 * $response = listWithPagination($pdo,
 *     "SELECT * FROM products ORDER BY name",
 *     "SELECT COUNT(*) FROM products"
 * );
 * // Retorna: {ok: true, meta: {page: 1, limit: 20, total: 150}, data: [...]}
 */
function listWithPagination(PDO $pdo, string $sql, string $sqlTotal, array $bind = []): array {
    // Obtenemos parámetros de paginación de la URL
    [$limit, $offset, $page] = paginateParams();
    
    // Primero contamos el total de registros (para saber cuántas páginas hay)
    $stmtTotal = $pdo->prepare($sqlTotal);
    $stmtTotal->execute($bind);
    $total = (int)$stmtTotal->fetchColumn();

    // Luego obtenemos solo los registros de la página actual
    $stmt = $pdo->prepare($sql . ' LIMIT :limit OFFSET :offset');
    
    // Bindeamos los parámetros originales de la consulta
    foreach ($bind as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    // Bindeamos limit y offset como enteros para evitar SQL injection
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    // Retornamos estructura estandarizada con metadatos de paginación
    return [
        'ok' => true,
        'meta' => [
            'page' => $page,      // Página actual
            'limit' => $limit,    // Registros por página
            'total' => $total     // Total de registros
        ],
        'data' => $stmt->fetchAll(),
    ];
}
