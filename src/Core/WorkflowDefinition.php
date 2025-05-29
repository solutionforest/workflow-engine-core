<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

/**
 * Represents a complete workflow definition with steps, transitions, and metadata.
 *
 * The WorkflowDefinition class encapsulates the complete structure and configuration
 * of a workflow, including its steps, transitions, metadata, and execution logic.
 * It provides methods for workflow navigation, validation, and serialization.
 *
 * ## Key Features
 * - **Step Management**: Organized collection of workflow steps with fast lookup
 * - **Transition Logic**: Defines how steps connect and flow within the workflow
 * - **Metadata Support**: Extensible metadata for documentation and configuration
 * - **Navigation**: Methods to find first steps, next steps, and validate flow
 * - **Serialization**: Full array/JSON conversion support
 *
 * ## Usage Examples
 *
 * ### Basic Definition Creation
 * ```php
 * $definition = new WorkflowDefinition(
 *     name: 'user-onboarding',
 *     version: '1.0',
 *     steps: [$step1, $step2, $step3],
 *     transitions: [
 *         ['from' => 'step1', 'to' => 'step2'],
 *         ['from' => 'step2', 'to' => 'step3']
 *     ]
 * );
 * ```
 *
 * ### Workflow Navigation
 * ```php
 * $firstStep = $definition->getFirstStep();
 * $nextSteps = $definition->getNextSteps('current_step', $workflowData);
 * $isComplete = $definition->isLastStep('final_step');
 * ```
 *
 * ### Step Access
 * ```php
 * $step = $definition->getStep('send_email');
 * $hasStep = $definition->hasStep('validate_input');
 * $allSteps = $definition->getSteps();
 * ```
 *
 * @see Step For individual step configuration
 * @see WorkflowBuilder For fluent workflow construction
 * @see WorkflowEngine For workflow execution
 */
final class WorkflowDefinition
{
    /** @var array<string, Step> Indexed steps for fast lookup by ID */
    private readonly array $steps;

    /**
     * Create a new workflow definition with validation.
     *
     * @param  string  $name  Unique workflow name/identifier
     * @param  string  $version  Workflow version for change tracking
     * @param  array<Step|array<string, mixed>>  $steps  Array of Step objects or step configurations
     * @param  array<array<string, string>>  $transitions  Array of step transitions
     * @param  array<string, mixed>  $metadata  Additional workflow metadata
     *
     * @throws InvalidWorkflowDefinitionException If the definition is invalid
     */
    public function __construct(
        private readonly string $name,
        private readonly string $version,
        array $steps = [],
        private readonly array $transitions = [],
        private readonly array $metadata = []
    ) {
        $this->steps = $this->processSteps($steps);
    }

    /**
     * Get the workflow name.
     *
     * @return string Unique workflow identifier
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the workflow version.
     *
     * @return string Version string for change tracking
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get all workflow steps indexed by their IDs.
     *
     * @return array<string, Step> Steps indexed by step ID for fast lookup
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get a specific step by its ID.
     *
     * @param  string  $id  Step identifier
     * @return Step|null The step instance, or null if not found
     *
     * @example
     * ```php
     * $emailStep = $definition->getStep('send_welcome_email');
     * if ($emailStep) {
     *     $config = $emailStep->getConfig();
     * }
     * ```
     */
    public function getStep(string $id): ?Step
    {
        return $this->steps[$id] ?? null;
    }

    /**
     * Get all defined step transitions.
     *
     * @return array<array<string, string>> Array of transition configurations
     */
    public function getTransitions(): array
    {
        return $this->transitions;
    }

    /**
     * Get workflow metadata.
     *
     * @return array<string, mixed> Additional workflow configuration and documentation
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Find the first step in the workflow execution sequence.
     *
     * Attempts to find a step with no incoming transitions. If none exists,
     * returns the first step in the steps array.
     *
     * @return Step|null The first step to execute, or null if no steps exist
     *
     * @example
     * ```php
     * $firstStep = $definition->getFirstStep();
     * if ($firstStep) {
     *     $engine->executeStep($firstStep, $context);
     * }
     * ```
     */
    public function getFirstStep(): ?Step
    {
        // Find step with no incoming transitions
        $stepsWithIncoming = [];
        foreach ($this->transitions as $transition) {
            $stepsWithIncoming[] = $transition['to'];
        }

        foreach ($this->steps as $step) {
            if (! in_array($step->getId(), $stepsWithIncoming)) {
                return $step;
            }
        }

        // If no step found without incoming transitions, return first step
        $stepsArray = $this->steps;

        return reset($stepsArray) ?: null;
    }

    /**
     * Get the next steps to execute after the current step.
     *
     * Considers transitions and conditional logic to determine which steps
     * should be executed next based on the current workflow state.
     *
     * @param  string|null  $currentStepId  Current step ID, or null to get first steps
     * @param  array<string, mixed>  $data  Workflow data for condition evaluation
     * @return array<Step> Array of next steps to execute
     *
     * @example
     * ```php
     * $nextSteps = $definition->getNextSteps('validate_order', $workflowData);
     * foreach ($nextSteps as $step) {
     *     if ($step->canExecute($workflowData)) {
     *         $engine->executeStep($step, $context);
     *     }
     * }
     * ```
     */
    public function getNextSteps(?string $currentStepId, array $data = []): array
    {
        if ($currentStepId === null) {
            $firstStep = $this->getFirstStep();

            return $firstStep ? [$firstStep] : [];
        }

        $nextSteps = [];
        foreach ($this->transitions as $transition) {
            if ($transition['from'] === $currentStepId) {
                // Check condition if present
                if (isset($transition['condition']) && ! $this->evaluateCondition($transition['condition'], $data)) {
                    continue;
                }

                $nextStep = $this->getStep($transition['to']);
                if ($nextStep) {
                    $nextSteps[] = $nextStep;
                }
            }
        }

        return $nextSteps;
    }

    /**
     * Check if a step exists in the workflow.
     *
     * @param  string  $stepId  Step identifier to check
     * @return bool True if the step exists
     */
    public function hasStep(string $stepId): bool
    {
        return isset($this->steps[$stepId]);
    }

    /**
     * Check if a step is the last step in the workflow.
     *
     * A step is considered the last step if it has no outgoing transitions.
     *
     * @param  string  $stepId  Step identifier to check
     * @return bool True if this is a terminal step
     *
     * @example
     * ```php
     * if ($definition->isLastStep($currentStepId)) {
     *     // Workflow is complete
     *     $this->markWorkflowComplete($workflowInstance);
     * }
     * ```
     */
    public function isLastStep(string $stepId): bool
    {
        // Check if this step has any outgoing transitions
        foreach ($this->transitions as $transition) {
            if ($transition['from'] === $stepId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process and validate step configurations into Step objects.
     *
     * @param  array<Step|array<string, mixed>>  $stepsData  Array of Step objects or configurations
     * @return array<string, Step> Processed steps indexed by ID
     *
     * @throws InvalidWorkflowDefinitionException If step configuration is invalid
     *
     * @internal Used during workflow definition construction
     */
    private function processSteps(array $stepsData): array
    {
        $steps = [];
        foreach ($stepsData as $index => $stepData) {
            // Handle both Step objects and array data
            if ($stepData instanceof Step) {
                $steps[$stepData->getId()] = $stepData;

                continue;
            }

            // Use the 'id' field from step data, or fall back to array index
            $stepId = $stepData['id'] ?? $index;

            $actionClass = null;
            if (isset($stepData['action'])) {
                $actionClass = ActionResolver::resolve($stepData['action']);
            }

            $steps[$stepId] = new Step(
                id: $stepId,
                actionClass: $actionClass,
                config: $stepData['parameters'] ?? $stepData['config'] ?? [],
                timeout: $stepData['timeout'] ?? null,
                retryAttempts: $stepData['retry_attempts'] ?? 0,
                compensationAction: $stepData['compensation'] ?? null,
                conditions: $stepData['conditions'] ?? [],
                prerequisites: $stepData['prerequisites'] ?? []
            );
        }

        return $steps;
    }

    /**
     * Evaluate a condition expression against workflow data.
     *
     * @param  string  $condition  Condition expression to evaluate
     * @param  array<string, mixed>  $data  Workflow data for evaluation
     * @return bool True if condition evaluates to true
     *
     * @internal Used for transition condition evaluation
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        // Enhanced condition evaluation with comprehensive operator support
        if (preg_match('/(\w+(?:\.\w+)*)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)/', $condition, $matches)) {
            $key = $matches[1];
            $operator = $matches[2];
            $value = trim($matches[3], '"\'');

            $dataValue = $this->getNestedValue($data, $key);

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

        return false; // Default to false if condition cannot be parsed
    }

    /**
     * Convert the workflow definition to an array representation.
     *
     * @return array<string, mixed> Array representation suitable for serialization
     *
     * @example
     * ```php
     * $definitionArray = $definition->toArray();
     * $json = json_encode($definitionArray);
     * file_put_contents('workflow.json', $json);
     * ```
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'steps' => array_map(fn ($step) => $step->toArray(), $this->steps),
            'transitions' => $this->transitions,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a workflow definition from an array representation.
     *
     * @param  array<string, mixed>  $data  Array representation of workflow definition
     * @return static New workflow definition instance
     *
     * @throws InvalidWorkflowDefinitionException If data is invalid
     *
     * @example
     * ```php
     * $json = file_get_contents('workflow.json');
     * $data = json_decode($json, true);
     * $definition = WorkflowDefinition::fromArray($data);
     * ```
     */
    public static function fromArray(array $data): static
    {
        return new self(
            name: $data['name'],
            version: $data['version'] ?? '1.0',
            steps: $data['steps'] ?? [],
            transitions: $data['transitions'] ?? [],
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Get a nested value from an array using dot notation.
     *
     * @param  array<string, mixed>  $array
     */
    private function getNestedValue(array $array, string $key, $default = null)
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
}
