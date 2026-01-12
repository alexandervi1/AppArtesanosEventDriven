<?php
/**
 * ============================================================================
 * ARCHIVO: db.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Proporciona una función centralizada para obtener conexiones a la base de datos.
 * Encapsula la creación de PDO con configuración óptima para la aplicación.
 * 
 * CARACTERÍSTICAS:
 * - Usa PDO (PHP Data Objects) para acceso seguro a MySQL
 * - Configuración de errores como excepciones (más fácil de debuggear)
 * - Fetch mode ASSOC por defecto (arrays asociativos)
 * - Charset UTF-8 para soporte de caracteres especiales
 * 
 * USO:
 *   require_once 'includes/db.php';
 *   $pdo = get_db_connection();
 *   $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
 */
declare(strict_types=1);

// Importamos la configuración de base de datos (DB_HOST, DB_NAME, etc.)
require_once __DIR__ . '/config.php';

// Importamos helpers para usar fail() en caso de error
require_once __DIR__ . '/helpers.php';

/**
 * ============================================================================
 * FUNCIÓN: get_db_connection()
 * ============================================================================
 * 
 * Crea y retorna una conexión PDO a la base de datos MySQL.
 * 
 * CONFIGURACIÓN PDO:
 * - ERRMODE_EXCEPTION: Lanza excepciones en lugar de warnings silenciosos
 * - FETCH_ASSOC: Los resultados son arrays asociativos ['columna' => 'valor']
 * - charset=utf8mb4: Soporte completo de Unicode (incluye emojis)
 * 
 * MANEJO DE ERRORES:
 * Si la conexión falla, loguea el error, responde con HTTP 500 y termina.
 * Esto evita que la aplicación continúe en un estado inválido.
 * 
 * @return PDO Objeto de conexión a la base de datos
 * @throws Throwable En caso de error de conexión (capturado internamente)
 * 
 * @example
 * $pdo = get_db_connection();
 * $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
 * $stmt->execute([':id' => 1]);
 * $user = $stmt->fetch();
 */
function get_db_connection(): PDO {
    try {
        // Creamos la conexión PDO con las opciones recomendadas
        return new PDO(
            // DSN (Data Source Name): mysql:host=localhost;dbname=ecommerce_artesanos;charset=utf8mb4
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,    // Usuario de MySQL
            DB_PASS,    // Contraseña de MySQL
            [
                // Lanzar excepciones en errores SQL (no fallar silenciosamente)
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                
                // Retornar arrays asociativos por defecto en fetch()
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        // ====================================================================
        // MANEJO DE ERROR DE CONEXIÓN
        // ====================================================================
        // Logueamos el error real (para debugging en servidor)
        error_log('DB Connection Error: ' . $e->getMessage());
        
        // Respondemos con error genérico al cliente (no exponemos detalles)
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'DB connection error']);
        
        // Terminamos la ejecución - no tiene sentido continuar sin BD
        exit;
    }
}
