<?php
declare(strict_types=1);

// Cargar variables de entorno si se usa phpdotenv (opcional, aquí usaremos getenv nativo o defaults)
// DB Config
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ecommerce_artesanos');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// RabbitMQ Config
define('RABBITMQ_ENABLED', filter_var(getenv('RABBITMQ_ENABLED') ?: '1', FILTER_VALIDATE_BOOL));
define('RABBITMQ_HOST', getenv('RABBITMQ_HOST') ?: 'localhost');
define('RABBITMQ_PORT', (int)(getenv('RABBITMQ_PORT') ?: 5672));
define('RABBITMQ_USER', getenv('RABBITMQ_USER') ?: 'admin');
define('RABBITMQ_PASS', getenv('RABBITMQ_PASS') ?: 'admin');
define('RABBITMQ_VHOST', getenv('RABBITMQ_VHOST') ?: '/');
define('RABBITMQ_EXCHANGE_PEDIDOS', getenv('RABBITMQ_EXCHANGE_PEDIDOS') ?: 'orders.events');
define('RABBITMQ_RK_PEDIDO_CREADO', getenv('RABBITMQ_RK_PEDIDO_CREADO') ?: 'order.created');
