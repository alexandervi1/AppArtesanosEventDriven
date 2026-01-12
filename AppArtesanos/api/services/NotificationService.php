<?php
/**
 * ============================================================================
 * ARCHIVO: NotificationService.php
 * ============================================================================
 * 
 * PROPÓSITO:
 * Servicio especializado en publicar notificaciones/eventos al sistema
 * de mensajería (RabbitMQ). Es un "Colleague" en el Patrón Mediator.
 * 
 * RESPONSABILIDADES (Single Responsibility Principle):
 * 1. Encapsular la lógica de publicación de eventos
 * 2. Manejar errores de forma resiliente (non-blocking)
 * 3. Aislar la dependencia de RabbitMQ del resto del código
 * 
 * ARQUITECTURA EVENT-DRIVEN:
 * Este servicio es el punto de conexión entre la API y el sistema de
 * eventos. Cuando se crea una orden, publica un mensaje que los Workers
 * consumen para procesamiento asíncrono y notificaciones en tiempo real.
 * 
 * FILOSOFÍA "NON-BLOCKING":
 * Si RabbitMQ falla, la orden YA está guardada en MySQL.
 * No tiene sentido fallar toda la operación por un evento secundario.
 * Solo logueamos el error y continuamos.
 */
declare(strict_types=1);

namespace Api\Services;

/**
 * Servicio de Notificaciones
 * 
 * Publica eventos a RabbitMQ para procesamiento asíncrono.
 * Diseñado para ser resiliente ante fallos del broker.
 */
class NotificationService {
    
    /**
     * ========================================================================
     * MÉTODO: publishOrderCreated() - Publicar Evento de Orden Creada
     * ========================================================================
     * 
     * Publica un evento a RabbitMQ cuando se crea una nueva orden.
     * El evento es consumido por Workers que pueden:
     * - Enviar emails de confirmación
     * - Actualizar dashboards en tiempo real (WebSocket)
     * - Sincronizar con sistemas externos
     * - Generar reportes
     * 
     * FLUJO:
     * 1. Incluye el archivo events.php (contiene la función de publicación)
     * 2. Verifica que la función exista
     * 3. Intenta publicar el evento
     * 4. Si falla, loguea el error pero NO interrumpe el flujo
     * 
     * ¿POR QUÉ USAMOS require_once AQUÍ?
     * Para mantener compatibilidad con el código existente (events.php)
     * sin tener que reescribir toda la lógica de AMQP.
     * En el futuro, podríamos mover esa lógica directamente aquí.
     * 
     * @param array $order Datos completos de la orden (de OrderService::getOrderDetails)
     * @return void
     * 
     * @example
     * $order = $orderService->getOrderDetails(7);
     * $notificationService->publishOrderCreated($order);
     * // El Worker recibirá el evento en la cola 'orders.created'
     */
    public function publishOrderCreated(array $order): void {
        // Incluimos el archivo que contiene la función de publicación
        // __DIR__ apunta a la carpeta 'services', así que subimos un nivel
        require_once __DIR__ . '/../includes/events.php';
        
        try {
            // Verificamos que la función global exista
            // (podría no existir si events.php falló al cargar php-amqplib)
            if (function_exists('publicar_evento_pedido_creado')) {
                // Llamamos a la función que hace el trabajo real
                publicar_evento_pedido_creado($order);
            } else {
                // Si la función no existe, es un error de configuración
                throw new \Exception('Función publicar_evento_pedido_creado no encontrada');
            }
        } catch (\Throwable $e) {
            // ================================================================
            // MANEJO DE ERRORES - NON-BLOCKING
            // ================================================================
            // 
            // IMPORTANTE: NO relanzamos la excepción.
            // 
            // ¿Por qué?
            // 1. La orden YA se creó en MySQL (el commit fue exitoso)
            // 2. El cliente ya pagó y espera confirmación
            // 3. Fallar aquí perdería la orden completada
            // 
            // Es mejor:
            // - Loguear el error para que el admin lo revise
            // - Dejar que la orden se complete
            // - Tener un proceso de "reintentar eventos fallidos" si es necesario
            // 
            error_log('Error RabbitMQ (Non-blocking): ' . $e->getMessage());
        }
    }
}
