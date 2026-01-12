# 🎨 Ecommerce Artesanos

> **Sistema de comercio electrónico para artesanos** con arquitectura Event-Driven, Patrón Mediator y actualización en tiempo real.

[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Angular](https://img.shields.io/badge/Angular-20-DD0031?logo=angular&logoColor=white)](https://angular.io/)
[![Node.js](https://img.shields.io/badge/Node.js-18+-339933?logo=node.js&logoColor=white)](https://nodejs.org/)
[![RabbitMQ](https://img.shields.io/badge/RabbitMQ-3.12+-FF6600?logo=rabbitmq&logoColor=white)](https://www.rabbitmq.com/)

---

## 📋 Índice

1. [Visión General](#-visión-general)
2. [Arquitectura del Sistema](#-arquitectura-del-sistema)
3. [Patrones de Diseño](#-patrones-de-diseño)
4. [Estructura del Proyecto](#-estructura-del-proyecto)
5. [Requisitos Previos](#-requisitos-previos)
6. [Instalación](#-instalación)
7. [Configuración](#-configuración)
8. [API Reference](#-api-reference)
9. [Flujo de Datos](#-flujo-de-datos)
10. [Guía de Contribución](#-guía-de-contribución)
11. [Troubleshooting](#-troubleshooting)

---

## 🎯 Visión General

**Ecommerce Artesanos** es una plataforma de comercio electrónico diseñada para conectar artesanos locales con compradores. El sistema implementa:

- **Arquitectura Event-Driven**: Procesamiento asíncrono de pedidos via RabbitMQ
- **Actualización en Tiempo Real**: WebSockets para notificaciones instantáneas
- **Patrón Mediator**: Desacoplamiento de la lógica de negocio compleja
- **Clean Code**: Principios SOLID y código auto-documentado

### Características Principales

| Característica | Descripción |
|---------------|-------------|
| 🛒 **Gestión de Carritos** | Agregar, eliminar, actualizar items |
| 📦 **Procesamiento de Órdenes** | Creación atómica con validación de stock |
| 📊 **Dashboard en Tiempo Real** | Actualización instantánea vía WebSocket |
| 🔄 **Sistema de Eventos** | Publicación/Suscripción con RabbitMQ |
| 📋 **Gestión de Inventario** | Control de stock y movimientos |

---

## 🏗 Arquitectura del Sistema

### Diagrama de Alto Nivel

```
┌─────────────────┐     HTTP REST      ┌─────────────────┐
│                 │ ─────────────────► │                 │
│   Angular 20    │                    │   API PHP 8.0   │
│   (Frontend)    │ ◄───────────────── │   (Backend)     │
│                 │     JSON Response  │                 │
└────────┬────────┘                    └────────┬────────┘
         │                                      │
         │ WebSocket                            │ AMQP Publish
         │ (ws://localhost:3005)                │
         │                                      ▼
         │                             ┌─────────────────┐
         │                             │                 │
         │                             │    RabbitMQ     │
         │                             │    (Broker)     │
         │                             │                 │
         │                             └────────┬────────┘
         │                                      │
         │                                      │ AMQP Consume
         │                                      ▼
         │                             ┌─────────────────┐
         │                             │                 │
         └────────────────────────────►│  Worker Node.js │
                       WebSocket Push  │  (Consumer)     │
                                       │                 │
                                       └─────────────────┘
```

### Componentes

| Componente | Tecnología | Puerto | Descripción |
|------------|------------|--------|-------------|
| **Frontend** | Angular 20 | 4200 | SPA, consume API REST y WebSocket |
| **Backend** | PHP 8.0+ | 80 | API REST modular con Mediator |
| **Message Broker** | RabbitMQ | 5672/15672 | Exchange tipo topic para eventos |
| **Worker** | Node.js 18+ | 3005 | Consume eventos, emite WebSocket |
| **Database** | MySQL 8.0 | 3306 | Persistencia de datos |

---

## 🎨 Patrones de Diseño

### Patrón Mediator (OrderMediator)

El **Patrón Mediator** centraliza la coordinación entre múltiples servicios, evitando el acoplamiento directo.

```
                    ┌─────────────────────────────────┐
                    │       OrderMediator             │
                    │  (Orquestador Central)          │
                    └─────────────────────────────────┘
                           │         │         │
           ┌───────────────┼─────────┼─────────┼───────────────┐
           │               │         │         │               │
           ▼               ▼         ▼         ▼               ▼
    ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
    │  Inventory  │ │    Cart     │ │   Order     │ │Notification │
    │  Service    │ │   Service   │ │   Service   │ │  Service    │
    └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘
         │                │               │               │
         └────────────────┴───────────────┴───────────────┘
                               │
                        ┌──────┴──────┐
                        │   MySQL     │    RabbitMQ
                        └─────────────┘
```

#### Beneficios Implementados

| Principio | Implementación |
|-----------|----------------|
| **Single Responsibility** | Cada servicio tiene una única responsabilidad |
| **Open/Closed** | Nuevos servicios se agregan sin modificar existentes |
| **Dependency Inversion** | Servicios inyectados en constructor |
| **Transaccionalidad** | Commit/Rollback centralizado en Mediator |

#### Archivos Clave

- `api/patterns/Mediator.php` - Interfaz del patrón
- `api/patterns/OrderMediator.php` - Implementación concreta
- `api/services/*.php` - Servicios (Colleagues)
- `api/resources/orders.php` - Controller "delgado"

---

## 📁 Estructura del Proyecto

```
EcommerceArtesanos/
│
├── AppArtesanos/                 # Aplicación principal
│   │
│   ├── api/                      # 🔷 BACKEND PHP
│   │   ├── api.php               # Router principal (Front Controller)
│   │   │
│   │   ├── includes/             # Núcleo de la aplicación
│   │   │   ├── config.php        # Constantes de configuración
│   │   │   ├── db.php            # Conexión PDO a MySQL
│   │   │   ├── helpers.php       # Funciones utilitarias (q, out, fail)
│   │   │   └── events.php        # Publicación a RabbitMQ
│   │   │
│   │   ├── patterns/             # Patrones de diseño
│   │   │   ├── Mediator.php      # Interface del patrón
│   │   │   └── OrderMediator.php # Implementación (199 líneas comentadas)
│   │   │
│   │   ├── services/             # Lógica de negocio encapsulada
│   │   │   ├── InventoryService.php  # Stock y movimientos
│   │   │   ├── CartService.php       # Validación de carritos
│   │   │   ├── OrderService.php      # Persistencia de órdenes
│   │   │   └── NotificationService.php # Eventos RabbitMQ
│   │   │
│   │   ├── resources/            # Controladores REST
│   │   │   ├── orders.php        # CRUD órdenes (usa Mediator)
│   │   │   ├── products.php      # CRUD productos
│   │   │   ├── carts.php         # CRUD carritos
│   │   │   ├── customers.php     # CRUD clientes
│   │   │   ├── categories.php    # CRUD categorías
│   │   │   ├── artisans.php      # CRUD artesanos
│   │   │   └── reports.php       # Reportes y estadísticas
│   │   │
│   │   ├── tests/                # Pruebas manuales
│   │   │   └── test_payload.json # Payload de prueba
│   │   │
│   │   └── vendor/               # Dependencias Composer
│   │
│   ├── database/                 # Scripts SQL
│   │   └── ecommerce_artesanos.sql
│   │
│   └── src/                      # 🔶 FRONTEND ANGULAR
│       ├── app/
│       │   ├── servicios/        # Servicios Angular
│       │   │   └── websocket.service.ts
│       │   └── ...
│       └── ...
│
├── workers/                      # 🟢 MICROSERVICIOS NODE.JS
│   ├── lib/                      # Librerías compartidas
│   │   ├── rabbitmq.js           # Cliente AMQP reutilizable
│   │   └── websocket.js          # Servidor WebSocket
│   ├── worker-pedidos-creados.js # Consumer de eventos
│   └── .env                      # Configuración local
│
├── docs/                         # 📚 DOCUMENTACIÓN
│   ├── Analisis_Patron_Mediator.txt
│   ├── Arquitectura_Event_Driven.txt
│   └── Diagramas_UML.txt
│
└── README.md                     # Esta guía
```

---

## ✅ Requisitos Previos

### Software Requerido

| Software | Versión Mínima | Verificación |
|----------|---------------|--------------|
| Node.js | 18.0+ | `node --version` |
| PHP | 8.0+ | `php --version` |
| Composer | 2.0+ | `composer --version` |
| MySQL | 8.0+ | `mysql --version` |
| RabbitMQ | 3.12+ | Acceder a `http://localhost:15672` |

### Extensiones PHP Requeridas

```bash
php -m | findstr /i "pdo_mysql sockets"
```

- `pdo_mysql` - Conexión a MySQL
- `sockets` - Comunicación con RabbitMQ

---

## 🚀 Instalación

### Paso 1: Clonar Repositorio

```bash
git clone https://github.com/tu-usuario/EcommerceArtesanos.git
cd EcommerceArtesanos
```

### Paso 2: Base de Datos

```sql
-- Crear base de datos
CREATE DATABASE ecommerce_artesanos 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Importar esquema
SOURCE AppArtesanos/database/ecommerce_artesanos.sql;
```

### Paso 3: API PHP

```powershell
cd AppArtesanos/api
composer install

# Para XAMPP: copiar a htdocs
Copy-Item -Path . -Destination "C:\xampp\htdocs\api" -Recurse
```

### Paso 4: Workers Node.js

```powershell
cd workers
npm install

# Crear archivo .env
@"
URL_BROKER_RABBITMQ=amqp://admin:admin@localhost:5672
COLA_PEDIDOS_CREADOS=orders.created
PUERTO_WEBSOCKET_PEDIDOS=3005
"@ | Out-File -FilePath .env -Encoding utf8
```

### Paso 5: Frontend Angular

```powershell
cd AppArtesanos
npm install
```

---

## ⚙ Configuración

### Variables de Entorno API (config.php)

| Variable | Default | Descripción |
|----------|---------|-------------|
| `DB_HOST` | localhost | Host de MySQL |
| `DB_NAME` | ecommerce_artesanos | Nombre de la BD |
| `DB_USER` | root | Usuario MySQL |
| `DB_PASS` | (vacío) | Contraseña MySQL |
| `RABBITMQ_HOST` | localhost | Host de RabbitMQ |
| `RABBITMQ_PORT` | 5672 | Puerto AMQP |
| `RABBITMQ_USER` | admin | Usuario RabbitMQ |
| `RABBITMQ_PASS` | admin | Contraseña RabbitMQ |

### Variables de Entorno Workers (.env)

```env
URL_BROKER_RABBITMQ=amqp://admin:admin@localhost:5672
COLA_PEDIDOS_CREADOS=orders.created
PUERTO_WEBSOCKET_PEDIDOS=3005
```

---

## 📡 API Reference

### Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `?resource=ping` | Health check |
| `GET` | `?resource=products` | Listar productos |
| `GET` | `?resource=products&id=5` | Obtener producto |
| `POST` | `?resource=orders` | Crear orden |
| `DELETE` | `?resource=orders&id=7` | Cancelar orden |

### Ejemplo: Crear Orden

```http
POST http://localhost/api/api.php?resource=orders
Content-Type: application/json

{
  "cart_id": 6,
  "status": "pending",
  "notes": "Entregar por la tarde"
}
```

**Respuesta (201 Created):**
```json
{
  "ok": true,
  "data": {
    "order_id": 7,
    "order_number": "ORD-20260111215000-742",
    "total": 150.00,
    "items": [...]
  }
}
```

---

## 🔄 Flujo de Datos

### Creación de Orden (Flujo Completo)

```
1. Usuario → [Angular] → POST /orders {cart_id: 6}
                              │
2.                    [api.php Router]
                              │
3.               [orders.php Controller]
                              │
4.                   [OrderMediator]
                        │   │   │
         ┌──────────────┼───┼───┼──────────────┐
         │              │   │   │              │
5.  CartService   InventoryService  OrderService  NotificationService
    (valida)       (reserva stock)   (INSERT)      (RabbitMQ)
         │              │   │   │              │
         └──────────────┴───┴───┴──────────────┘
                        │
6.                  COMMIT MySQL
                        │
7.              RabbitMQ ← evento "order.created"
                        │
8.                    Worker
                        │
9.              WebSocket → [Angular Dashboard]
                        │
10.          ¡Actualización en tiempo real!
```

---

## 👥 Guía de Contribución

### Estándares de Código

1. **PHP**: PSR-12, tipos estrictos (`declare(strict_types=1)`)
2. **Comentarios**: Español, explicativos, no redundantes
3. **Commits**: Mensajes descriptivos en español

### Cómo Agregar un Nuevo Servicio

1. Crear `api/services/NuevoService.php`
2. Inyectar en `OrderMediator` o crear nuevo Mediator
3. Documentar en este README

### Cómo Agregar un Nuevo Endpoint

1. Crear `api/resources/nuevo.php`
2. Agregar case en `api/api.php` switch
3. Documentar en API Reference

---

## 🔧 Troubleshooting

### Error: "Stock insuficiente"

**Causa**: El producto no tiene suficiente stock.
**Solución**: Verificar stock en BD o ajustar cantidad.

### Error: "Carrito no encontrado"

**Causa**: El cart_id no existe o ya fue convertido.
**Solución**: Verificar que el carrito esté en estado 'open'.

### Worker no recibe eventos

**Causa**: RabbitMQ no está corriendo o credenciales incorrectas.
**Solución**:
```powershell
# Verificar RabbitMQ
curl http://localhost:15672

# Reiniciar Worker
npm run iniciar:pedidos
```

### API responde 500

**Causa**: Error de PHP no manejado.
**Solución**: Revisar `error_log` de Apache/PHP.

---

## 📞 Contacto

- **Equipo de Desarrollo**: [equipo@artesanos.com]
- **Documentación Adicional**: Carpeta `/docs`

---

<p align="center">
  <strong>Ecommerce Artesanos</strong> | Desarrollado con ❤️ para artesanos locales
</p>
