import { RabbitMQClient } from './lib/rabbitmq.js';
import { config } from './config.js';

const mqClient = new RabbitMQClient(config.rabbitmq);

async function publicarPrueba() {
  console.log('[Publicador] Connecting to RabbitMQ...');

  const connected = await mqClient.connect();
  if (!connected) {
    console.error('[Publicador] Could not connect to broker');
    process.exit(1);
  }

  const eventoPedido = {
    tipo_evento: 'pedido-creado',
    numero_pedido: Math.floor(Math.random() * 10000),
    total: parseFloat((Math.random() * 200).toFixed(2)),
    correo_cliente: `usuario_${Math.floor(Math.random() * 100)}@ejemplo.com`,
    generadoEn: new Date().toISOString(),
    items: [
      { id: 1, nombre: 'Artesanía A', precio: 50.0 },
      { id: 2, nombre: 'Artesanía B', precio: 30.0 }
    ]
  };

  try {
    await mqClient.publish(
      config.rabbitmq.exchange,
      config.rabbitmq.routingKey,
      eventoPedido
    );

    console.log(`[Publicador] Order Event Sent: #${eventoPedido.numero_pedido}`);

    // Close connection after a short delay to ensure message is flushed
    setTimeout(async () => {
      await mqClient.close();
      console.log('[Publicador] Connection closed.');
      process.exit(0);
    }, 500);

  } catch (error) {
    console.error('[Publicador] Error publishing:', error.message);
    process.exit(1);
  }
}

publicarPrueba().catch(console.error);
