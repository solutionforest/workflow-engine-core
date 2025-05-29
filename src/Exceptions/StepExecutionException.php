<?php

namespace SolutionForest\WorkflowEngine\Exceptions;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

/**
 * Thrown when a workflow step fails during execution.
 *
 * This exception provides detailed context about the failed step,
 * including execution history, retry information, and suggestions
 * for resolving the issue.
 */
final class StepExecutionException extends WorkflowException
{
    /**
     * Create a new step execution exception.
     *
     * @param  string  $message  The error message
     * @param  Step  $step  The step that failed
     * @param  WorkflowContext  $context  The execution context
     * @param  int  $attemptNumber  The current attempt number (for retries)
     * @param  \Throwable|null  $previous  Previous exception that caused the failure
     */
    public function __construct(
        string $message,
        protected readonly Step $step,
        WorkflowContext $context,
        protected readonly int $attemptNumber = 1,
        ?\Throwable $previous = null
    ) {
        $contextData = [
            'step_id' => $step->getId(),
            'step_action' => $step->getActionClass(),
            'step_config' => $step->getConfig(),
            'attempt_number' => $attemptNumber,
            'workflow_id' => $context->getWorkflowId(),
            'context_data' => $context->getData(),
            'execution_time' => $context->executedAt->format('Y-m-d H:i:s'),
        ];

        parent::__construct($message, $contextData, 0, $previous);
    }

    /**
     * Get the step that failed.
     *
     * @return Step The failed step
     */
    public function getStep(): Step
    {
        return $this->step;
    }

    /**
     * Get the current attempt number.
     *
     * @return int The attempt number (1-based)
     */
    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Check if this step can be retried.
     *
     * @return bool True if the step supports retries and hasn't exceeded the limit
     */
    public function canRetry(): bool
    {
        $maxRetries = $this->step->getConfig()['retry_attempts'] ?? 0;

        return $this->attemptNumber <= $maxRetries;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserMessage(): string
    {
        $stepName = $this->step->getId();
        $actionClass = class_basename($this->step->getActionClass());

        return "The workflow step '{$stepName}' (using {$actionClass}) failed to execute. ".
               'This may be due to invalid input data, external service issues, or configuration problems.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSuggestions(): array
    {
        $suggestions = [];
        $actionClass = $this->step->getActionClass();

        // Basic suggestions
        $suggestions[] = "Verify the action class '{$actionClass}' is properly implemented";
        $suggestions[] = 'Check the step configuration and input data for correctness';
        $suggestions[] = 'Review the logs for more detailed error information';

        // Retry-specific suggestions
        if ($this->canRetry()) {
            $maxRetries = $this->step->getConfig()['retry_attempts'] ?? 0;
            $remaining = $maxRetries - $this->attemptNumber + 1;
            $suggestions[] = "This step will be retried automatically ({$remaining} attempts remaining)";
        } elseif (isset($this->step->getConfig()['retry_attempts'])) {
            $suggestions[] = 'All retry attempts have been exhausted';
            $suggestions[] = 'Consider increasing the retry_attempts configuration if this is a transient issue';
        } else {
            $suggestions[] = "Consider adding retry logic by setting 'retry_attempts' in the step configuration";
        }

        // Action-specific suggestions
        if (str_contains($actionClass, 'Http') || str_contains($actionClass, 'Api')) {
            $suggestions[] = 'If this is an HTTP/API action, check network connectivity and service availability';
            $suggestions[] = 'Verify API credentials and endpoint URLs are correct';
        }

        if (str_contains($actionClass, 'Database') || str_contains($actionClass, 'Sql')) {
            $suggestions[] = 'If this is a database action, check database connectivity and permissions';
            $suggestions[] = 'Verify the SQL queries and table/column names are correct';
        }

        if (str_contains($actionClass, 'Email') || str_contains($actionClass, 'Mail')) {
            $suggestions[] = 'If this is an email action, check SMTP configuration and recipient addresses';
        }

        // Context-specific suggestions
        $contextData = $this->getContextValue('context_data', []);
        if (empty($contextData)) {
            $suggestions[] = 'The workflow context is empty - ensure previous steps are setting data correctly';
        }

        return $suggestions;
    }

    /**
     * Create an exception for action class not found.
     *
     * @param  string  $actionClass  The missing action class
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function actionClassNotFound(
        string $actionClass,
        Step $step,
        WorkflowContext $context
    ): static {
        $message = "Action class '{$actionClass}' does not exist or could not be loaded";
        $exception = new self($message, $step, $context);
        $exception->context['error_type'] = 'class_not_found';
        $exception->context['missing_class'] = $actionClass;

        return $exception;
    }

    /**
     * Create an exception for invalid action class.
     *
     * @param  string  $actionClass  The invalid action class
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function invalidActionClass(
        string $actionClass,
        Step $step,
        WorkflowContext $context
    ): static {
        $message = "Class '{$actionClass}' does not implement the WorkflowAction interface";
        $exception = new self($message, $step, $context);
        $exception->context['error_type'] = 'invalid_interface';
        $exception->context['required_interface'] = 'WorkflowAction';

        return $exception;
    }

    /**
     * Create an exception for timeout.
     *
     * @param  int  $timeout  The timeout duration in seconds
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function timeout(
        int $timeout,
        Step $step,
        WorkflowContext $context
    ): static {
        $message = "Step '{$step->getId()}' timed out after {$timeout} seconds";
        $exception = new self($message, $step, $context);
        $exception->context['error_type'] = 'timeout';
        $exception->context['timeout_seconds'] = $timeout;

        return $exception;
    }

    /**
     * Create a StepExecutionException from any other exception.
     *
     * @param  \Exception  $exception  The original exception
     * @param  Step  $step  The step that failed
     * @param  WorkflowContext  $context  The execution context
     */
    public static function fromException(
        \Exception $exception,
        Step $step,
        WorkflowContext $context
    ): static {
        $message = "Step '{$step->getId()}' failed: {$exception->getMessage()}";
        $stepException = new self($message, $step, $context, 0, $exception);

        $stepException->context['original_exception'] = get_class($exception);
        $stepException->context['original_message'] = $exception->getMessage();
        $stepException->context['original_code'] = $exception->getCode();
        $stepException->context['original_file'] = $exception->getFile();
        $stepException->context['original_line'] = $exception->getLine();

        return $stepException;
    }

    /**
     * Create an exception for action execution failure.
     *
     * @param  string  $errorMessage  The action error message
     * @param  Step  $step  The step that failed
     * @param  WorkflowContext  $context  The execution context
     */
    public static function actionFailed(
        string $errorMessage,
        Step $step,
        WorkflowContext $context
    ): static {
        $message = "Action execution failed in step '{$step->getId()}': {$errorMessage}";
        $exception = new self($message, $step, $context);

        $exception->context['error_type'] = 'action_execution_failed';
        $exception->context['action_class'] = $step->getActionClass();
        $exception->context['action_error'] = $errorMessage;

        return $exception;
    }
}
