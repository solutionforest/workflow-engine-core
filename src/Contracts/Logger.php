<?php

namespace SolutionForest\WorkflowEngine\Contracts;

/**
 * Simple logger interface for the workflow engine.
 */
interface Logger
{
    /**
     * Log an info message.
     *
     * @param  array<string, mixed>  $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a warning message.
     *
     * @param  array<string, mixed>  $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log an error message.
     *
     * @param  array<string, mixed>  $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param  array<string, mixed>  $context
     */
    public function debug(string $message, array $context = []): void;
}
