# Ecommerce Artesanos

Guia detallada para levantar y extender la aplicacion Ecommerce Artesanos. El proyecto se centra en demostrar un patron Event-Driven apoyado en RabbitMQ y un conjunto de workers que reparten los eventos a los clientes web en tiempo real.

## Arquitectura en una mirada

```
Frontend Angular (AppArtesanos)
    |
    |  HTTP REST (productos, pedidos, etc.)
    v
API PHP (AppArtesanos/api/api.php)
    |
    |  Evento "pedido-creado" --> exchange topic "orders.events"
    v
RabbitMQ (broker de eventos)
    |
    |  Queue "orders.created" (routing key "order.created")
    v
Worker Node "worker-pedidos-creados.js"
    |
    |  WebSocket ws://localhost:3001
    v
Dashboard Angular (Resumen de ventas, alertas)
```

Componentes principales:
- `AppArtesanos/src`: aplicacion Angular 20 que consume la API y escucha eventos de pedidos (`eventos-pedidos.service.ts`).
- `AppArtesanos/api/api.php`: backend PHP 8.1 con MySQL, publica eventos de pedidos usando `php-amqplib`.
- `workers/worker-pedidos-creados.js`: worker Node.js que consume RabbitMQ y retransmite los eventos via WebSocket.
- `workers/publicador-pedido-prueba.js`: script de utilidad para publicar eventos de prueba.
- `AppArtesanos/database/ecommerce_artesanos.sql`: esquema y datos semilla para MySQL.

## Por que Event-Driven en este proyecto

1. **Desacoplamiento entre dominio y reacciones**: el API PHP publica el evento de pedido en `AppArtesanos/api/api.php` (ver funcion `publicar_evento_pedido_creado`, linea ~120). No conoce quien reaccionara a ese evento.
2. **Orquestacion con RabbitMQ**: se usa un exchange `topic` llamado `orders.events` y rutas semanticas (`order.created`) para encaminar eventos a las colas adecuadas.
3. **Workers especializados**: el worker `workers/worker-pedidos-creados.js` consume la cola `orders.created`, admite reconexiones y traduce el evento a WebSocket para multiples clientes.
4. **UI reactiva**: el servicio Angular `AppArtesanos/src/app/servicios/eventos-pedidos.service.ts` abre un WebSocket y expone un observable `pedidoCreado$`. El dashboard de ventas (`resumen-ventas.component.ts`) se subscribe y refresca estadisticas sin recargar.
5. **Escalabilidad natural**: se pueden anadir mas workers (notificaciones, facturacion, etc.) con nuevas colas/routing keys sin modificar el servicio que emite el pedido.

## Requisitos previos

- Node.js 18+ y npm.
- Angular CLI (`npm install -g @angular/cli`) si desea usar comandos `ng`.
- PHP 8.1+ con extensiones PDO y PDO MySQL.
- Composer 2.x.
- MySQL (o MariaDB) para la base `ecommerce_artesanos`.
- RabbitMQ 3.12+ con un usuario capaz de declarar exchanges/queues.
- Opcional: Docker para RabbitMQ y MySQL si prefiere contenedores.

## Preparacion inicial

### 1. Base de datos

1. Cree la base:
   ```sql
   CREATE DATABASE ecommerce_artesanos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
2. Importe `AppArtesanos/database/ecommerce_artesanos.sql` en esa base (MySQL Workbench, `mysql` CLI o herramienta de su preferencia).

### 2. API PHP

```powershell
cd AppArtesanos/api
composer install
```

La API asume por defecto:
- Host MySQL `localhost`, usuario `root`, sin contrasena. Ajuste en el propio script (`$DB_HOST`, `$DB_USER`, etc.).
- RabbitMQ en `localhost` con credenciales `admin/secret123` (vea siguientes secciones para cambiarlos con variables de entorno).

Para exponer la API durante el desarrollo puede usar el servidor embebido de PHP:

```powershell
cd AppArtesanos/api
php -S 0.0.0.0:70 api.php
```

El frontend esta configurado para consumir `http://localhost:70/api/api.php`. Si usa otro puerto u host, puede proveer un valor alterno via provider `API_BASE_URL` en Angular (por ejemplo en `app.config.ts`).

### 3. Frontend Angular

```powershell
cd AppArtesanos
npm install
```

Scripts utiles:
- `npm start` o `ng serve` -> levanta el frontend en `http://localhost:4200`.
- `npm run build` -> construye artefactos de produccion en `dist/`.

### 4. Workers Node

```powershell
cd workers
npm install
```

Scripts:
- `npm run iniciar:pedidos` -> inicia `worker-pedidos-creados.js`.
- `npm run enviar:pedido` -> ejecuta `publicador-pedido-prueba.js` para simular un pedido.

## Variables de entorno y configuracion

### API PHP (`AppArtesanos/api/api.php`)

Puede declarar las variables antes de lanzar `php -S` o configurarlas en Apache/Nginx:

- `RABBITMQ_ENABLED` (default `1`): use `0` para desactivar la publicacion de eventos.
- `RABBITMQ_HOST`, `RABBITMQ_PORT`: ubicacion del broker (default `localhost:5672`).
- `RABBITMQ_USER`, `RABBITMQ_PASS`, `RABBITMQ_VHOST`: credenciales (default `admin` / `secret123` / `/`).
- `RABBITMQ_EXCHANGE_PEDIDOS`: nombre del exchange topic (default `orders.events`).
- `RABBITMQ_RK_PEDIDO_CREADO`: routing key (default `order.created`).

Ejemplo en PowerShell:

```powershell
$env:RABBITMQ_USER = "guest"
$env:RABBITMQ_PASS = "guest"
php -S 0.0.0.0:70 api.php
```

### Worker Node (`workers/worker-pedidos-creados.js`)

- `URL_BROKER_RABBITMQ`: URI completa AMQP (default `amqp://guest:guest@localhost:5672`).
- `EXCHANGE_PEDIDOS`: exchange a consumir (default `orders.events`).
- `COLA_PEDIDOS_CREADOS`: nombre de la cola (default `orders.created`).
- `CLAVE_PEDIDO_CREADO`: routing key (default `order.created`).
- `PUERTO_WEBSOCKET_PEDIDOS`: puerto para WebSocket (default `3001`).

Ejemplo:

```powershell
$env:URL_BROKER_RABBITMQ = "amqp://admin:secret123@localhost:5672"
$env:PUERTO_WEBSOCKET_PEDIDOS = "3100"
npm run iniciar:pedidos
```

### Frontend Angular

Si necesita cambiar la URL base del API sin modificar codigo, puede proveer un valor en el bootstrap del aplicativo (`AppArtesanos/src/app/app.config.ts`):

```typescript
provideApiBaseUrl({ apiBaseUrl: 'http://mi-host:8080/api/api.php' });
```

El servicio de eventos usa `ws://localhost:3001` por defecto (`eventos-pedidos.service.ts`). Para cambiarlo en tiempo de ejecucion:

```typescript
eventosPedidosService.configurarUrlWebSocket('ws://mi-host:3100');
eventosPedidosService.conectar();
```

## Ejecucion en desarrollo (paso a paso)

1. **RabbitMQ**: asegure que el broker esta activo y que el usuario tiene permisos para declarar `orders.events`.
2. **API PHP**:
   ```powershell
   cd AppArtesanos/api
   php -S 0.0.0.0:70 api.php
   ```
3. **Worker de pedidos**:
   ```powershell
   cd workers
   npm run iniciar:pedidos
   ```
   El worker abrira un WebSocket (por defecto en `ws://localhost:3001`) y mostrara logs cuando reciba eventos.
4. **Frontend**:
   ```powershell
   cd AppArtesanos
   npm start
   ```
   Abra `http://localhost:4200`. El panel de resumen (`/admin/resumen`) mostrara datos iniciales desde la API y quedara suscrito al WebSocket.

## Pruebas del flujo Event-Driven

### Opcion A: crear pedido real via API

Use `POST` contra `http://localhost:70/api/api.php?resource=orders` con un payload similar (ajuste IDs segun sus datos):

```bash
curl -X POST "http://localhost:70/api/api.php?resource=orders" ^
  -H "Content-Type: application/json" ^
  -d "{\"customer_id\":1,\"cart_id\":1,\"items\":[{\"product_id\":1,\"quantity\":1}]}"
```

La API:
1. Registra el pedido en MySQL.
2. Publica el evento `pedido-creado` en RabbitMQ (ver `publicar_evento_pedido_creado`).

El worker:
1. Consume la cola `orders.created`.
2. Reenvia el evento via WebSocket.

El frontend:
1. `EventosPedidosService` interpreta el mensaje.
2. `ResumenVentasComponent` llama otra vez al endpoint de pedidos para refrescar datos.

### Opcion B: publicar un evento simulado

```powershell
cd workers
npm run enviar:pedido
```

Esto genera un mensaje con campos aleatorios, util para probar la cadena sin depender de la API.

## Estrategia de escalabilidad Event-Driven

- **Multiples consumidores**: mas instancias del worker pueden escuchar la misma cola para balancear carga.
- **Nuevas funcionalidades**: puede declarar nuevas colas (p.ej. `orders.fulfilled`) y workers especializados (notificaciones, facturacion) sin tocar el backend Angular o PHP.
- **Resiliencia**: si RabbitMQ se cae, `worker-pedidos-creados.js` intenta reconectar y el frontend vuelve a conectarse (ver `programarReconexion` en `eventos-pedidos.service.ts`).
- **Back-pressure**: RabbitMQ asegura almacenamiento de eventos cuando los consumers estan offline, evitando perdida de informacion.

## Estructura de carpetas

```
EcommerceArtesanos/
├── AppArtesanos/
│   ├── api/                  # API PHP + Composer
│   ├── database/             # Script SQL
│   ├── src/                  # Aplicacion Angular
│   └── ...                   # Config Angular/Tailwind
├── workers/                  # Workers Node.js para eventos
└── .gitattributes
```

## Buenas practicas y siguientes pasos

- Añada monitoreo (Prometheus/Grafana) tomando los logs del worker y mensajes RabbitMQ.
- Versione los contratos de evento (JSON Schema) para alinear backend y consumers.
- Considere mover la configuracion sensible a archivos `.env` (PHP y Node).
- Para despliegue, declare el exchange y la cola mediante `rabbitmqadmin` o IaC antes de iniciar los workers.

Con estos pasos puede instalar, ejecutar y extender la aplicacion manteniendo el enfoque Event-Driven centrado en RabbitMQ y los workers especializados.
