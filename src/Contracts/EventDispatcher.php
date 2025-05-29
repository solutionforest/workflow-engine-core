<?php

namespace SolutionForest\WorkflowEngine\Contracts;

/**
 * Simple event dispatcher interface for the workflow engine.
 */
interface EventDispatcher
{
    /**
     * Dispatch an event.
     */
    public function dispatch(object $event): void;
}
