<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Contracts\Logger;

/**
 * Null object pattern implementation for logger.
 * Used when no logger is provided.
 */
final class NullLogger implements Logger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void
    {
        // Do nothing - null object pattern
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void
    {
        // Do nothing - null object pattern
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void
    {
        // Do nothing - null object pattern
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): void
    {
        // Do nothing - null object pattern
    }
}
