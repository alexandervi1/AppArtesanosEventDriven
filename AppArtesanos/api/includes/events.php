<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function publicar_evento_pedido_creado(array $order): void {
  if (!defined('RABBITMQ_ENABLED') || !RABBITMQ_ENABLED) {
    return;
  }

  if (!class_exists('\PhpAmqpLib\Connection\AMQPStreamConnection')) {
    error_log('[EventosPedidos] Biblioteca php-amqplib no disponible, evento no enviado.');
    return;
  }

  $items = [];
  if (!empty($order['items']) && is_array($order['items'])) {
    foreach ($order['items'] as $item) {
      $items[] = [
        'producto_id' => isset($item['product_id']) ? (int)$item['product_id'] : null,
        'sku' => $item['sku'] ?? null,
        'nombre' => $item['name'] ?? null,
        'cantidad' => isset($item['quantity']) ? (int)$item['quantity'] : null,
        'precio_unitario' => isset($item['unit_price']) ? (float)$item['unit_price'] : null,
        'total_linea' => isset($item['line_total']) ? (float)$item['line_total'] : null,
      ];
    }
  }

  $payload = [
    'tipo_evento' => 'pedido-creado',
    'order_id' => isset($order['order_id']) ? (int)$order['order_id'] : null,
    'order_number' => $order['order_number'] ?? null,
    'numero_pedido' => $order['order_number'] ?? null,
    'total' => isset($order['total']) ? (float)$order['total'] : null,
    'subtotal' => isset($order['subtotal']) ? (float)$order['subtotal'] : null,
    'impuesto' => isset($order['tax']) ? (float)$order['tax'] : null,
    'envio' => isset($order['shipping_cost']) ? (float)$order['shipping_cost'] : null,
    'estado' => $order['status'] ?? null,
    'estado_pago' => $order['payment_status'] ?? null,
    'correo_cliente' => $order['email'] ?? null,
    'cliente' => [
      'id' => isset($order['customer_id']) ? (int)$order['customer_id'] : null,
      'nombre' => $order['first_name'] ?? null,
      'apellido' => $order['last_name'] ?? null,
    ],
    'items' => $items,
    'generadoEn' => (new DateTimeImmutable())->format(DATE_ATOM),
  ];

  try {
    $connection = new \PhpAmqpLib\Connection\AMQPStreamConnection(
      RABBITMQ_HOST,
      RABBITMQ_PORT,
      RABBITMQ_USER,
      RABBITMQ_PASS,
      RABBITMQ_VHOST
    );
    $channel = $connection->channel();
    $channel->exchange_declare(RABBITMQ_EXCHANGE_PEDIDOS, 'topic', false, true, false);

    $mensaje = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $message = new \PhpAmqpLib\Message\AMQPMessage($mensaje, [
      'content_type' => 'application/json',
      'delivery_mode' => 2,
    ]);

    $channel->basic_publish($message, RABBITMQ_EXCHANGE_PEDIDOS, RABBITMQ_RK_PEDIDO_CREADO);
    $channel->close();
    $connection->close();
  } catch (Throwable $e) {
    error_log('[EventosPedidos] Error publicando evento: ' . $e->getMessage());
  }
}
