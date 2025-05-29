<?php

namespace SolutionForest\WorkflowEngine\Core;

/**
 * Represents the result of a workflow action execution.
 *
 * ActionResult is an immutable value object that encapsulates the outcome
 * of executing a workflow action. It provides a consistent interface for
 * communicating success/failure status, data, error messages, and metadata
 * between workflow steps and the execution engine.
 *
 * ## Key Features
 * - **Immutable**: Once created, the result cannot be modified
 * - **Type Safety**: Clear distinction between success and failure states
 * - **Rich Context**: Supports data, error messages, and metadata
 * - **Serializable**: Can be converted to arrays for storage/transmission
 * - **Chainable**: Factory methods for fluent result creation
 *
 * ## Usage Examples
 *
 * ### Success Results
 * ```php
 * // Simple success
 * $result = ActionResult::success();
 *
 * // Success with data
 * $result = ActionResult::success([
 *     'user_id' => 123,
 *     'email_sent' => true,
 *     'timestamp' => (new \DateTime())->format('c')
 * ]);
 *
 * // Success with data and metadata
 * $result = ActionResult::success(
 *     ['processed_count' => 50],
 *     ['execution_time_ms' => 1250, 'memory_peak_mb' => 12.5]
 * );
 * ```
 *
 * ### Failure Results
 * ```php
 * // Simple failure
 * $result = ActionResult::failure('Database connection failed');
 *
 * // Failure with metadata for debugging
 * $result = ActionResult::failure(
 *     'API rate limit exceeded',
 *     [
 *         'retry_after' => 3600,
 *         'requests_remaining' => 0,
 *         'reset_time' => '2024-01-01T15:00:00Z'
 *     ]
 * );
 * ```
 *
 * ### Conditional Results
 * ```php
 * $users = User::where('active', true)->get();
 *
 * if ($users->count() > 0) {
 *     return ActionResult::success([
 *         'users' => $users->toArray(),
 *         'count' => $users->count()
 *     ]);
 * } else {
 *     return ActionResult::failure('No active users found');
 * }
 * ```
 *
 * @see WorkflowAction For the interface that returns ActionResult
 * @see BaseAction For the base implementation using ActionResult
 */
final class ActionResult
{
    /**
     * Create a new action result.
     *
     * @param bool $success Whether the action execution was successful
     * @param string|null $errorMessage Error message for failed actions
     * @param array<string, mixed> $data Result data for successful actions
     * @param array<string, mixed> $metadata Additional context and debugging information
     */
    public function __construct(
        private readonly bool $success,
        private readonly ?string $errorMessage = null,
        private readonly array $data = [],
        private readonly array $metadata = []
    ) {}

    /**
     * Create a successful action result.
     *
     * Use this factory method to create a result indicating successful
     * action execution. The data array should contain any information
     * that needs to be passed to subsequent workflow steps.
     *
     * @param array<string, mixed> $data Result data to pass to next steps
     * @param array<string, mixed> $metadata Additional execution metadata
     * @return static A successful action result
     *
     * @example Basic success
     * ```php
     * return ActionResult::success([
     *     'order_id' => $order->id,
     *     'total_amount' => $order->total,
     *     'status' => 'completed'
     * ]);
     * ```
     * @example Success with metadata
     * ```php
     * return ActionResult::success(
     *     ['emails_sent' => $count],
     *     ['execution_time' => $duration, 'memory_used' => $memory]
     * );
     * ```
     */
    public static function success(array $data = [], array $metadata = []): static
    {
        return new self(true, null, $data, $metadata);
    }

    /**
     * Create a failed action result.
     *
     * Use this factory method to create a result indicating failed
     * action execution. The error message should be descriptive and
     * helpful for debugging. Metadata can include additional context.
     *
     * @param string $errorMessage Descriptive error message
     * @param array<string, mixed> $metadata Additional error context and debugging info
     * @return static A failed action result
     *
     * @example Basic failure
     * ```php
     * return ActionResult::failure('User with ID 123 not found');
     * ```
     * @example Failure with debugging metadata
     * ```php
     * return ActionResult::failure(
     *     'Payment gateway timeout',
     *     [
     *         'gateway' => 'stripe',
     *         'response_time' => 30000,
     *         'attempt_number' => 3
     *     ]
     * );
     * ```
     */
    public static function failure(string $errorMessage, array $metadata = []): static
    {
        return new self(false, $errorMessage, [], $metadata);
    }

    /**
     * Check if the action execution was successful.
     *
     * @return bool True if the action succeeded, false otherwise
     *
     * @example Conditional processing
     * ```php
     * $result = $action->execute($context);
     *
     * if ($result->isSuccess()) {
     *     $data = $result->getData();
     *     // Process success data
     * }
     * ```
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the action execution failed.
     *
     * @return bool True if the action failed, false otherwise
     *
     * @example Error handling
     * ```php
     * if ($result->isFailure()) {
     *     Log::error('Action failed: ' . $result->getErrorMessage());
     * }
     * ```
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * Get the error message for failed results.
     *
     * Returns the error message if the action failed, or null if it succeeded.
     * The error message should provide clear information about what went wrong.
     *
     * @return string|null The error message, or null for successful results
     *
     * @example Error message access
     * ```php
     * if ($result->isFailure()) {
     *     $errorMessage = $result->getErrorMessage();
     *     throw new \Exception("Action failed: {$errorMessage}");
     * }
     * ```
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get the result data from successful actions.
     *
     * Returns the data array containing information produced by the action.
     * For failed actions, this will always be an empty array.
     *
     * @return array<string, mixed> The result data
     *
     * @example Data access
     * ```php
     * if ($result->isSuccess()) {
     *     $data = $result->getData();
     *     $userId = data_get($data, 'user.id');
     *     $email = data_get($data, 'user.email');
     * }
     * ```
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Check if the result contains any data.
     *
     * @return bool True if the result has data, false if empty
     *
     * @example Data presence check
     * ```php
     * if ($result->hasData()) {
     *     $this->processResultData($result->getData());
     * }
     * ```
     */
    public function hasData(): bool
    {
        return ! empty($this->data);
    }

    /**
     * Get the metadata for additional context.
     *
     * Metadata contains additional information about the action execution,
     * such as performance metrics, debugging information, or other context
     * that doesn't belong in the main result data.
     *
     * @return array<string, mixed> The metadata array
     *
     * @example Metadata access
     * ```php
     * $metadata = $result->getMetadata();
     * $executionTime = data_get($metadata, 'execution_time_ms');
     * $memoryUsage = data_get($metadata, 'memory_peak_mb');
     * ```
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a new result with additional metadata.
     *
     * Since ActionResult is immutable, this method returns a new instance
     * with the provided metadata merged with the existing metadata.
     *
     * @param array<string, mixed> $metadata Additional metadata to merge
     * @return static A new ActionResult instance with merged metadata
     *
     * @example Adding metadata
     * ```php
     * $result = ActionResult::success(['user_id' => 123]);
     * $resultWithMetadata = $result->withMetadata([
     *     'execution_time' => 150,
     *     'cache_hit' => true
     * ]);
     * ```
     */
    public function withMetadata(array $metadata): static
    {
        return new self(
            $this->success,
            $this->errorMessage,
            $this->data,
            array_merge($this->metadata, $metadata)
        );
    }

    /**
     * Create a new result with an additional metadata entry.
     *
     * Convenience method for adding a single metadata entry. Returns a new
     * ActionResult instance with the additional metadata.
     *
     * @param string $key The metadata key
     * @param mixed $value The metadata value
     * @return static A new ActionResult instance with the additional metadata
     *
     * @example Adding single metadata
     * ```php
     * $result = ActionResult::success(['data' => 'value']);
     * $resultWithTimer = $result->withMetadataEntry('duration_ms', 250);
     * ```
     */
    public function withMetadataEntry(string $key, $value): self
    {
        return $this->withMetadata([$key => $value]);
    }

    /**
     * Convert the result to an array representation.
     *
     * Creates a plain array representation of the action result that can
     * be serialized, stored, or transmitted. Useful for logging, caching,
     * or API responses.
     *
     * @return array<string, mixed> Array representation of the result
     *
     * @example Serialization
     * ```php
     * $result = ActionResult::success(['user_id' => 123]);
     * $array = $result->toArray();
     *
     * // Store in cache or database
     * Cache::put('action_result', json_encode($array));
     *
     * // Log for debugging
     * Log::info('Action completed', $array);
     * ```
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error_message' => $this->errorMessage,
            'data' => $this->data,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get a specific data value using dot notation.
     *
     * Convenience method for accessing nested data values without
     * manually checking array keys. Returns null if the key doesn't exist.
     *
     * @param string $key The data key (supports dot notation)
     * @param mixed $default The default value if key is not found
     * @return mixed The data value or default
     *
     * @example Dot notation access
     * ```php
     * $result = ActionResult::success([
     *     'user' => ['id' => 123, 'email' => 'user@example.com'],
     *     'metadata' => ['timestamp' => '2024-01-01T12:00:00Z']
     * ]);
     *
     * $userId = $result->get('user.id'); // 123
     * $email = $result->get('user.email'); // 'user@example.com'
     * $timestamp = $result->get('metadata.timestamp');
     * $missing = $result->get('user.phone', 'N/A'); // 'N/A'
     * ```
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * Create a new successful result by merging data with this result.
     *
     * Convenience method for creating a new success result that combines
     * the current result's data with additional data. Only works with
     * successful results.
     *
     * @param array<string, mixed> $additionalData Data to merge
     * @return static A new successful ActionResult with merged data
     *
     * @throws \LogicException If called on a failed result
     *
     * @example Merging results
     * ```php
     * $result1 = ActionResult::success(['user_id' => 123]);
     * $result2 = $result1->mergeData(['email' => 'user@example.com']);
     *
     * // result2 now contains: ['user_id' => 123, 'email' => 'user@example.com']
     * ```
     */
    public function mergeData(array $additionalData): self
    {
        if ($this->isFailure()) {
            throw new \LogicException('Cannot merge data on a failed ActionResult');
        }

        return self::success(
            array_merge($this->data, $additionalData),
            $this->metadata
        );
    }
}
