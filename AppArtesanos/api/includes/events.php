<?php
/**
 * ============================================================================
 * ARCHIVO: events.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Contiene funciones para publicar eventos a RabbitMQ.
 * Es la conexión entre la API PHP y el sistema de mensajería asíncrona.
 * 
 * ARQUITECTURA EVENT-DRIVEN:
 * 1. La API crea una orden en MySQL
 * 2. Esta función publica un evento "pedido-creado" a RabbitMQ
 * 3. Un Worker Node.js consume el evento
 * 4. El Worker notifica a los clientes via WebSocket
 * 
 * DEPENDENCIAS:
 * - php-amqplib (instalado via Composer)
 * - RabbitMQ corriendo en el servidor
 * 
 * NOTA SOBRE RESILIENCIA:
 * Si RabbitMQ no está disponible, la función solo loguea el error.
 * La orden ya está guardada en MySQL, así que no se pierde información.
 */
declare(strict_types=1);

// Importamos la configuración de RabbitMQ
require_once __DIR__ . '/config.php';

/**
 * ============================================================================
 * FUNCIÓN: publicar_evento_pedido_creado()
 * ============================================================================
 * 
 * Publica un evento a RabbitMQ cuando se crea una nueva orden.
 * El evento es consumido por el Worker que notifica en tiempo real.
 * 
 * FLUJO:
 * 1. Verifica si RabbitMQ está habilitado
 * 2. Verifica si la biblioteca php-amqplib está instalada
 * 3. Construye el payload con los datos de la orden
 * 4. Se conecta a RabbitMQ
 * 5. Declara el exchange (tipo: topic)
 * 6. Publica el mensaje
 * 7. Cierra la conexión
 * 
 * @param array $order Datos completos de la orden (viene de OrderService::getOrderDetails)
 * @return void
 * 
 * @example
 * publicar_evento_pedido_creado([
 *     'order_id' => 5,
 *     'order_number' => 'ORD-20260111-001',
 *     'total' => 150.00,
 *     'items' => [...]
 * ]);
 */
function publicar_evento_pedido_creado(array $order): void {
    
    // ========================================================================
    // PASO 1: VERIFICAR SI RABBITMQ ESTÁ HABILITADO
    // ========================================================================
    // Esto permite desactivar RabbitMQ en entornos de desarrollo
    // donde no hay broker instalado (ej: testing local).
    if (!defined('RABBITMQ_ENABLED') || !RABBITMQ_ENABLED) {
        return; // Salimos silenciosamente
    }

    // ========================================================================
    // PASO 2: VERIFICAR DEPENDENCIA php-amqplib
    // ========================================================================
    // Si Composer no instaló la biblioteca, no podemos conectar a RabbitMQ.
    if (!class_exists('\\PhpAmqpLib\\Connection\\AMQPStreamConnection')) {
        error_log('[EventosPedidos] Biblioteca php-amqplib no disponible, evento no enviado.');
        return;
    }

    // ========================================================================
    // PASO 3: CONSTRUIR PAYLOAD DEL EVENTO
    // ========================================================================
    // Transformamos los items al formato esperado por el Worker
    $items = [];
    if (!empty($order['items']) && is_array($order['items'])) {
        foreach ($order['items'] as $item) {
            $items[] = [
                'producto_id'     => isset($item['product_id']) ? (int)$item['product_id'] : null,
                'sku'             => $item['sku'] ?? null,
                'nombre'          => $item['name'] ?? null,
                'cantidad'        => isset($item['quantity']) ? (int)$item['quantity'] : null,
                'precio_unitario' => isset($item['unit_price']) ? (float)$item['unit_price'] : null,
                'total_linea'     => isset($item['line_total']) ? (float)$item['line_total'] : null,
            ];
        }
    }

    // Estructura del payload que recibirá el Worker
    // Incluimos campos tanto en español como en inglés para compatibilidad
    $payload = [
        // Metadatos del evento
        'tipo_evento'   => 'pedido-creado',
        'generadoEn'    => (new DateTimeImmutable())->format(DATE_ATOM), // ISO 8601
        
        // Datos de la orden (ambos nombres para compatibilidad)
        'order_id'      => isset($order['order_id']) ? (int)$order['order_id'] : null,
        'order_number'  => $order['order_number'] ?? null,
        'numero_pedido' => $order['order_number'] ?? null,
        
        // Montos
        'total'         => isset($order['total']) ? (float)$order['total'] : null,
        'subtotal'      => isset($order['subtotal']) ? (float)$order['subtotal'] : null,
        'impuesto'      => isset($order['tax']) ? (float)$order['tax'] : null,
        'envio'         => isset($order['shipping_cost']) ? (float)$order['shipping_cost'] : null,
        
        // Estados
        'estado'        => $order['status'] ?? null,
        'estado_pago'   => $order['payment_status'] ?? null,
        
        // Datos del cliente
        'correo_cliente' => $order['email'] ?? null,
        'cliente' => [
            'id'       => isset($order['customer_id']) ? (int)$order['customer_id'] : null,
            'nombre'   => $order['first_name'] ?? null,
            'apellido' => $order['last_name'] ?? null,
        ],
        
        // Productos
        'items' => $items,
    ];

    // ========================================================================
    // PASO 4-7: CONECTAR Y PUBLICAR
    // ========================================================================
    try {
        // Creamos conexión AMQP a RabbitMQ
        $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            RABBITMQ_HOST,   // 'localhost'
            RABBITMQ_PORT,   // 5672
            RABBITMQ_USER,   // 'admin'
            RABBITMQ_PASS,   // 'admin'
            RABBITMQ_VHOST   // '/'
        );
        
        // Creamos un canal de comunicación
        $channel = $connection->channel();
        
        // Declaramos el exchange (si no existe, lo crea)
        // Parámetros: nombre, tipo, passive, durable, auto_delete
        // - 'topic': permite routing por patrones (ej: order.* → orders queue)
        // - durable=true: el exchange sobrevive reinicios del broker
        $channel->exchange_declare(
            RABBITMQ_EXCHANGE_PEDIDOS,  // 'orders.events'
            'topic',                     // tipo
            false,                       // passive
            true,                        // durable
            false                        // auto_delete
        );

        // Serializamos el payload a JSON
        $mensaje = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        // Creamos el mensaje AMQP
        $message = new \PhpAmqpLib\Message\AMQPMessage($mensaje, [
            'content_type'  => 'application/json',
            'delivery_mode' => 2,  // 2 = mensaje persistente (sobrevive reinicios)
        ]);

        // Publicamos al exchange con la routing key
        // El Worker suscrito a 'order.created' recibirá este mensaje
        $channel->basic_publish(
            $message,
            RABBITMQ_EXCHANGE_PEDIDOS,  // 'orders.events'
            RABBITMQ_RK_PEDIDO_CREADO   // 'order.created'
        );
        
        // Cerramos canal y conexión para liberar recursos
        $channel->close();
        $connection->close();
        
    } catch (Throwable $e) {
        // ====================================================================
        // MANEJO DE ERRORES (NON-BLOCKING)
        // ====================================================================
        // Si RabbitMQ falla, NO interrumpimos el flujo.
        // La orden ya está guardada en MySQL, solo logueamos el error.
        // El admin puede revisar los logs y reenviar eventos manualmente.
        error_log('[EventosPedidos] Error publicando evento: ' . $e->getMessage());
    }
}
