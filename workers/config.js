import 'dotenv/config';

export const config = {
    rabbitmq: {
        url: process.env.URL_BROKER_RABBITMQ || 'amqp://admin:admin@localhost:5672',
        exchange: process.env.EXCHANGE_PEDIDOS || 'orders.events',
        queue: process.env.COLA_PEDIDOS_CREADOS || 'orders.created',
        routingKey: process.env.CLAVE_PEDIDO_CREADO || 'order.created',
        type: 'topic'
    },
    websocket: {
        port: parseInt(process.env.PUERTO_WEBSOCKET_PEDIDOS || '3001', 10)
    },
    logging: {
        level: process.env.LOG_LEVEL || 'info'
    }
};
