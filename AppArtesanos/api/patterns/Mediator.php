<?php
/**
 * ============================================================================
 * ARCHIVO: Mediator.php - INTERFAZ DEL PATRÓN MEDIATOR
 * ============================================================================
 * 
 * PROPÓSITO:
 * Define el contrato que deben cumplir todos los Mediadores en la aplicación.
 * Es parte de la implementación del Patrón de Diseño Mediator (GoF).
 * 
 * PATRÓN MEDIATOR - TEORÍA:
 * "Define un objeto que encapsula cómo un conjunto de objetos interactúan.
 *  El Mediator promueve el acoplamiento débil evitando que los objetos
 *  se refieran explícitamente entre sí."
 * 
 * COMPONENTES DEL PATRÓN:
 * 1. Mediator (Interface): Este archivo - Define el contrato
 * 2. ConcreteMediator: OrderMediator.php - Implementa la lógica
 * 3. Colleagues: Los servicios (InventoryService, CartService, etc.)
 * 
 * BENEFICIOS:
 * - Los servicios no se conocen entre sí (desacoplamiento)
 * - La lógica de coordinación está centralizada
 * - Fácil de testear y modificar
 * - Un solo punto de control de transacciones
 * 
 * NOTA SOBRE notify():
 * En nuestra implementación, OrderMediator tiene métodos específicos
 * (placeOrder, cancelOrder) en lugar de usar notify() genérico.
 * Esto es una variante pragmática del patrón, apropiada para flujos lineales.
 */
declare(strict_types=1);

namespace Api\Patterns;

/**
 * Interface Mediator
 * 
 * Define el contrato para mediadores que coordinan la interacción
 * entre múltiples componentes (Colleagues).
 * 
 * En el patrón puro, los Colleagues llaman a notify() cuando algo sucede,
 * y el Mediator decide qué hacer (llamar a otros Colleagues, etc.).
 * 
 * @example Uso teórico:
 * // Un Colleague notifica al Mediator:
 * $this->mediator->notify($this, 'stockChanged', ['productId' => 5]);
 * 
 * // El Mediator reacciona:
 * if ($event === 'stockChanged') {
 *     $this->emailService->alertLowStock($data);
 * }
 */
interface Mediator {
    
    /**
     * ========================================================================
     * MÉTODO: notify() - Notificar Evento al Mediador
     * ========================================================================
     * 
     * Este método es llamado por los Colleagues para notificar al Mediador
     * que algo ha sucedido. El Mediador decide qué acción tomar.
     * 
     * FLUJO TÍPICO:
     * 1. Colleague detecta un evento
     * 2. Colleague llama a mediator->notify($this, 'evento', $datos)
     * 3. Mediator procesa el evento
     * 4. Mediator puede llamar a otros Colleagues
     * 
     * NOTA:
     * En OrderMediator, este método está vacío porque usamos métodos
     * específicos (placeOrder, cancelOrder) que son más explícitos
     * para nuestro caso de uso.
     * 
     * @param object $sender El componente que inicia la acción
     * @param string $event Nombre descriptivo del evento
     * @param array $data Datos adicionales necesarios para procesar el evento
     * @return mixed Resultado del procesamiento (depende de la implementación)
     * 
     * @example
     * // Notificar que el stock de un producto cambió:
     * $mediator->notify($inventoryService, 'stockUpdated', [
     *     'productId' => 5,
     *     'newStock' => 10
     * ]);
     * 
     * // Notificar que se creó una orden:
     * $mediator->notify($orderService, 'orderCreated', [
     *     'orderId' => 7,
     *     'total' => 150.00
     * ]);
     */
    public function notify(object $sender, string $event, array $data = []): mixed;
}
