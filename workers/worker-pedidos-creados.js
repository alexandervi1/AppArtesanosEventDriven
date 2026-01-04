import { RabbitMQClient } from './lib/rabbitmq.js';
import { WebSocketManager } from './lib/websocket.js';
import { config } from './config.js';

const wsManager = new WebSocketManager(config.websocket.port);
const mqClient = new RabbitMQClient(config.rabbitmq);

async function start() {
  console.log('--- Starting Worker Pedidos Creados ---');

  // 1. Start WebSocket Server
  wsManager.start();

  // 2. Connect to RabbitMQ
  const connected = await mqClient.connect();
  if (!connected) {
    console.error('Critical: Could not connect to RabbitMQ broker.');
    // The library will attempt to reconnect in the background
  }

  // 3. Setup Queue and Consume
  try {
    const queueName = await mqClient.setupExchangeAndQueue(
      config.rabbitmq.exchange,
      config.rabbitmq.type,
      config.rabbitmq.queue,
      config.rabbitmq.routingKey
    );

    console.log(`[Worker] Listening for events on queue: ${queueName}`);

    await mqClient.consume(queueName, (evento) => {
      console.info(`[Worker] Received Order -> ${evento.numero_pedido || evento.order_id || 'N/A'}`);

      // Broadcast to all WebSocket clients
      wsManager.broadcast({
        tipo: 'pedido-creado',
        datos: evento,
        recibidoEn: new Date().toISOString()
      });
    });

  } catch (error) {
    console.error('[Worker] Fatal error during setup:', error.message);
    process.exit(1);
  }
}

// Graceful Shutdown
const shutdown = async (signal) => {
  console.log(`\n[Worker] Received ${signal}. Shutting down...`);
  await mqClient.close();
  await wsManager.close();
  console.log('[Worker] Graceful shutdown complete.');
  process.exit(0);
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));

start().catch(err => {
  console.error('[Worker] Startup error:', err);
  process.exit(1);
});
