<?php

namespace SolutionForest\WorkflowEngine\Exceptions;

/**
 * Thrown when a workflow instance cannot be found in storage.
 *
 * This exception provides helpful suggestions for troubleshooting
 * missing workflow instances and storage configuration issues.
 */
final class WorkflowInstanceNotFoundException extends WorkflowException
{
    /**
     * Create a new workflow instance not found exception.
     *
     * @param  string  $instanceId  The workflow instance ID that was not found
     * @param  string|null  $storageType  The storage adapter type being used
     * @param  array<string, mixed>  $searchFilters  Any filters that were applied during search
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        protected readonly string $instanceId,
        protected readonly ?string $storageType = null,
        array $searchFilters = [],
        ?\Throwable $previous = null
    ) {
        $message = "Workflow instance '{$instanceId}' was not found";

        $context = [
            'instance_id' => $instanceId,
            'storage_type' => $storageType,
            'search_filters' => $searchFilters,
        ];

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get the workflow instance ID that was not found.
     *
     * @return string The instance ID
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * Get the storage type being used.
     *
     * @return string|null The storage adapter type
     */
    public function getStorageType(): ?string
    {
        return $this->storageType;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserMessage(): string
    {
        return "The requested workflow instance '{$this->instanceId}' could not be found. ".
               'It may have been deleted, or the instance ID may be incorrect.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSuggestions(): array
    {
        $suggestions = [
            "Verify the workflow instance ID '{$this->instanceId}' is correct",
            'Check if the workflow instance was created successfully',
            'Ensure the storage configuration is working properly',
        ];

        // Storage-specific suggestions
        if ($this->storageType) {
            switch (strtolower($this->storageType)) {
                case 'database':
                    $suggestions[] = 'Check database connectivity and table structure';
                    $suggestions[] = 'Verify the workflow_instances table exists and is accessible';
                    $suggestions[] = 'Look for any database migration issues';
                    break;

                case 'file':
                    $suggestions[] = 'Check file system permissions for the storage directory';
                    $suggestions[] = 'Verify the storage directory exists and is writable';
                    $suggestions[] = 'Look for any file corruption or disk space issues';
                    break;

                case 'redis':
                    $suggestions[] = 'Check Redis connectivity and configuration';
                    $suggestions[] = 'Verify Redis is running and accessible';
                    $suggestions[] = 'Check if the Redis key may have expired';
                    break;
            }
        }

        // ID format suggestions
        if (strlen($this->instanceId) < 10) {
            $suggestions[] = "The instance ID seems unusually short - verify it's a complete ID";
        }

        if (preg_match('/[^a-zA-Z0-9\-_]/', $this->instanceId)) {
            $suggestions[] = "The instance ID contains unusual characters - ensure it's properly formatted";
        }

        $suggestions[] = "Use the workflow engine's getInstance() method to check if the instance exists";
        $suggestions[] = 'Review recent logs for any deletion or cleanup operations';

        return $suggestions;
    }

    /**
     * Create an exception for a malformed instance ID.
     *
     * @param  string  $instanceId  The malformed instance ID
     * @param  string  $expectedFormat  Description of the expected format
     * @param  string|null  $storageType  The storage type being used
     */
    public static function malformedId(
        string $instanceId,
        string $expectedFormat,
        ?string $storageType = null
    ): static {
        $exception = new self($instanceId, $storageType);
        $exception->context['error_type'] = 'malformed_id';
        $exception->context['expected_format'] = $expectedFormat;

        return $exception;
    }

    /**
     * Create an exception for storage connectivity issues.
     *
     * @param  string  $instanceId  The instance ID that was being searched for
     * @param  string  $storageType  The storage type that failed
     * @param  string  $connectionError  The connection error message
     */
    public static function storageConnectionError(
        string $instanceId,
        string $storageType,
        string $connectionError
    ): static {
        $exception = new self($instanceId, $storageType);
        $exception->context['error_type'] = 'storage_connection';
        $exception->context['connection_error'] = $connectionError;

        return $exception;
    }

    /**
     * Create an exception for a workflow instance that was not found.
     *
     * @param  string  $instanceId  The workflow instance ID that was not found
     * @param  string|null  $storageType  The storage adapter type
     */
    public static function notFound(string $instanceId, ?string $storageType = null): static
    {
        return new self(
            $instanceId,
            $storageType,
            [] // empty search filters
        );
    }
}
