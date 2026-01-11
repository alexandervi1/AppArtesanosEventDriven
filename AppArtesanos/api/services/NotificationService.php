<?php
declare(strict_types=1);

namespace Api\Services;

class NotificationService {
    public function publishOrderCreated(array $order): void {
        // En un refactor puro, moveríamos la lógica de events.php aquí adentro.
        // Para mantener compatibilidad con lo que ya existe, llamaremos a la función global.
        // O mejor aún, usamos el require_once dentro de un método encapsulado.
        
        require_once __DIR__ . '/../includes/events.php';
        
        try {
            if (function_exists('publicar_evento_pedido_creado')) {
                publicar_evento_pedido_creado($order);
            } else {
                throw new \Exception('Función publicar_evento_pedido_creado no encontrada');
            }
        } catch (\Throwable $e) {
            // En producción, solo logueamos el error pero NO interrumpimos el flujo.
            // La orden ya se creó (commit), esto es solo una notificación.
            error_log('Error RabbitMQ (Non-blocking): ' . $e->getMessage());
        }
    }
}
