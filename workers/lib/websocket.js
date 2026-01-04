import { WebSocketServer } from 'ws';

export class WebSocketManager {
    constructor(port) {
        this.port = port;
        this.wss = null;
        this.clients = new Set();
    }

    start() {
        this.wss = new WebSocketServer({ port: this.port });

        this.wss.on('connection', (ws) => {
            console.log('[WebSocket] Client connected');
            this.clients.add(ws);

            ws.on('close', () => {
                console.log('[WebSocket] Client disconnected');
                this.clients.delete(ws);
            });

            ws.on('error', (err) => {
                console.error('[WebSocket] Client error:', err.message);
                this.clients.delete(ws);
            });
        });

        this.wss.on('listening', () => {
            console.log(`[WebSocket] Server listening on port ${this.port}`);
        });

        return this.wss;
    }

    broadcast(data) {
        const message = JSON.stringify(data);
        this.clients.forEach((client) => {
            if (client.readyState === 1) { // OPEN
                client.send(message);
            }
        });
    }

    close() {
        if (this.wss) {
            return new Promise((resolve) => this.wss.close(resolve));
        }
        return Promise.resolve();
    }
}
