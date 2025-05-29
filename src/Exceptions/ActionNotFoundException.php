<?php

namespace SolutionForest\WorkflowEngine\Exceptions;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

/**
 * Thrown when a workflow action class cannot be found or loaded.
 *
 * This exception provides detailed guidance for resolving action-related
 * issues including class loading, dependency injection, and interface compliance.
 */
final class ActionNotFoundException extends WorkflowException
{
    /**
     * Create a new action not found exception.
     *
     * @param  string  $actionClass  The action class that was not found
     * @param  string  $stepId  The step ID that references this action
     * @param  string|null  $errorType  The type of error (class_not_found, interface_mismatch, etc.)
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        protected readonly string $actionClass,
        protected readonly string $stepId,
        protected readonly ?string $errorType = null,
        ?\Throwable $previous = null
    ) {
        $message = "Action class '{$actionClass}' for step '{$stepId}' could not be loaded";

        $context = [
            'action_class' => $actionClass,
            'step_id' => $stepId,
            'error_type' => $errorType,
            'class_exists' => class_exists($actionClass),
            'suggested_namespace' => $this->suggestNamespace($actionClass),
        ];

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get the action class that was not found.
     *
     * @return string The action class name
     */
    public function getActionClass(): string
    {
        return $this->actionClass;
    }

    /**
     * Get the step ID that references this action.
     *
     * @return string The step ID
     */
    public function getStepId(): string
    {
        return $this->stepId;
    }

    /**
     * Get the error type.
     *
     * @return string|null The error type
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /**
     * Suggest a proper namespace for the action class.
     *
     * @param  string  $className  The class name to analyze
     * @return string|null Suggested namespace
     */
    private function suggestNamespace(string $className): ?string
    {
        // Common Laravel action patterns
        $suggestions = [
            'App\\Actions\\',
            'App\\Workflow\\Actions\\',
            'App\\Services\\',
            'App\\Jobs\\',
        ];

        foreach ($suggestions as $namespace) {
            if (class_exists($namespace.$className)) {
                return $namespace.$className;
            }
        }

        return null;
    }

    /**
     * Get helpful suggestions for resolving this error.
     *
     * @return array<string> Array of suggestion messages
     */
    public function getSuggestions(): array
    {
        $suggestions = [];

        // Check if class exists anywhere
        if (! class_exists($this->actionClass)) {
            $suggestions[] = "Ensure the action class '{$this->actionClass}' exists and is properly autoloaded";

            $suggested = $this->suggestNamespace(class_basename($this->actionClass));
            if ($suggested) {
                $suggestions[] = "Did you mean '{$suggested}'?";
            }

            $suggestions[] = 'Run "composer dump-autoload" to refresh the autoloader';
            $suggestions[] = 'Check that the namespace declaration in the action class file is correct';
        }

        // Interface compliance check
        if (class_exists($this->actionClass)) {
            $reflection = new \ReflectionClass($this->actionClass);
            if (! $reflection->implementsInterface('SolutionForest\\WorkflowEngine\\Contracts\\WorkflowAction')) {
                $suggestions[] = 'Action class must implement the WorkflowAction interface';
                $suggestions[] = "Add 'implements WorkflowAction' to your class declaration";
            }
        }

        $suggestions[] = 'Verify the action class is registered in your service container if using dependency injection';

        return $suggestions;
    }

    /**
     * Get user-friendly error message for display.
     *
     * @return string User-friendly error message
     */
    public function getUserMessage(): string
    {
        return "The action '{$this->actionClass}' for step '{$this->stepId}' could not be found or loaded.";
    }

    /**
     * Create exception for action not found errors.
     *
     * @param  string  $actionClass  The action class that was not found
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function actionNotFound(
        string $actionClass,
        Step $step,
        WorkflowContext $context
    ): static {
        return new self($actionClass, $step->getId(), 'action_not_found');
    }

    /**
     * Create exception for invalid action class.
     *
     * @param  string  $actionClass  The invalid action class name
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function invalidActionClass(
        string $actionClass,
        Step $step,
        WorkflowContext $context
    ): static {
        return new self($actionClass, $step->getId(), 'invalid_action_class');
    }

    /**
     * Create an exception for class not found errors.
     *
     * @param  string  $actionClass  The missing action class name
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function classNotFound(
        string $actionClass,
        Step $step,
        WorkflowContext $context
    ): static {
        return new self($actionClass, $step->getId(), 'class_not_found');
    }

    /**
     * Create an exception for an action class with invalid interface.
     *
     * @param  string  $actionClass  The invalid action class name
     * @param  Step  $step  The step configuration
     * @param  WorkflowContext  $context  The execution context
     */
    public static function invalidInterface(
        string $actionClass,
        Step $step,
        WorkflowContext $context
    ): static {
        return new self($actionClass, $step->getId(), 'invalid_interface');
    }
}
