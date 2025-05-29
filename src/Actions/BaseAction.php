<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Contracts\Logger;
use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;
use SolutionForest\WorkflowEngine\Support\NullLogger;

/**
 * Base implementation for workflow actions with common functionality.
 *
 * BaseAction provides a convenient foundation for implementing workflow actions
 * with built-in logging, error handling, configuration management, and lifecycle
 * methods. It implements the template method pattern to provide consistent
 * action execution flow while allowing customization of specific logic.
 *
 * ## Features Provided
 * - **Automatic Logging**: Comprehensive logging of action execution and errors
 * - **Error Handling**: Consistent exception handling and error reporting
 * - **Configuration Management**: Easy access to action configuration
 * - **Template Method**: Structured execution flow with customization points
 * - **Validation**: Built-in prerequisite checking before execution
 *
 * ## Usage Examples
 *
 * ### Simple Action Implementation
 * ```php
 * class UpdateUserStatusAction extends BaseAction
 * {
 *     protected function doExecute(WorkflowContext $context): ActionResult
 *     {
 *         $userId = data_get($context->getData(), 'user.id');
 *         $status = $this->getConfig('status', 'active');
 *
 *         User::where('id', $userId)->update(['status' => $status]);
 *
 *         return ActionResult::success(['user_id' => $userId, 'status' => $status]);
 *     }
 *
 *     public function canExecute(WorkflowContext $context): bool
 *     {
 *         return data_get($context->getData(), 'user.id') !== null;
 *     }
 *
 *     public function getName(): string
 *     {
 *         return 'Update User Status';
 *     }
 * }
 * ```
 *
 * ### Action with Complex Validation
 * ```php
 * class ProcessPaymentAction extends BaseAction
 * {
 *     public function canExecute(WorkflowContext $context): bool
 *     {
 *         $data = $context->getData();
 *
 *         // Check required data
 *         if (!data_get($data, 'payment.amount') || !data_get($data, 'payment.method')) {
 *             return false;
 *         }
 *
 *         // Check configuration
 *         return !empty($this->getConfig('gateway_key'));
 *     }
 * }
 * ```
 *
 * @see WorkflowAction For the interface definition
 * @see ActionResult For return value structure
 * @see StepExecutionException For error handling patterns
 */
abstract class BaseAction implements WorkflowAction
{
    /** @var array<string, mixed> Action-specific configuration parameters */
    protected array $config;

    /** @var Logger Logger instance for action execution logging */
    protected Logger $logger;

    /**
     * Create a new action instance.
     *
     * @param  array<string, mixed>  $config  Action-specific configuration
     * @param  Logger|null  $logger  Optional logger instance (uses NullLogger if not provided)
     */
    public function __construct(array $config = [], ?Logger $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * Execute the workflow action with comprehensive logging and error handling.
     *
     * This method implements the template method pattern, providing a consistent
     * execution flow while allowing customization through the abstract doExecute
     * method and optional canExecute validation.
     *
     * ## Execution Flow
     * 1. **Logging**: Log action start with context information
     * 2. **Validation**: Check prerequisites via canExecute()
     * 3. **Execution**: Run business logic via doExecute()
     * 4. **Success Logging**: Log successful completion with results
     * 5. **Error Handling**: Catch and log any exceptions
     *
     * ## Error Handling
     * All exceptions are caught and converted to failed ActionResults.
     * For custom error handling, override this method or throw
     * StepExecutionException with specific context.
     *
     * @param  WorkflowContext  $context  The current workflow execution context
     * @return ActionResult Success or failure result with data and messages
     *
     * @throws StepExecutionException When action execution fails
     *
     * @example Basic execution flow
     * ```php
     * $action = new MyAction(['config' => 'value']);
     * $result = $action->execute($context);
     *
     * if ($result->isSuccess()) {
     *     echo "Action completed: " . json_encode($result->getData());
     * } else {
     *     echo "Action failed: " . $result->getMessage();
     * }
     * ```
     */
    public function execute(WorkflowContext $context): ActionResult
    {
        $this->logger->info('Executing action', [
            'action' => static::class,
            'action_name' => $this->getName(),
            'workflow_id' => $context->getWorkflowId(),
            'step_id' => $context->getStepId(),
            'config' => $this->config,
        ]);

        try {
            // Validate prerequisites before execution
            if (! $this->canExecute($context)) {
                $message = sprintf(
                    'Action prerequisites not met for %s in workflow %s step %s',
                    $this->getName(),
                    $context->getWorkflowId(),
                    $context->getStepId()
                );

                $this->logger->warning('Action prerequisites failed', [
                    'action' => static::class,
                    'workflow_id' => $context->getWorkflowId(),
                    'step_id' => $context->getStepId(),
                ]);

                return ActionResult::failure($message);
            }

            // Execute the action's business logic
            $result = $this->doExecute($context);

            // Log successful completion with result data
            $this->logger->info('Action completed successfully', [
                'action' => static::class,
                'action_name' => $this->getName(),
                'workflow_id' => $context->getWorkflowId(),
                'step_id' => $context->getStepId(),
                'success' => $result->isSuccess(),
                'result_data' => $result->getData(),
            ]);

            return $result;

        } catch (StepExecutionException $e) {
            // Re-throw StepExecutionException to preserve context
            $this->logger->error('Action failed with step execution exception', [
                'action' => static::class,
                'workflow_id' => $context->getWorkflowId(),
                'step_id' => $context->getStepId(),
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            // Log and return failure result for general exceptions
            // The Executor will convert this to a StepExecutionException with Step context
            $this->logger->error('Action failed with unexpected exception', [
                'action' => static::class,
                'workflow_id' => $context->getWorkflowId(),
                'step_id' => $context->getStepId(),
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return ActionResult::failure(
                sprintf(
                    'Action %s failed: %s',
                    $this->getName(),
                    $e->getMessage()
                ),
                [
                    'exception_class' => get_class($e),
                    'exception_code' => $e->getCode(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                ]
            );
        }
    }

    /**
     * Check if the action can be executed with the given context.
     *
     * This method provides a validation hook before action execution.
     * Override this method to implement custom validation logic, such as
     * checking required data fields, external service availability, or
     * action-specific prerequisites.
     *
     * ## Validation Examples
     * - **Data Validation**: Check required fields in context data
     * - **Configuration**: Verify required configuration values
     * - **External Dependencies**: Test connectivity to external services
     * - **Business Rules**: Apply domain-specific validation logic
     *
     * @param  WorkflowContext  $context  The current workflow execution context
     * @return bool True if the action can be executed, false otherwise
     *
     * @example Data validation
     * ```php
     * public function canExecute(WorkflowContext $context): bool
     * {
     *     $data = $context->getData();
     *
     *     // Check required fields
     *     if (!data_get($data, 'user.id') || !data_get($data, 'email')) {
     *         return false;
     *     }
     *
     *     // Check configuration
     *     return !empty($this->getConfig('api_key'));
     * }
     * ```
     */
    public function canExecute(WorkflowContext $context): bool
    {
        return true; // Default implementation allows execution
    }

    /**
     * Get a human-readable name for this action.
     *
     * Override this method to provide a descriptive name for the action
     * that will be used in logging, debugging, and user interfaces.
     * The default implementation returns the class name without namespace.
     *
     * @return string The action name
     *
     * @example Custom action name
     * ```php
     * public function getName(): string
     * {
     *     return 'Send Welcome Email';
     * }
     * ```
     */
    public function getName(): string
    {
        $parts = explode('\\', static::class);

        return end($parts);
    }

    /**
     * Get a detailed description of what this action does.
     *
     * Override this method to provide a comprehensive description of the
     * action's purpose, behavior, and effects. This is useful for
     * documentation, debugging, and workflow visualization tools.
     *
     * @return string The action description
     *
     * @example Detailed description
     * ```php
     * public function getDescription(): string
     * {
     *     return 'Sends a personalized welcome email to new users with account setup instructions and verification link.';
     * }
     * ```
     */
    public function getDescription(): string
    {
        return 'Base workflow action implementation with logging and error handling';
    }

    /**
     * Implement the actual action logic in this method.
     *
     * This is the main method where action-specific business logic should be
     * implemented. It will be called by the execute() method after validation
     * and logging setup. The method should return an ActionResult indicating
     * success or failure.
     *
     * ## Implementation Guidelines
     * - **Return ActionResult**: Always return success() or failure() result
     * - **Exception Handling**: Let exceptions bubble up for consistent logging
     * - **Data Access**: Use context data and action configuration
     * - **Side Effects**: Perform the action's business operations
     *
     * @param  WorkflowContext  $context  The current workflow execution context
     * @return ActionResult The result of the action execution
     *
     * @throws \Exception Any exceptions during action execution
     *
     * @example Implementation pattern
     * ```php
     * protected function doExecute(WorkflowContext $context): ActionResult
     * {
     *     // Get data from context
     *     $userId = data_get($context->getData(), 'user.id');
     *     $email = data_get($context->getData(), 'user.email');
     *
     *     // Get configuration
     *     $template = $this->getConfig('email_template', 'welcome');
     *
     *     // Perform business logic
     *     $result = EmailService::send($email, $template, ['user_id' => $userId]);
     *
     *     // Return appropriate result
     *     if ($result['sent']) {
     *         return ActionResult::success([
     *             'email_id' => $result['id'],
     *             'sent_at' => (new \DateTime())->format('c')
     *         ]);
     *     } else {
     *         return ActionResult::failure('Failed to send email: ' . $result['error']);
     *     }
     * }
     * ```
     */
    abstract protected function doExecute(WorkflowContext $context): ActionResult;

    /**
     * Get a configuration value with optional default.
     *
     * Retrieves a value from the action configuration using dot notation.
     * This is a convenience method for accessing action-specific settings
     * that were provided during action instantiation.
     *
     * @param  string  $key  The configuration key (supports dot notation)
     * @param  mixed  $default  The default value if key is not found
     * @return mixed The configuration value or default
     *
     * @example Configuration access
     * ```php
     * // Simple key
     * $apiKey = $this->getConfig('api_key');
     *
     * // Nested key with dot notation
     * $timeout = $this->getConfig('http.timeout', 30);
     *
     * // Array key with default
     * $retries = $this->getConfig('retry.attempts', 3);
     * ```
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    /**
     * Get a nested value from an array using dot notation.
     *
     * @param  array<string, mixed>  $array
     */
    protected function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        // Handle dot notation for nested arrays
        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $nestedKey) {
            if (! is_array($value) || ! array_key_exists($nestedKey, $value)) {
                return $default;
            }
            $value = $value[$nestedKey];
        }

        return $value;
    }

    /**
     * Get all action configuration.
     *
     * Returns the complete configuration array that was provided
     * during action construction. Useful for debugging or when
     * you need to access multiple configuration values.
     *
     * @return array<string, mixed> The complete configuration array
     *
     * @example Configuration access
     * ```php
     * $config = $this->getAllConfig();
     *
     * // Log all configuration for debugging
     * Log::debug('Action config', $config);
     *
     * // Check if any configuration was provided
     * if (empty($config)) {
     *     // Use default behavior
     * }
     * ```
     */
    protected function getAllConfig(): array
    {
        return $this->config;
    }
}
