import amqp from 'amqplib';

export class RabbitMQClient {
    constructor(config) {
        this.config = config;
        this.connection = null;
        this.channel = null;
        this.reconnectTimeout = 5000;
    }

    async connect() {
        try {
            this.connection = await amqp.connect(this.config.url);
            this.channel = await this.connection.createChannel();

            this.connection.on('error', (err) => {
                console.error('[RabbitMQ] Connection error:', err.message);
                this.retryConnect();
            });

            this.connection.on('close', () => {
                console.warn('[RabbitMQ] Connection closed. Retrying...');
                this.retryConnect();
            });

            console.log('[RabbitMQ] Connected successfully');
            return true;
        } catch (error) {
            console.error('[RabbitMQ] Failed to connect:', error.message);
            this.retryConnect();
            return false;
        }
    }

    retryConnect() {
        setTimeout(() => this.connect(), this.reconnectTimeout);
    }

    async setupExchangeAndQueue(exchange, type, queue, routingKey) {
        if (!this.channel) throw new Error('Channel not initialized');

        await this.channel.assertExchange(exchange, type, { durable: true });
        const q = await this.channel.assertQueue(queue, { durable: true });
        await this.channel.bindQueue(q.queue, exchange, routingKey);

        return q.queue;
    }

    async consume(queue, onMessage) {
        if (!this.channel) throw new Error('Channel not initialized');

        await this.channel.consume(queue, (msg) => {
            if (msg !== null) {
                try {
                    const content = JSON.parse(msg.content.toString());
                    onMessage(content);
                    this.channel.ack(msg);
                } catch (error) {
                    console.error('[RabbitMQ] Error processing message:', error.message);
                    this.channel.nack(msg, false, true); // Requeue by default
                }
            }
        });
    }

    async publish(exchange, routingKey, message) {
        if (!this.channel) throw new Error('Channel not initialized');

        const buffer = Buffer.from(JSON.stringify(message));
        return this.channel.publish(exchange, routingKey, buffer, {
            persistent: true,
            contentType: 'application/json'
        });
    }

    async close() {
        if (this.channel) await this.channel.close();
        if (this.connection) await this.connection.close();
    }
}
