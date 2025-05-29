<?php

namespace SolutionForest\WorkflowEngine\Core;

use DateTime;

/**
 * Immutable workflow execution context using PHP 8.3+ readonly properties.
 *
 * The WorkflowContext represents the complete execution state and data for a specific
 * workflow step. It provides immutable access to workflow data, configuration, and
 * execution metadata while ensuring thread-safety and preventing accidental mutations.
 *
 * ## Key Features
 * - **Immutable**: All properties are readonly, preventing accidental modifications
 * - **Type-Safe**: Full type hints for better IDE support and runtime safety
 * - **Fluent API**: Methods return new instances for functional programming style
 * - **Laravel Integration**: Supports Laravel's data_get/data_set helpers
 *
 * ## Usage Examples
 *
 * ### Basic Data Access
 * ```php
 * $context = new WorkflowContext(
 *     workflowId: 'user-onboarding',
 *     stepId: 'send_welcome_email',
 *     data: ['user' => ['email' => 'user@example.com', 'name' => 'John']]
 * );
 *
 * $userEmail = data_get($context->data, 'user.email'); // 'user@example.com'
 * $hasUserData = $context->hasData('user.name'); // true
 * ```
 *
 * ### Immutable Updates
 * ```php
 * $newContext = $context->with('user.verified', true);
 * $mergedContext = $context->withData(['order' => ['id' => 123]]);
 * ```
 *
 * ### Configuration Access
 * ```php
 * $timeout = $context->getConfig('timeout', 30);
 * $allConfig = $context->getConfig();
 * ```
 *
 * @see WorkflowInstance For workflow execution state management
 * @see WorkflowEngine For workflow execution coordination
 */
final readonly class WorkflowContext
{
    /**
     * Create a new immutable workflow context.
     *
     * @param  string  $workflowId  Unique identifier for the workflow definition
     * @param  string  $stepId  Current step identifier within the workflow
     * @param  array<string, mixed>  $data  Workflow execution data (JSON-serializable)
     * @param  array<string, mixed>  $config  Step-specific configuration parameters
     * @param  WorkflowInstance|null  $instance  Associated workflow instance (if available)
     * @param  DateTime  $executedAt  Timestamp when this context was created
     */
    public function __construct(
        public string $workflowId,
        public string $stepId,
        public array $data = [],
        public array $config = [],
        public ?WorkflowInstance $instance = null,
        public DateTime $executedAt = new DateTime
    ) {}

    /**
     * Get the workflow identifier.
     *
     * @return string The unique workflow definition identifier
     */
    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    /**
     * Get the current step identifier.
     *
     * @return string The step identifier within the workflow
     */
    public function getStepId(): string
    {
        return $this->stepId;
    }

    /**
     * Get all workflow execution data.
     *
     * @return array<string, mixed> Complete data array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get all workflow execution data (alias for compatibility).
     *
     * @return array<string, mixed> Complete data array
     *
     * @deprecated Use getData() instead
     */
    public function getAllData(): array
    {
        return $this->data;
    }

    /**
     * Create a new context with additional data merged in (immutable operation).
     *
     * The new data is merged with existing data using array_merge, so new keys
     * are added and existing keys are overwritten.
     *
     * @param  array<string, mixed>  $newData  Data to merge with existing data
     * @return static New context instance with merged data
     *
     * @example
     * ```php
     * $newContext = $context->withData([
     *     'order' => ['id' => 123, 'total' => 99.99],
     *     'payment' => ['method' => 'credit_card']
     * ]);
     * ```
     */
    public function withData(array $newData): static
    {
        return new self(
            workflowId: $this->workflowId,
            stepId: $this->stepId,
            data: array_merge($this->data, $newData),
            config: $this->config,
            instance: $this->instance,
            executedAt: $this->executedAt
        );
    }

    /**
     * Create a new context with a single data value set (immutable operation).
     *
     * Uses Laravel's data_set helper for setting nested values using dot notation.
     *
     * @param  string  $key  Data key (supports dot notation for nested access)
     * @param  mixed  $value  Value to set
     * @return static New context instance with updated data
     *
     * @example
     * ```php
     * $newContext = $context->with('user.email', 'newemail@example.com');
     * $nestedContext = $context->with('order.items.0.quantity', 2);
     * ```
     */
    public function with(string $key, mixed $value): static
    {
        $newData = $this->data;
        data_set($newData, $key, $value);

        return new self(
            workflowId: $this->workflowId,
            stepId: $this->stepId,
            data: $newData,
            config: $this->config,
            instance: $this->instance,
            executedAt: $this->executedAt
        );
    }

    /**
     * Check if a data key exists in the context.
     *
     * Uses Laravel's data_get helper for checking nested keys using dot notation.
     *
     * @param  string  $key  Data key (supports dot notation for nested access)
     * @return bool True if the key exists and has a non-null value
     *
     * @example
     * ```php
     * if ($context->hasData('user.email')) {
     *     // User email is available
     * }
     *
     * if ($context->hasData('order.payment.status')) {
     *     // Payment status is set
     * }
     * ```
     */
    public function hasData(string $key): bool
    {
        return data_get($this->data, $key) !== null;
    }

    /**
     * Get configuration value(s) for the current step.
     *
     * @param  string|null  $key  Configuration key (supports dot notation), or null for all config
     * @param  mixed  $default  Default value to return if key doesn't exist
     * @return mixed Configuration value or default
     *
     * @example
     * ```php
     * $timeout = $context->getConfig('timeout', 30);
     * $retries = $context->getConfig('retry.attempts', 3);
     * $allConfig = $context->getConfig(); // Gets all configuration
     * ```
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    /**
     * Get current step ID (alias for compatibility).
     *
     * @return string The step identifier within the workflow
     *
     * @deprecated Use getStepId() instead
     */
    public function getCurrentStepId(): string
    {
        return $this->stepId;
    }

    /**
     * Convert the context to an array representation for serialization.
     *
     * @return array<string, mixed> Array representation suitable for JSON encoding
     *
     * @example
     * ```php
     * $contextArray = $context->toArray();
     * $json = json_encode($contextArray);
     * ```
     */
    public function toArray(): array
    {
        return [
            'workflow_id' => $this->workflowId,
            'step_id' => $this->stepId,
            'data' => $this->data,
            'config' => $this->config,
            'instance_id' => $this->instance?->getId(),
            'executed_at' => $this->executedAt->format('Y-m-d H:i:s'),
        ];
    }
}
