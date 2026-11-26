<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Support\Arr;
use SolutionForest\WorkflowEngine\Support\ConditionEvaluator;

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
     * @param string $name Unique workflow name/identifier
     * @param string $version Workflow version for change tracking
     * @param array<Step|array<string, mixed>> $steps Array of Step objects or step configurations
     * @param array<array{from: string, to: string, ...<string, mixed>}> $transitions Array of step transitions
     *                                                                                ('condition', when set, is a string;
     *                                                                                extra keys such as 'metadata' are kept)
     * @param array<string, mixed> $metadata Additional workflow metadata
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
     * @param string $id Step identifier
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
     * @return array<array{from: string, to: string, ...<string, mixed>}> Array of transition configurations
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
        $firstSteps = $this->getFirstSteps();

        return $firstSteps[0] ?? null;
    }

    /**
     * Find every entry point of the workflow.
     *
     * A workflow may legitimately begin with more than one root — several
     * independent branches that later converge. Returning only one of them
     * would silently drop the others while the workflow still reported success,
     * so every step with no incoming transition is treated as a starting point.
     *
     * @return array<int, Step> All steps with no incoming transitions; falls
     *                          back to the first declared step when the graph
     *                          is entirely cyclic.
     */
    public function getFirstSteps(): array
    {
        $stepsWithIncoming = [];
        foreach ($this->transitions as $transition) {
            $stepsWithIncoming[] = $transition['to'];
        }

        $roots = [];
        foreach ($this->steps as $step) {
            if (! in_array($step->getId(), $stepsWithIncoming, true)) {
                $roots[] = $step;
            }
        }

        if ($roots !== []) {
            return $roots;
        }

        // Every step has an incoming transition (a fully cyclic graph): fall
        // back to the first declared step so execution can still begin.
        $stepsArray = $this->steps;
        $first = reset($stepsArray);

        return $first === false ? [] : [$first];
    }

    /**
     * Get the next steps to execute after the current step.
     *
     * Considers transitions and conditional logic to determine which steps
     * should be executed next based on the current workflow state.
     *
     * @param string|null $currentStepId Current step ID, or null to get first steps
     * @param array<string, mixed> $data Workflow data for condition evaluation
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
            return $this->getFirstSteps();
        }

        $nextSteps = [];
        foreach ($this->transitions as $transition) {
            if ($transition['from'] === $currentStepId) {
                // Check condition if present
                $condition = $transition['condition'] ?? null;
                if (is_string($condition) && ! $this->evaluateCondition($condition, $data)) {
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
     * @param string $stepId Step identifier to check
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
     * @param string $stepId Step identifier to check
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
     * @param array<Step|array<string, mixed>> $stepsData Array of Step objects or configurations
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
            if (is_int($stepId)) {
                $stepId = (string) $stepId;
            }
            if (! is_string($stepId)) {
                throw InvalidWorkflowDefinitionException::invalidStepId(Arr::describe($stepId));
            }

            $actionClass = null;
            if (isset($stepData['action'])) {
                if (! is_string($stepData['action'])) {
                    throw InvalidWorkflowDefinitionException::invalidStep($stepId, 'action must be a string', $stepData);
                }
                $actionClass = ActionResolver::resolve($stepData['action']);
            }

            $config = $stepData['parameters'] ?? $stepData['config'] ?? [];
            if (! is_array($config) || ! Arr::isStringKeyed($config)) {
                throw InvalidWorkflowDefinitionException::invalidStep($stepId, 'parameters/config must be an associative array', $stepData);
            }

            // Timeouts are strings such as '30s'; a bare integer means seconds
            // and is kept in its string form, as the constructor always coerced it.
            $timeout = $stepData['timeout'] ?? null;
            if (is_int($timeout)) {
                $timeout = (string) $timeout;
            }
            if ($timeout !== null && ! is_string($timeout)) {
                throw InvalidWorkflowDefinitionException::invalidStep($stepId, 'timeout must be a string such as "30s"', $stepData);
            }

            $retryAttempts = $stepData['retry_attempts'] ?? 0;
            if (! is_int($retryAttempts)) {
                throw InvalidWorkflowDefinitionException::invalidStep($stepId, 'retry_attempts must be an integer', $stepData);
            }

            $conditions = $stepData['conditions'] ?? [];
            if (! is_array($conditions) || ! Arr::isStringArray($conditions)) {
                throw InvalidWorkflowDefinitionException::invalidStep($stepId, 'conditions must be an array of strings', $stepData);
            }

            $prerequisites = $stepData['prerequisites'] ?? [];
            if (! is_array($prerequisites) || ! Arr::isStringArray($prerequisites)) {
                throw InvalidWorkflowDefinitionException::invalidStep($stepId, 'prerequisites must be an array of step IDs', $stepData);
            }

            $steps[$stepId] = new Step(
                id: $stepId,
                actionClass: $actionClass,
                config: $config,
                timeout: $timeout,
                retryAttempts: $retryAttempts,
                conditions: $conditions,
                prerequisites: $prerequisites
            );
        }

        return $steps;
    }

    /**
     * Evaluate a condition expression against workflow data.
     *
     * @param string $condition Condition expression to evaluate
     * @param array<string, mixed> $data Workflow data for evaluation
     * @return bool True if condition evaluates to true
     *
     * @internal Used for transition condition evaluation
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        return ConditionEvaluator::evaluate($condition, $data);
    }

    /**
     * A SHA-256 of the definition's content. An instance records it, so you can tell which
     * version of an edited workflow it runs. Key order does not change it.
     */
    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode(self::canonical($this->toArray()), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }

    /**
     * Convert the workflow definition to an array representation.
     *
     * @return array{name: string, version: string, steps: array<string, array<string, mixed>>, transitions: array<array{from: string, to: string, ...<string, mixed>}>, metadata: array<string, mixed>} Array representation suitable for serialization
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
            'steps' => array_map(fn (Step $step): array => $step->toArray(), $this->steps),
            'transitions' => $this->transitions,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create a workflow definition from an array representation.
     *
     * @param array<string, mixed> $data Array representation of workflow definition
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
        $name = $data['name'] ?? null;
        if (! is_string($name)) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow definition name must be a string',
                $data,
                ['Expected string for name, got: '.get_debug_type($name)]
            );
        }

        $version = $data['version'] ?? '1.0';
        if (! is_string($version)) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow version must be a string',
                $data,
                ['Expected string for version, got: '.get_debug_type($version)]
            );
        }

        $metadata = $data['metadata'] ?? [];
        if (! is_array($metadata) || ! Arr::isStringKeyed($metadata)) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow metadata must be an associative array',
                $data,
                ['Expected associative array for metadata, got: '.get_debug_type($metadata)]
            );
        }

        return new self(
            name: $name,
            version: $version,
            steps: self::stepsFromArray($data['steps'] ?? [], $data),
            transitions: self::transitionsFromArray($data['transitions'] ?? [], $data),
            metadata: $metadata
        );
    }

    /**
     * @param array<string, mixed> $data Full definition, for error context
     * @return array<Step|array<string, mixed>>
     *
     * @throws InvalidWorkflowDefinitionException If steps is not an array of step configurations
     */
    private static function stepsFromArray(mixed $steps, array $data): array
    {
        if (! is_array($steps)) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow definition steps must be an array',
                $data,
                ['Expected array for steps, got: '.get_debug_type($steps)]
            );
        }

        $result = [];
        foreach ($steps as $index => $step) {
            if (! $step instanceof Step && (! is_array($step) || ! Arr::isStringKeyed($step))) {
                throw new InvalidWorkflowDefinitionException(
                    "Step at index {$index} must be a Step or an associative array",
                    $data,
                    ["Expected Step or associative array for step {$index}, got: ".get_debug_type($step)]
                );
            }
            $result[$index] = $step;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data Full definition, for error context
     * @return array<array{from: string, to: string, ...<string, mixed>}>
     *
     * @throws InvalidWorkflowDefinitionException If a transition lacks string 'from'/'to' fields
     */
    private static function transitionsFromArray(mixed $transitions, array $data): array
    {
        if (! is_array($transitions)) {
            throw new InvalidWorkflowDefinitionException(
                'Workflow transitions must be an array',
                $data,
                ['Expected array for transitions, got: '.get_debug_type($transitions)]
            );
        }

        $result = [];
        foreach ($transitions as $index => $transition) {
            $from = is_array($transition) ? ($transition['from'] ?? null) : null;
            $to = is_array($transition) ? ($transition['to'] ?? null) : null;
            $condition = is_array($transition) ? ($transition['condition'] ?? null) : null;

            if (! is_array($transition)
                || ! Arr::isStringKeyed($transition)
                || ! is_string($from)
                || ! is_string($to)
                || ($condition !== null && ! is_string($condition))
            ) {
                throw new InvalidWorkflowDefinitionException(
                    "Transition at index {$index} must have string 'from' and 'to' fields (and an optional string 'condition')",
                    $data,
                    ['Invalid transition: '.Arr::describe($transition)]
                );
            }

            // Re-assert the validated fields in place so the key order (and any
            // extra keys such as 'metadata') is preserved.
            $transition['from'] = $from;
            $transition['to'] = $to;
            $result[$index] = $transition;
        }

        return $result;
    }
}
