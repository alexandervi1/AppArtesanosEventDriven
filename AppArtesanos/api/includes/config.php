<?php
/**
 * ============================================================================
 * ARCHIVO: config.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Centraliza TODA la configuración de la aplicación en un solo lugar.
 * Usa variables de entorno cuando están disponibles, con valores por defecto
 * para desarrollo local.
 * 
 * VENTAJAS:
 * - Un solo lugar para cambiar configuraciones
 * - Fácil de adaptar a diferentes entornos (local, staging, producción)
 * - Seguro: las credenciales sensibles pueden venir de variables de entorno
 * 
 * USO:
 * Este archivo es incluido por db.php y events.php.
 * Las constantes definidas aquí están disponibles globalmente.
 */
declare(strict_types=1);

// ============================================================================
// CONFIGURACIÓN DE BASE DE DATOS (MySQL)
// ============================================================================
// Usamos getenv() para leer variables de entorno del sistema.
// El operador ?: proporciona un valor por defecto si la variable no existe.

/** @var string Host del servidor MySQL (por defecto: localhost) */
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');

/** @var string Nombre de la base de datos */
define('DB_NAME', getenv('DB_NAME') ?: 'ecommerce_artesanos');

/** @var string Usuario de MySQL (por defecto: root para XAMPP) */
define('DB_USER', getenv('DB_USER') ?: 'root');

/** @var string Contraseña de MySQL (por defecto: vacía para XAMPP) */
define('DB_PASS', getenv('DB_PASS') ?: '');

// ============================================================================
// CONFIGURACIÓN DE RABBITMQ (Mensajería/Eventos)
// ============================================================================
// RabbitMQ es el broker de mensajes que conecta la API con los Workers.
// Permite arquitectura Event-Driven y procesamiento asíncrono.

/**
 * @var bool Habilitar/deshabilitar publicación de eventos
 * Útil para desactivar RabbitMQ en entornos de prueba sin broker instalado.
 * Valores válidos: '1', 'true', 'on' = habilitado
 */
define('RABBITMQ_ENABLED', filter_var(getenv('RABBITMQ_ENABLED') ?: '1', FILTER_VALIDATE_BOOL));

/** @var string Host del servidor RabbitMQ */
define('RABBITMQ_HOST', getenv('RABBITMQ_HOST') ?: 'localhost');

/**
 * @var int Puerto AMQP de RabbitMQ
 * IMPORTANTE: 5672 es el puerto AMQP (para código)
 *             15672 es el puerto de la UI web de administración
 */
define('RABBITMQ_PORT', (int)(getenv('RABBITMQ_PORT') ?: 5672));

/** @var string Usuario de RabbitMQ */
define('RABBITMQ_USER', getenv('RABBITMQ_USER') ?: 'admin');

/** @var string Contraseña de RabbitMQ */
define('RABBITMQ_PASS', getenv('RABBITMQ_PASS') ?: 'admin');

/** @var string Virtual Host de RabbitMQ (por defecto: '/') */
define('RABBITMQ_VHOST', getenv('RABBITMQ_VHOST') ?: '/');

/**
 * @var string Nombre del Exchange para eventos de pedidos
 * Un Exchange es un "distribuidor" que enruta mensajes a las colas correctas.
 * Tipo 'topic' permite enrutar por patrones (ej: order.* va a cola orders)
 */
define('RABBITMQ_EXCHANGE_PEDIDOS', getenv('RABBITMQ_EXCHANGE_PEDIDOS') ?: 'orders.events');

/**
 * @var string Routing Key para eventos de pedido creado
 * El Worker que escucha 'order.created' recibirá este mensaje.
 */
define('RABBITMQ_RK_PEDIDO_CREADO', getenv('RABBITMQ_RK_PEDIDO_CREADO') ?: 'order.created');
