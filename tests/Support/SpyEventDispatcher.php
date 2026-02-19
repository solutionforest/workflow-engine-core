<?php

namespace SolutionForest\WorkflowEngine\Tests\Support;

use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;

class SpyEventDispatcher implements EventDispatcher
{
    /** @var array<int, object> */
    public array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
    }

    /**
     * Get all dispatched events of a given class.
     *
     * @template T of object
     * @param class-string<T> $eventClass
     * @return array<int, T>
     */
    public function getDispatched(string $eventClass): array
    {
        return array_values(array_filter(
            $this->dispatched,
            fn (object $event) => $event instanceof $eventClass
        ));
    }

    /**
     * Check if an event of the given class was dispatched.
     *
     * @param class-string $eventClass
     */
    public function hasDispatched(string $eventClass): bool
    {
        return count($this->getDispatched($eventClass)) > 0;
    }

    /**
     * Get the count of dispatched events of a given class.
     *
     * @param class-string $eventClass
     */
    public function countDispatched(string $eventClass): int
    {
        return count($this->getDispatched($eventClass));
    }

    /**
     * Reset the dispatched events list.
     */
    public function reset(): void
    {
        $this->dispatched = [];
    }
}
