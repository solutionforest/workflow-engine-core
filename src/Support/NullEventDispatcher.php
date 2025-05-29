<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;

/**
 * Null object pattern implementation for event dispatcher.
 * Used when no event dispatcher is provided.
 */
final class NullEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        // Do nothing - null object pattern
    }
}
