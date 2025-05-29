<?php

namespace SolutionForest\WorkflowEngine\Exceptions;

/**
 * Thrown when a workflow definition is invalid or malformed.
 *
 * This exception indicates issues with the workflow structure,
 * missing required fields, or invalid configuration values.
 */
final class InvalidWorkflowDefinitionException extends WorkflowException
{
    /**
     * Create a new invalid workflow definition exception.
     *
     * @param  string  $message  The error message
     * @param  array<string, mixed>  $definition  The invalid definition that caused the error
     * @param  string[]  $validationErrors  Specific validation error messages
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message,
        array $definition = [],
        protected readonly array $validationErrors = [],
        ?\Throwable $previous = null
    ) {
        $context = [
            'definition' => $definition,
            'validation_errors' => $validationErrors,
            'definition_keys' => array_keys($definition),
        ];

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get the validation errors that caused this exception.
     *
     * @return string[] Array of validation error messages
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserMessage(): string
    {
        return 'The workflow definition contains errors and cannot be processed. Please check the definition structure and ensure all required fields are present.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSuggestions(): array
    {
        $suggestions = [
            'Verify the workflow definition follows the correct schema',
            'Check that all required fields (name, steps) are present',
            'Ensure step IDs are unique within the workflow',
            'Validate that all action classes exist and implement WorkflowAction',
        ];

        if (! empty($this->validationErrors)) {
            $suggestions[] = 'Review the specific validation errors: '.implode(', ', $this->validationErrors);
        }

        $definition = $this->getContextValue('definition', []);
        if (empty($definition['name'])) {
            $suggestions[] = 'Add a "name" field to identify your workflow';
        }
        if (empty($definition['steps'])) {
            $suggestions[] = 'Add at least one step to the "steps" array';
        }

        return $suggestions;
    }

    /**
     * Create an exception for a missing required field.
     *
     * @param  string  $fieldName  The name of the missing field
     * @param  array<string, mixed>  $definition  The workflow definition
     */
    public static function missingRequiredField(string $fieldName, array $definition): static
    {
        return new self(
            "Required field '{$fieldName}' is missing from workflow definition",
            $definition,
            ["Missing required field: {$fieldName}"]
        );
    }

    /**
     * Create an exception for invalid step configuration.
     *
     * @param  string  $stepId  The step ID with invalid configuration
     * @param  string  $reason  The reason why the step is invalid
     * @param  array<string, mixed>  $definition  The workflow definition
     */
    public static function invalidStep(string $stepId, string $reason, array $definition): static
    {
        return new self(
            "Step '{$stepId}' has invalid configuration: {$reason}",
            $definition,
            ["Invalid step '{$stepId}': {$reason}"]
        );
    }

    /**
     * Create exception for invalid step ID.
     *
     * @param  string  $stepId  The invalid step ID
     */
    public static function invalidStepId(string $stepId): static
    {
        return new self(
            message: "Invalid step ID: '{$stepId}'. Step ID cannot be empty.",
            definition: ['provided_step_id' => $stepId],
            validationErrors: [
                'Use a descriptive step identifier',
                'Examples: "send_email", "validate_input", "process_payment"',
                'Ensure the step ID is not empty or whitespace-only',
            ]
        );
    }

    /**
     * Create exception for invalid retry attempts.
     *
     * @param  int  $attempts  The invalid retry attempts value
     */
    public static function invalidRetryAttempts(int $attempts): static
    {
        return new self(
            message: "Invalid retry attempts: {$attempts}. Must be between 0 and 10.",
            definition: ['provided_attempts' => $attempts, 'valid_range' => '0-10'],
            validationErrors: [
                'Use a value between 0 and 10 for retry attempts',
                'Consider 0 for no retries, 3 for moderate resilience, or 5+ for critical operations',
                'Too many retries can delay workflow completion significantly',
            ]
        );
    }

    /**
     * Create exception for invalid timeout.
     *
     * @param  int|null  $timeout  The invalid timeout value
     */
    public static function invalidTimeout(?int $timeout): static
    {
        return new self(
            message: "Invalid timeout: {$timeout}. Timeout must be a positive integer or null.",
            definition: ['provided_timeout' => $timeout],
            validationErrors: [
                'Use a positive integer for timeout in seconds',
                'Use null for no timeout limit',
                'Consider reasonable timeouts: 30s for quick operations, 300s for complex tasks',
            ]
        );
    }

    /**
     * Create an exception for duplicate step ID.
     *
     * @param  string  $stepId  The duplicate step ID
     */
    public static function duplicateStepId(string $stepId): static
    {
        return new self(
            message: "Duplicate step ID: '{$stepId}'. Step IDs must be unique within a workflow.",
            definition: ['duplicate_step_id' => $stepId],
            validationErrors: [
                'Use unique step identifiers within each workflow',
                'Consider adding prefixes or suffixes to make IDs unique',
                'Examples: "send_email_1", "send_email_welcome", "send_email_reminder"',
            ]
        );
    }

    /**
     * Create exception for invalid workflow name.
     *
     * @param  string  $name  The invalid workflow name
     * @param  string|null  $reason  Additional reason for the error
     */
    public static function invalidName(string $name, ?string $reason = null): static
    {
        $message = "Invalid workflow name: '{$name}'";
        if ($reason) {
            $message .= ". {$reason}";
        }

        $suggestions = [
            'Use a descriptive name that starts with a letter',
            'Only use letters, numbers, hyphens, and underscores',
            'Avoid special characters and spaces',
            'Examples: "user-onboarding", "order_processing", "documentApproval"',
        ];

        return new self(
            message: $message,
            definition: [
                'provided_name' => $name,
                'validation_rule' => '/^[a-zA-Z][a-zA-Z0-9_-]*$/',
                'reason' => $reason,
            ],
            validationErrors: $suggestions
        );
    }

    /**
     * Create exception for invalid condition expression.
     *
     * @param  string  $condition  The invalid condition expression
     */
    public static function invalidCondition(string $condition): static
    {
        return new self(
            message: "Invalid condition expression: '{$condition}'. Condition cannot be empty.",
            definition: ['provided_condition' => $condition],
            validationErrors: [
                'Use valid condition expressions with comparison operators',
                'Examples: "user.premium === true", "order.amount > 1000", "status !== \'cancelled\'"',
                'Supported operators: ===, !==, ==, !=, >, <, >=, <=',
                'Use dot notation to access nested properties: "user.profile.type"',
            ]
        );
    }

    /**
     * Create exception for invalid delay configuration.
     *
     * @param  int|null  $seconds  Provided seconds value
     * @param  int|null  $minutes  Provided minutes value
     * @param  int|null  $hours  Provided hours value
     */
    public static function invalidDelay(?int $seconds, ?int $minutes, ?int $hours): static
    {
        return new self(
            message: 'Invalid delay configuration. At least one positive time value must be provided.',
            definition: [
                'provided_seconds' => $seconds,
                'provided_minutes' => $minutes,
                'provided_hours' => $hours,
            ],
            validationErrors: [
                'Provide at least one positive time value',
                'Examples: delay(seconds: 30), delay(minutes: 5), delay(hours: 1)',
                'You can combine multiple time units: delay(hours: 1, minutes: 30)',
                'All time values must be positive integers',
            ]
        );
    }

    /**
     * Create exception for empty workflow (no steps defined).
     *
     * @param  string  $workflowName  The name of the empty workflow
     */
    public static function emptyWorkflow(string $workflowName): static
    {
        return new self(
            message: "Workflow '{$workflowName}' cannot be built with no steps defined.",
            definition: ['workflow_name' => $workflowName],
            validationErrors: [
                'Add at least one step using addStep(), startWith(), or then() methods',
                'Example: $builder->addStep("validate", ValidateAction::class)',
                'Consider using common patterns: email(), delay(), or http()',
                'Use WorkflowBuilder::quick() for pre-built common workflows',
            ]
        );
    }

    /**
     * Create exception for action class not found.
     *
     * @param  string  $actionName  The missing action name or class
     * @param  array<string, mixed>  $context  Additional context information
     */
    public static function actionNotFound(string $actionName, array $context = []): static
    {
        $message = "Action '{$actionName}' could not be found or resolved to a valid class.";

        $suggestions = [
            'Check if the action class exists and is properly autoloaded',
            'Verify the action name spelling and capitalization',
            'Ensure the action class implements the WorkflowAction interface',
        ];

        if (isset($context['tried_classes'])) {
            $suggestions[] = 'Tried resolving to classes: '.implode(', ', $context['tried_classes']);
        }

        if (isset($context['predefined_actions'])) {
            $suggestions[] = 'Available predefined actions: '.implode(', ', $context['predefined_actions']);
        }

        if (isset($context['custom_actions']) && ! empty($context['custom_actions'])) {
            $suggestions[] = 'Available custom actions: '.implode(', ', $context['custom_actions']);
        }

        if (isset($context['suggestion'])) {
            $suggestions[] = $context['suggestion'];
        }

        return new self(
            message: $message,
            definition: array_merge(['action_name' => $actionName], $context),
            validationErrors: $suggestions
        );
    }

    /**
     * Create exception for invalid action class (doesn't implement required interface).
     *
     * @param  string  $className  The invalid action class name
     * @param  string  $requiredInterface  The required interface
     */
    public static function invalidActionClass(string $className, string $requiredInterface): static
    {
        return new self(
            message: "Class '{$className}' does not implement the required '{$requiredInterface}' interface.",
            definition: [
                'class_name' => $className,
                'required_interface' => $requiredInterface,
                'class_exists' => class_exists($className),
                'implemented_interfaces' => class_exists($className) ? class_implements($className) : [],
            ],
            validationErrors: [
                "Make sure '{$className}' implements the '{$requiredInterface}' interface",
                'Check that the class has the required methods: execute(), canExecute(), getName(), getDescription()',
                'Verify the class is properly imported and autoloaded',
                "Example: class MyAction implements {$requiredInterface} { ... }",
            ]
        );
    }
}
