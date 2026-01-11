<?php
declare(strict_types=1);

namespace Api\Patterns;

/**
 * Interface Mediator
 * Defines the contract for mediators that coordinate interaction between components.
 */
interface Mediator {
    /**
     * Executes a specific event or command.
     * 
     * @param object $sender The component initiating the action.
     * @param string $event The event name or context.
     * @param array $data Additional data required for processing.
     * @return mixed
     */
    public function notify(object $sender, string $event, array $data = []): mixed;
}
