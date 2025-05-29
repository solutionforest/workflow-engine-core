<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

/**
 * Parses and validates workflow definitions from various formats.
 *
 * The DefinitionParser class handles the conversion of workflow definitions
 * from raw data formats (arrays, JSON strings) into validated WorkflowDefinition
 * objects. It performs comprehensive validation of the workflow structure,
 * steps, transitions, and metadata to ensure definitions are well-formed
 * and executable.
 *
 * ## Key Features
 * - **Multi-format Support**: Accepts array or JSON string input
 * - **Comprehensive Validation**: Validates structure, steps, transitions, and references
 * - **Step Normalization**: Converts various step formats to consistent structure
 * - **Error Context**: Provides detailed error messages with specific validation failures
 * - **Type Safety**: Ensures all required fields are present and correctly typed
 *
 * ## Usage Examples
 *
 * ### Parse Array Definition
 * ```php
 * $parser = new DefinitionParser();
 *
 * $definition = $parser->parse([
 *     'name' => 'user-onboarding',
 *     'version' => '1.0',
 *     'steps' => [
 *         ['id' => 'welcome', 'action' => 'log', 'parameters' => ['message' => 'Welcome!']],
 *         ['id' => 'profile', 'action' => 'SaveProfileAction', 'timeout' => '30s']
 *     ],
 *     'transitions' => [
 *         ['from' => 'welcome', 'to' => 'profile']
 *     ]
 * ]);
 * ```
 *
 * ### Parse JSON Definition
 * ```php
 * $json = file_get_contents('workflow.json');
 * $definition = $parser->parse($json);
 * ```
 *
 * ### With Error Handling
 * ```php
 * try {
 *     $definition = $parser->parse($rawDefinition);
 *     // Use the validated definition...
 * } catch (InvalidWorkflowDefinitionException $e) {
 *     // Handle validation errors with detailed context
 *     echo "Validation failed: " . $e->getMessage();
 *     echo "Context: " . json_encode($e->getContext());
 * }
 * ```
 *
 * ## Validation Rules
 *
 * ### Required Fields
 * - `name`: Non-empty string workflow identifier
 * - `steps`: Array of step definitions with at least one step
 *
 * ### Step Validation
 * - Each step must have a unique ID
 * - Action class (if specified) must be a valid string
 * - Timeout format: `/^\d+[smhd]$/` (e.g., "30s", "5m", "2h", "1d")
 * - Retry attempts: Non-negative integer
 *
 * ### Transition Validation
 * - Must have both `from` and `to` fields
 * - Referenced steps must exist in the workflow
 * - No circular dependencies (future enhancement)
 *
 * @see WorkflowDefinition For the resulting validated definition object
 * @see WorkflowBuilder For fluent definition creation
 * @see InvalidWorkflowDefinitionException For validation error details
 */
class DefinitionParser
{
    /**
     * Parse a workflow definition from array or JSON string format.
     *
     * Converts raw workflow definition data into a validated WorkflowDefinition
     * object. Supports both array and JSON string input formats with comprehensive
     * validation and normalization.
     *
     * @param  array<string, mixed>|string  $definition  Raw workflow definition data
     * @return WorkflowDefinition Validated and normalized workflow definition
     *
     * @throws InvalidWorkflowDefinitionException If definition structure is invalid
     *
     * @example Parse from array
     * ```php
     * $definition = $parser->parse([
     *     'name' => 'order-processing',
     *     'version' => '2.0',
     *     'steps' => [
     *         ['id' => 'validate', 'action' => 'ValidateOrderAction'],
     *         ['id' => 'charge', 'action' => 'ChargePaymentAction', 'timeout' => '30s'],
     *         ['id' => 'fulfill', 'action' => 'FulfillOrderAction']
     *     ],
     *     'transitions' => [
     *         ['from' => 'validate', 'to' => 'charge'],
     *         ['from' => 'charge', 'to' => 'fulfill']
     *     ],
     *     'metadata' => ['department' => 'orders', 'priority' => 'high']
     * ]);
     * ```
     * @example Parse from JSON
     * ```php
     * $json = '{"name":"user-signup","steps":[{"id":"welcome","action":"log"}]}';
     * $definition = $parser->parse($json);
     * ```
     */
    public function parse(array|string $definition): WorkflowDefinition
    {
        // Handle JSON string input
        if (is_string($definition)) {
            $decoded = json_decode($definition, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidWorkflowDefinitionException(
                    'Invalid JSON workflow definition: '.json_last_error_msg(),
                    ['json_error_code' => json_last_error(), 'raw_definition' => substr($definition, 0, 200)],
                    ['JSON parsing failed with error code: '.json_last_error()]
                );
            }
            $definition = $decoded;
        }

        // Validate the complete definition structure
        $this->validateDefinition($definition);

        // Normalize steps before creating WorkflowDefinition
        $definition['steps'] = $this->normalizeSteps($definition['steps']);

        return WorkflowDefinition::fromArray($definition);
    }

    /**
     * Validate the complete workflow definition structure.
     *
     * Performs comprehensive validation of the workflow definition including
     * required fields, data types, step validation, and transition validation.
     * Throws detailed exceptions with context for any validation failures.
     *
     * @param  array<string, mixed>  $definition  The workflow definition to validate
     *
     * @throws InvalidWorkflowDefinitionException If any validation rules fail
     *
     * @internal Called during definition parsing
     */
    private function validateDefinition(array $definition): void
    {
        // Validate required name field
        if (! isset($definition['name'])) {
            throw InvalidWorkflowDefinitionException::missingRequiredField('name', $definition);
        }

        if (! is_string($definition['name']) || empty(trim($definition['name']))) {
            throw InvalidWorkflowDefinitionException::invalidName($definition['name']);
        }

        // Validate required steps field
        if (! isset($definition['steps'])) {
            throw InvalidWorkflowDefinitionException::missingRequiredField('steps', $definition);
        }

        if (! is_array($definition['steps'])) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow definition steps must be an array',
                $definition,
                ['Expected array for steps field, got: '.gettype($definition['steps'])]
            );
        }

        if (empty($definition['steps'])) {
            throw InvalidWorkflowDefinitionException::emptyWorkflow($definition['name']);
        }

        // Normalize steps format - convert sequential array to associative if needed
        $steps = $this->normalizeSteps($definition['steps']);

        // Validate each step
        foreach ($steps as $stepId => $stepData) {
            $this->validateStep($stepId, $stepData, $definition);
        }

        // Validate transitions if present
        if (isset($definition['transitions']) && is_array($definition['transitions'])) {
            foreach ($definition['transitions'] as $transitionIndex => $transition) {
                $this->validateTransition($transition, $steps, $transitionIndex);
            }
        }

        // Validate optional version field
        if (isset($definition['version']) && ! is_string($definition['version'])) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow version must be a string',
                $definition,
                ['Expected string for version field, got: '.gettype($definition['version'])]
            );
        }

        // Validate optional metadata field
        if (isset($definition['metadata']) && ! is_array($definition['metadata'])) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow metadata must be an array',
                $definition,
                ['Expected array for metadata field, got: '.gettype($definition['metadata'])]
            );
        }
    }

    /**
     * Normalize step definitions to consistent associative array format.
     *
     * Converts various step input formats to a standardized associative array
     * where step IDs are keys and step configurations are values. Handles both
     * sequential arrays with 'id' properties and pre-normalized associative arrays.
     *
     * @param  array<int|string, mixed>  $steps  Raw steps array in various formats
     * @return array<string, array<string, mixed>> Normalized steps indexed by ID
     *
     * @throws InvalidWorkflowDefinitionException If step structure is invalid
     *
     * @example Input formats
     * ```php
     * // Sequential array with ID properties (will be normalized)
     * $steps = [
     *     ['id' => 'step1', 'action' => 'LogAction', 'timeout' => '30s'],
     *     ['id' => 'step2', 'action' => 'EmailAction']
     * ];
     *
     * // Already associative (returned as-is)
     * $steps = [
     *     'step1' => ['action' => 'LogAction', 'timeout' => '30s'],
     *     'step2' => ['action' => 'EmailAction']
     * ];
     * ```
     *
     * @internal Used during definition validation and parsing
     */
    private function normalizeSteps(array $steps): array
    {
        // If steps is already associative, return as-is
        if (! array_is_list($steps)) {
            // Type hint: already associative array with string keys
            /** @var array<string, array<string, mixed>> $steps */
            return $steps;
        }

        // Convert sequential array with 'id' property to associative
        $normalizedSteps = [];
        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                throw new InvalidWorkflowDefinitionException(
                    "Step at index {$index} must be an array",
                    ['invalid_step' => $step, 'step_index' => $index],
                    ["Expected array for step {$index}, got: ".gettype($step)]
                );
            }

            if (! isset($step['id'])) {
                throw new InvalidWorkflowDefinitionException(
                    "Step at index {$index} must have an 'id' property when using sequential array format",
                    ['step_data' => $step, 'step_index' => $index],
                    ['Missing required id field in step array']
                );
            }

            $stepId = $step['id'];
            if (! is_string($stepId) || empty(trim($stepId))) {
                throw InvalidWorkflowDefinitionException::invalidStepId($stepId);
            }

            // Check for duplicate step IDs
            if (isset($normalizedSteps[$stepId])) {
                throw InvalidWorkflowDefinitionException::duplicateStepId($stepId);
            }

            // Remove id from step data since it's now the key
            unset($step['id']);
            $normalizedSteps[$stepId] = $step;
        }

        return $normalizedSteps;
    }

    /**
     * Validate an individual step configuration.
     *
     * Performs detailed validation of a single step including ID format,
     * action class validity, timeout format, retry configuration, and
     * other step-specific properties.
     *
     * @param  string  $stepId  The step identifier
     * @param  array<string, mixed>  $stepData  The step configuration data
     * @param  array<string, mixed>  $fullDefinition  Complete workflow definition for context
     *
     * @throws InvalidWorkflowDefinitionException If step configuration is invalid
     *
     * @example Valid step configurations
     * ```php
     * // Basic step
     * $step = ['action' => 'LogAction', 'parameters' => ['message' => 'Hello']];
     *
     * // Step with timeout and retry
     * $step = [
     *     'action' => 'EmailAction',
     *     'timeout' => '30s',
     *     'retry_attempts' => 3,
     *     'parameters' => ['to' => 'user@example.com']
     * ];
     *
     * // Conditional step
     * $step = [
     *     'action' => 'PaymentAction',
     *     'conditions' => ['payment.method === "credit_card"']
     * ];
     * ```
     *
     * @internal Called during definition validation
     */
    private function validateStep(string $stepId, array $stepData, array $fullDefinition): void
    {
        if (empty($stepId)) {
            throw InvalidWorkflowDefinitionException::invalidStepId($stepId);
        }

        // Validate step ID format (alphanumeric, hyphens, underscores only)
        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $stepId)) {
            throw InvalidWorkflowDefinitionException::invalidStepId($stepId);
        }

        // Action is optional for some step types (like manual steps or conditions)
        if (isset($stepData['action'])) {
            if (! is_string($stepData['action'])) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$stepId}' action must be a string",
                    $fullDefinition,
                    ["Expected string for action in step {$stepId}, got: ".gettype($stepData['action'])]
                );
            }

            if (empty(trim($stepData['action']))) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$stepId}' action cannot be empty",
                    $fullDefinition,
                    ['Action field is empty or whitespace only']
                );
            }
        }

        // Validate timeout format if present
        if (isset($stepData['timeout'])) {
            if (! is_string($stepData['timeout']) || ! $this->isValidTimeout($stepData['timeout'])) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$stepId}' has invalid timeout format. Expected format: number followed by s/m/h/d (e.g., '30s', '5m')",
                    $fullDefinition,
                    [
                        "Invalid timeout: {$stepData['timeout']}",
                        'Valid formats: "30s", "5m", "2h", "1d"',
                    ]
                );
            }
        }

        // Validate retry attempts
        if (isset($stepData['retry_attempts'])) {
            if (! is_int($stepData['retry_attempts']) || $stepData['retry_attempts'] < 0) {
                throw InvalidWorkflowDefinitionException::invalidRetryAttempts($stepData['retry_attempts']);
            }
        }

        // Validate parameters field if present
        if (isset($stepData['parameters']) && ! is_array($stepData['parameters'])) {
            throw new InvalidWorkflowDefinitionException(
                "Step '{$stepId}' parameters must be an array",
                $fullDefinition,
                ["Expected array for parameters in step {$stepId}, got: ".gettype($stepData['parameters'])]
            );
        }

        // Validate conditions field if present
        if (isset($stepData['conditions'])) {
            if (! is_array($stepData['conditions'])) {
                throw new InvalidWorkflowDefinitionException(
                    "Step '{$stepId}' conditions must be an array",
                    $fullDefinition,
                    ["Expected array for conditions in step {$stepId}, got: ".gettype($stepData['conditions'])]
                );
            }

            foreach ($stepData['conditions'] as $conditionIndex => $condition) {
                if (! is_string($condition) || empty(trim($condition))) {
                    throw InvalidWorkflowDefinitionException::invalidCondition($condition);
                }
            }
        }
    }

    /**
     * Validate a workflow transition definition.
     *
     * Ensures transition definitions have required fields and reference
     * valid steps that exist in the workflow. Validates transition structure
     * and provides detailed error context for debugging.
     *
     * @param  array<string, mixed>  $transition  The transition definition to validate
     * @param  array<string, array<string, mixed>>  $steps  Available workflow steps
     * @param  int  $transitionIndex  Index of transition for error context
     *
     * @throws InvalidWorkflowDefinitionException If transition is invalid
     *
     * @example Valid transition formats
     * ```php
     * // Basic transition
     * $transition = ['from' => 'step1', 'to' => 'step2'];
     *
     * // Conditional transition
     * $transition = [
     *     'from' => 'payment',
     *     'to' => 'fulfillment',
     *     'condition' => 'payment.status === "success"'
     * ];
     *
     * // Transition with metadata
     * $transition = [
     *     'from' => 'review',
     *     'to' => 'approved',
     *     'metadata' => ['requires_manager_approval' => true]
     * ];
     * ```
     *
     * @internal Called during definition validation
     */
    private function validateTransition(array $transition, array $steps, int $transitionIndex): void
    {
        // Validate required 'from' field
        if (! isset($transition['from'])) {
            throw new InvalidWorkflowDefinitionException(
                "Transition at index {$transitionIndex} must have a 'from' field",
                ['transition' => $transition, 'available_steps' => array_keys($steps)],
                ['Missing required from field in transition']
            );
        }

        // Validate required 'to' field
        if (! isset($transition['to'])) {
            throw new InvalidWorkflowDefinitionException(
                "Transition at index {$transitionIndex} must have a 'to' field",
                ['transition' => $transition, 'available_steps' => array_keys($steps)],
                ['Missing required to field in transition']
            );
        }

        $fromStep = $transition['from'];
        $toStep = $transition['to'];

        // Validate 'from' field type and value
        if (! is_string($fromStep) || empty(trim($fromStep))) {
            throw new InvalidWorkflowDefinitionException(
                "Transition 'from' field must be a non-empty string",
                ['transition' => $transition, 'from_value' => $fromStep],
                ['Invalid from field: '.var_export($fromStep, true)]
            );
        }

        // Validate 'to' field type and value
        if (! is_string($toStep) || empty(trim($toStep))) {
            throw new InvalidWorkflowDefinitionException(
                "Transition 'to' field must be a non-empty string",
                ['transition' => $transition, 'to_value' => $toStep],
                ['Invalid to field: '.var_export($toStep, true)]
            );
        }

        // Validate that referenced steps exist
        if (! isset($steps[$fromStep])) {
            throw new InvalidWorkflowDefinitionException(
                "Transition references unknown source step: '{$fromStep}'",
                [
                    'transition' => $transition,
                    'missing_step' => $fromStep,
                    'available_steps' => array_keys($steps),
                ],
                [
                    "Source step '{$fromStep}' does not exist in workflow",
                    'Available steps: '.implode(', ', array_keys($steps)),
                ]
            );
        }

        if (! isset($steps[$toStep])) {
            throw new InvalidWorkflowDefinitionException(
                "Transition references unknown target step: '{$toStep}'",
                [
                    'transition' => $transition,
                    'missing_step' => $toStep,
                    'available_steps' => array_keys($steps),
                ],
                [
                    "Target step '{$toStep}' does not exist in workflow",
                    'Available steps: '.implode(', ', array_keys($steps)),
                ]
            );
        }

        // Validate optional condition field
        if (isset($transition['condition'])) {
            if (! is_string($transition['condition']) || empty(trim($transition['condition']))) {
                throw InvalidWorkflowDefinitionException::invalidCondition($transition['condition']);
            }
        }

        // Validate optional metadata field
        if (isset($transition['metadata']) && ! is_array($transition['metadata'])) {
            throw new InvalidWorkflowDefinitionException(
                'Transition metadata must be an array',
                ['transition' => $transition],
                ['Expected array for metadata, got: '.gettype($transition['metadata'])]
            );
        }
    }

    /**
     * Validate timeout string format.
     *
     * Checks if a timeout string follows the expected format of a number
     * followed by a time unit (s=seconds, m=minutes, h=hours, d=days).
     *
     * @param  string  $timeout  The timeout string to validate
     * @return bool True if format is valid, false otherwise
     *
     * @example Valid timeout formats
     * ```php
     * $parser->isValidTimeout('30s');   // true - 30 seconds
     * $parser->isValidTimeout('5m');    // true - 5 minutes
     * $parser->isValidTimeout('2h');    // true - 2 hours
     * $parser->isValidTimeout('1d');    // true - 1 day
     * $parser->isValidTimeout('10');    // false - missing unit
     * $parser->isValidTimeout('abc');   // false - invalid format
     * ```
     *
     * @internal Used for step timeout validation
     */
    private function isValidTimeout(string $timeout): bool
    {
        // Valid formats: "30s", "5m", "2h", "1d"
        // Must be: one or more digits followed by exactly one time unit
        return preg_match('/^\d+[smhd]$/', $timeout) === 1;
    }
}
