<?php

namespace SolutionForest\WorkflowEngine\Core;

/**
 * Represents a single step in a workflow with complete configuration and execution metadata.
 *
 * The Step class encapsulates all information needed to execute a workflow step,
 * including the action to perform, configuration parameters, execution constraints,
 * and conditional logic.
 *
 * ## Key Features
 * - **Action Execution**: Links to specific action classes for step execution
 * - **Configuration**: Step-specific parameters and settings
 * - **Resilience**: Timeout and retry configuration for robust execution
 * - **Conditional Logic**: Support for conditional step execution
 * - **Compensation**: Rollback actions for error scenarios
 *
 * ## Usage Examples
 *
 * ### Basic Step Creation
 * ```php
 * $step = new Step(
 *     id: 'send_email',
 *     actionClass: SendEmailAction::class,
 *     config: ['template' => 'welcome', 'to' => 'user@example.com']
 * );
 * ```
 *
 * ### Step with Resilience Configuration
 * ```php
 * $step = new Step(
 *     id: 'payment_processing',
 *     actionClass: ProcessPaymentAction::class,
 *     config: ['gateway' => 'stripe'],
 *     timeout: '300', // 5 minutes
 *     retryAttempts: 3
 * );
 * ```
 *
 * ### Conditional Step
 * ```php
 * $step = new Step(
 *     id: 'premium_features',
 *     actionClass: EnablePremiumAction::class,
 *     conditions: ['user.plan === "premium"']
 * );
 * ```
 *
 * @see WorkflowAction For action implementation interface
 * @see WorkflowDefinition For step orchestration and transitions
 */
class Step
{
    /**
     * Create a new workflow step with comprehensive configuration.
     *
     * @param  string  $id  Unique step identifier within the workflow
     * @param  string|null  $actionClass  Fully qualified action class name
     * @param  array<string, mixed>  $config  Step-specific configuration parameters
     * @param  string|null  $timeout  Maximum execution time (in seconds as string)
     * @param  int  $retryAttempts  Number of retry attempts on failure (0-10)
     * @param  string|null  $compensationAction  Action class for rollback scenarios
     * @param  array<string>  $conditions  Array of condition expressions for conditional execution
     * @param  array<string>  $prerequisites  Array of prerequisite step IDs that must complete first
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $actionClass = null,
        private readonly array $config = [],
        private readonly ?string $timeout = null,
        private readonly int $retryAttempts = 0,
        private readonly ?string $compensationAction = null,
        private readonly array $conditions = [],
        private readonly array $prerequisites = []
    ) {}

    /**
     * Get the unique step identifier.
     *
     * @return string The step ID within the workflow
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the action class name for this step.
     *
     * @return string|null Fully qualified action class name, or null for no-op steps
     */
    public function getActionClass(): ?string
    {
        return $this->actionClass;
    }

    /**
     * Get the step-specific configuration parameters.
     *
     * @return array<string, mixed> Configuration array passed to the action
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the maximum execution timeout for this step.
     *
     * @return string|null Timeout in seconds as string, or null for no timeout
     */
    public function getTimeout(): ?string
    {
        return $this->timeout;
    }

    /**
     * Get the number of retry attempts on failure.
     *
     * @return int Number of retries (0 means no retries)
     */
    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    /**
     * Get the compensation action class for rollback scenarios.
     *
     * @return string|null Compensation action class name, or null if none
     */
    public function getCompensationAction(): ?string
    {
        return $this->compensationAction;
    }

    /**
     * Get the conditional expressions for this step.
     *
     * @return array<string> Array of condition expressions that must all be true
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get the prerequisite step IDs that must complete before this step.
     *
     * @return array<string> Array of step IDs that are prerequisites
     */
    public function getPrerequisites(): array
    {
        return $this->prerequisites;
    }

    /**
     * Check if this step has an associated action to execute.
     *
     * @return bool True if an action class is defined
     */
    public function hasAction(): bool
    {
        return $this->actionClass !== null;
    }

    /**
     * Check if this step has a compensation action for rollback.
     *
     * @return bool True if a compensation action is defined
     */
    public function hasCompensation(): bool
    {
        return $this->compensationAction !== null;
    }

    /**
     * Determine if this step can execute based on its conditions.
     *
     * Evaluates all condition expressions against the provided data.
     * All conditions must be true for the step to be executable.
     *
     * @param  array<string, mixed>  $data  Workflow data to evaluate conditions against
     * @return bool True if all conditions pass (or no conditions exist)
     *
     * @example
     * ```php
     * $step = new Step(
     *     id: 'premium_step',
     *     actionClass: PremiumAction::class,
     *     conditions: ['user.plan === "premium"', 'user.active === true']
     * );
     *
     * $data = ['user' => ['plan' => 'premium', 'active' => true]];
     * $canExecute = $step->canExecute($data); // true
     * ```
     */
    public function canExecute(array $data): bool
    {
        foreach ($this->conditions as $condition) {
            if (! $this->evaluateCondition($condition, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition expression against workflow data.
     *
     * Supports basic comparison operators and nested property access using dot notation.
     *
     * @param  string  $condition  Condition expression (e.g., "user.age >= 18")
     * @param  array<string, mixed>  $data  Workflow data to evaluate against
     * @return bool True if condition evaluates to true
     *
     * @internal This method handles condition parsing and evaluation
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        // Enhanced condition evaluation with support for more operators
        if (preg_match('/(\w+(?:\.\w+)*)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)/', $condition, $matches)) {
            $key = $matches[1];
            $operator = $matches[2];
            $value = trim($matches[3], '"\'');

            $dataValue = data_get($data, $key);

            return match ($operator) {
                '===' => $dataValue === $value,
                '!==' => $dataValue !== $value,
                '==' => $dataValue == $value,
                '!=' => $dataValue != $value,
                '>' => $dataValue > $value,
                '<' => $dataValue < $value,
                '>=' => $dataValue >= $value,
                '<=' => $dataValue <= $value,
                default => false,
            };
        }

        return true; // Default to true if condition cannot be parsed
    }

    /**
     * Convert the step to an array representation for serialization.
     *
     * @return array<string, mixed> Array representation suitable for JSON encoding
     *
     * @example
     * ```php
     * $stepArray = $step->toArray();
     * $json = json_encode($stepArray);
     * ```
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->actionClass,
            'config' => $this->config,
            'timeout' => $this->timeout,
            'retry_attempts' => $this->retryAttempts,
            'compensation' => $this->compensationAction,
            'conditions' => $this->conditions,
            'prerequisites' => $this->prerequisites,
        ];
    }
}
