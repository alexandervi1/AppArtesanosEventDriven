# Ecommerce Artesanos

Guia detallada para levantar y extender la aplicacion Ecommerce Artesanos. El proyecto demuestra una arquitectura **Event-Driven** robusta y modular, apoyada en RabbitMQ y Workers especializados.

## Arquitectura en una mirada

```
Frontend Angular (AppArtesanos)
    |
    |  HTTP REST (http://localhost/api/api.php)
    v
API PHP Modular (AppArtesanos/api)
    |
    |  Evento "pedido-creado" --> exchange topic "orders.events"
    v
RabbitMQ (broker de eventos)
    |
    |  Queue "orders.created" (routing key "order.created")
    v
Worker Node (workers/worker-pedidos-creados.js)
    |
    |  WebSocket ws://localhost:3005
    v
Dashboard Angular (Resumen de ventas en tiempo real)
```

**Componentes principales:**
- **AppArtesanos/src**: Aplicacion Angular 20. Consume la API y escucha eventos vía WebSocket.
- **AppArtesanos/api**: Backend PHP modular (Router + Resources). Soporta PHP 8.0+ y 8.1+. Configuración centralizada.
- **workers/**: Microservicios en Node.js 18+. Arquitectura modular (`lib/rabbitmq`, `lib/websocket`) con reconexión automática y gestión por `.env`.
- **RabbitMQ**: Broker de mensajería para desacoplar el procesamiento de pedidos.

## Requisitos previos

- **Node.js 18+** y npm.
- **PHP 8.0+** (Probado en 8.0.30 y 8.1+). Extensiones PDO MySQL requeridas.
- **Composer 2.x**.
- **MySQL** (o MariaDB).
- **RabbitMQ 3.12+**.
- **XAMPP / Apache** (Recomendado para Windows) o servidor PHP embebido.

## Preparación inicial

### 1. Base de datos

1. Cree la base de datos:
   ```sql
   CREATE DATABASE ecommerce_artesanos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Importe `AppArtesanos/database/ecommerce_artesanos.sql`.

### 2. API PHP

La API ha sido refactorizada para ser modular.

1. Instalar dependencias:
   ```powershell
   cd AppArtesanos/api
   composer install
   ```
   *Nota: Si usa PHP 8.0, el proyecto ya incluye un parche para compatibilidad.*

2. Despliegue en XAMPP (Recomendado):
   - Copie el contenido de `AppArtesanos/api` a `C:\xampp\htdocs\api`.
   - La API estará disponible en `http://localhost/api/api.php`.

3. Configuración:
   - La API usa constantes definidas en `api/includes/config.php`.
   - Puede usar variables de entorno del sistema o editar `config.php` si necesita credenciales personalizadas (por defecto: `admin`/`admin` para RabbitMQ).

### 3. Workers Node

Los workers manejan la lógica de fondo y WebSockets.

1. Instalar dependencias:
   ```powershell
   cd workers
   npm install
   ```

2. Configuración (`.env`):
   - Cree un archivo `.env` en la carpeta `workers` (basado en el ejemplo o instrucciones).
   - Variables clave:
     ```env
     URL_BROKER_RABBITMQ=amqp://admin:admin@localhost:5672
     PUERTO_WEBSOCKET_PEDIDOS=3005
     ```

3. Iniciar Worker:
   ```powershell
   npm run iniciar:pedidos
   ```
   *El worker escuchará RabbitMQ y abrirá el WebSocket en el puerto 3005.*

### 4. Frontend Angular

1. Instalar dependencias:
   ```powershell
   cd AppArtesanos
   npm install
   ```

2. Ejecutar:
   ```powershell
   npm start
   ```
   - Acceda a `http://localhost:4200`.
   - La app está configurada para conectarse a la API en `http://localhost/api/api.php` y al WebSocket en puerto `3005`.

## Verificación del flujo

1. **Ping a la API**:
   Visite `http://localhost/api/api.php?resource=ping` -> Debería responder `{"ok":true...}`.

2. **Prueba End-to-End**:
   - Asegúrese que RabbitMQ, el Worker y la API estén corriendo.
   - Cree una orden desde la aplicación web o usando Postman:
     ```http
     POST http://localhost/api/api.php?resource=orders
     Content-Type: application/json
     { "cart_id": 1, "status": "pending" }
     ```
   - **Resultado**:
     1. La API responde con la orden creada.
     2. El Worker muestra en consola: `[Worker] Recibido evento de pedido: ORD-XXXX`.
     3. El Dashboard de Angular se actualiza automáticamente.

## Estructura de carpetas (Actualizada)

```
EcommerceArtesanos/
├── AppArtesanos/
│   ├── api/                  # Backend Modular
│   │   ├── includes/         # Config, DB, Helpers, Events
│   │   ├── resources/        # Lógica de negocio (Orders, Products...)
│   │   ├── vendor/           # Dependencias Composer
│   │   └── api.php           # Router principal
│   ├── database/             # Scripts SQL
│   └── src/                  # Frontend Angular
├── workers/                  # Microservicios Node.js
│   ├── lib/                  # Librerías reutilizables (RabbitMQ, WS)
│   ├── .env                  # Configuración local
│   └── worker-*.js           # Entry points
└── README.md                 # Esta guía
```
