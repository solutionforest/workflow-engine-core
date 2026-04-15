<?php

namespace SolutionForest\WorkflowEngine\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all workflow-related errors.
 *
 * Provides rich context and debugging information to help developers
 * quickly identify and resolve workflow issues.
 */
abstract class WorkflowException extends Exception
{
    /**
     * Create a new workflow exception with rich context.
     *
     * @param string $message The error message
     * @param array<string, mixed> $context Additional context data for debugging
     * @param int $code The error code (default: 0)
     * @param Throwable|null $previous The previous throwable used for chaining
     */
    public function __construct(
        string $message,
        protected array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the context data for this exception.
     *
     * Contains debugging information such as workflow instance details,
     * step information, configuration, and execution state.
     *
     * @return array<string, mixed> The context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get a specific context value.
     *
     * @param string $key The context key to retrieve
     * @param mixed $default The default value if key doesn't exist
     * @return mixed The context value or default
     */
    public function getContextValue(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Get formatted debug information for logging and error reporting.
     *
     * @return array<string, mixed> Structured debug information
     */
    public function getDebugInfo(): array
    {
        return [
            'exception_type' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'context' => $this->getContext(),
            'suggestions' => $this->getSuggestions(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Get helpful suggestions for resolving this error.
     *
     * Override this method in specific exception classes to provide
     * contextual suggestions based on the error type.
     *
     * @return string[] Array of suggestion strings
     */
    public function getSuggestions(): array
    {
        return [
            'Check the workflow definition for syntax errors',
            'Verify all required action classes exist and are accessible',
            'Review the execution logs for additional context',
        ];
    }

    /**
     * Get a user-friendly error summary.
     *
     * Provides a concise explanation of what went wrong without
     * exposing internal implementation details.
     *
     * @return string User-friendly error description
     */
    abstract public function getUserMessage(): string;
}
