<?php

namespace SolutionForest\WorkflowEngine\Core;

/**
 * Represents an active instance of a workflow execution.
 *
 * WorkflowInstance encapsulates the runtime state of a workflow execution,
 * including current progress, data, completed steps, failures, and timing
 * information. Each instance represents a unique execution of a workflow
 * definition with specific input data and maintains its state throughout
 * the execution lifecycle.
 *
 * ## Key Features
 * - **State Management**: Tracks workflow execution state (pending, running, completed, failed)
 * - **Data Context**: Maintains workflow data that flows between steps
 * - **Progress Tracking**: Records completed and failed steps with timestamps
 * - **Error Handling**: Captures and stores error messages and failure context
 * - **Serialization**: Can be converted to/from arrays for persistence
 * - **Navigation**: Provides access to next available steps and prerequisites
 *
 * ## Workflow Lifecycle
 * 1. **Creation**: Instance created with initial data and workflow definition
 * 2. **Execution**: Steps are executed, data is updated, progress is tracked
 * 3. **Completion**: Workflow reaches final state (completed or failed)
 * 4. **Persistence**: Instance state can be serialized for storage
 *
 * ## Usage Examples
 *
 * ### Creating an Instance
 * ```php
 * $definition = WorkflowBuilder::create('user-onboarding')
 *     ->addStep('send-welcome', SendWelcomeEmailAction::class)
 *     ->addStep('create-profile', CreateProfileAction::class)
 *     ->build();
 *
 * $instance = new WorkflowInstance(
 *     id: 'user-123-onboarding',
 *     definition: $definition,
 *     state: WorkflowState::Pending,
 *     data: ['user_id' => 123, 'email' => 'user@example.com']
 * );
 * ```
 *
 * ### Tracking Progress
 * ```php
 * // Check current progress
 * $progress = $instance->getProgress(); // 0.0 to 100.0
 *
 * // Mark step as completed
 * $instance->addCompletedStep('send-welcome');
 *
 * // Check what steps can be executed next
 * $nextSteps = $instance->getNextSteps();
 * ```
 *
 * ### Data Management
 * ```php
 * // Update workflow data
 * $instance->mergeData(['profile_created' => true, 'welcome_sent' => true]);
 *
 * // Get current data
 * $data = $instance->getData();
 * $userId = data_get($data, 'user_id');
 * ```
 *
 * ### Error Handling
 * ```php
 * // Record a failed step
 * $instance->addFailedStep('send-welcome', 'SMTP server unavailable');
 *
 * // Update workflow state
 * $instance->setState(WorkflowState::Failed);
 * $instance->setErrorMessage('Email delivery failed');
 * ```
 *
 * ### Serialization
 * ```php
 * // Convert to array for storage
 * $data = $instance->toArray();
 * Cache::put("workflow:{$instance->getId()}", $data);
 *
 * // Restore from array
 * $restoredInstance = WorkflowInstance::fromArray($data, $definition);
 * ```
 *
 * @see WorkflowDefinition For the workflow blueprint
 * @see WorkflowState For execution state enumeration
 * @see WorkflowContext For step execution context
 */
final class WorkflowInstance
{
    /** @var WorkflowState Current execution state of the workflow */
    private WorkflowState $state;

    /** @var array<string, mixed> Workflow data that flows between steps */
    private array $data;

    /** @var string|null ID of the currently executing or next step */
    private ?string $currentStepId = null;

    /** @var array<int, string> List of step IDs that have been completed */
    private array $completedSteps = [];

    /** @var array<int, array{step_id: string, error: string, failed_at: string}> List of failed steps with error details */
    private array $failedSteps = [];

    /** @var string|null Overall workflow error message if execution failed */
    private ?string $errorMessage = null;

    /** @var \DateTime When this workflow instance was created */
    private readonly \DateTime $createdAt;

    /** @var \DateTime When this workflow instance was last updated */
    private \DateTime $updatedAt;

    /**
     * Create a new workflow instance.
     *
     * @param  string  $id  Unique identifier for this workflow instance
     * @param  WorkflowDefinition  $definition  The workflow definition blueprint
     * @param  WorkflowState  $state  Initial execution state
     * @param  array<string, mixed>  $data  Initial workflow data
     * @param  \DateTime|null  $createdAt  Creation timestamp (defaults to now)
     * @param  \DateTime|null  $updatedAt  Last update timestamp (defaults to now)
     */
    public function __construct(
        private readonly string $id,
        private readonly WorkflowDefinition $definition,
        WorkflowState $state,
        array $data = [],
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->state = $state;
        $this->data = $data;
        $this->createdAt = $createdAt ?? new \DateTime;
        $this->updatedAt = $updatedAt ?? new \DateTime;
    }

    /**
     * Get the unique identifier for this workflow instance.
     *
     * @return string The instance ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the workflow definition blueprint.
     *
     * @return WorkflowDefinition The workflow definition
     */
    public function getDefinition(): WorkflowDefinition
    {
        return $this->definition;
    }

    /**
     * Get the current workflow execution state.
     *
     * @return WorkflowState The current state
     */
    public function getState(): WorkflowState
    {
        return $this->state;
    }

    /**
     * Update the workflow execution state.
     *
     * @param  WorkflowState  $state  The new execution state
     */
    public function setState(WorkflowState $state): void
    {
        $this->state = $state;
        $this->updatedAt = new \DateTime;
    }

    /**
     * Get the current workflow data.
     *
     * @return array<string, mixed> The workflow data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Replace the entire workflow data.
     *
     * @param  array<string, mixed>  $data  The new workflow data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
        $this->updatedAt = new \DateTime;
    }

    /**
     * Merge new data with existing workflow data.
     *
     * @param  array<string, mixed>  $data  Data to merge
     */
    public function mergeData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
        $this->updatedAt = new \DateTime;
    }

    /**
     * Get the ID of the current step.
     *
     * @return string|null The current step ID, or null if no step is active
     */
    public function getCurrentStepId(): ?string
    {
        return $this->currentStepId;
    }

    /**
     * Set the current step ID.
     *
     * @param  string|null  $stepId  The step ID to set as current
     */
    public function setCurrentStepId(?string $stepId): void
    {
        $this->currentStepId = $stepId;
        $this->updatedAt = new \DateTime;
    }

    /**
     * Get the list of completed step IDs.
     *
     * @return array<int, string> List of completed step IDs
     */
    public function getCompletedSteps(): array
    {
        return $this->completedSteps;
    }

    /**
     * Mark a step as completed.
     *
     * @param  string  $stepId  The step ID to mark as completed
     */
    public function addCompletedStep(string $stepId): void
    {
        if (! in_array($stepId, $this->completedSteps)) {
            $this->completedSteps[] = $stepId;
            $this->updatedAt = new \DateTime;
        }
    }

    /**
     * Get the list of failed steps with error details.
     *
     * @return array<int, array{step_id: string, error: string, failed_at: string}> List of failed steps
     */
    public function getFailedSteps(): array
    {
        return $this->failedSteps;
    }

    /**
     * Record a step failure with error details.
     *
     * @param  string  $stepId  The step ID that failed
     * @param  string  $error  The error message or description
     */
    public function addFailedStep(string $stepId, string $error): void
    {
        $this->failedSteps[] = [
            'step_id' => $stepId,
            'error' => $error,
            'failed_at' => (new \DateTime)->format('c'),
        ];
        $this->updatedAt = new \DateTime;
    }

    /**
     * Get the overall workflow error message.
     *
     * @return string|null The error message, or null if no error
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Set the overall workflow error message.
     *
     * @param  string|null  $errorMessage  The error message
     */
    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
        $this->updatedAt = new \DateTime;
    }

    /**
     * Get the workflow instance creation timestamp.
     *
     * @return \DateTime The creation timestamp
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get the workflow instance last update timestamp.
     *
     * @return \DateTime The last update timestamp
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Check if a specific step has been completed.
     *
     * @param  string  $stepId  The step ID to check
     * @return bool True if the step is completed, false otherwise
     */
    public function isStepCompleted(string $stepId): bool
    {
        return in_array($stepId, $this->completedSteps);
    }

    /**
     * Get the next executable steps based on current state.
     *
     * @return array<int, Step> List of steps that can be executed next
     */
    public function getNextSteps(): array
    {
        return $this->definition->getNextSteps($this->currentStepId, $this->data);
    }

    /**
     * Check if a specific step can be executed now.
     *
     * @param  string  $stepId  The step ID to check
     * @return bool True if the step can be executed, false otherwise
     */
    public function canExecuteStep(string $stepId): bool
    {
        $step = $this->definition->getStep($stepId);
        if (! $step) {
            return false;
        }

        // Check if all prerequisites are met
        foreach ($step->getPrerequisites() as $prerequisite) {
            if (! $this->isStepCompleted($prerequisite)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert the workflow instance to an array representation.
     *
     * @return array<string, mixed> Array representation of the instance
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'definition_name' => $this->definition->getName(),
            'definition_version' => $this->definition->getVersion(),
            'state' => $this->state->value,
            'data' => $this->data,
            'current_step_id' => $this->currentStepId,
            'completed_steps' => $this->completedSteps,
            'failed_steps' => $this->failedSteps,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }

    /**
     * Create a workflow instance from an array representation.
     *
     * @param  array<string, mixed>  $data  Array data to restore from
     * @param  WorkflowDefinition  $definition  The workflow definition
     * @return static Restored workflow instance
     */
    public static function fromArray(array $data, WorkflowDefinition $definition): static
    {
        $instance = new self(
            id: $data['id'],
            definition: $definition,
            state: WorkflowState::from($data['state']),
            data: $data['data'] ?? [],
            createdAt: new \DateTime($data['created_at']),
            updatedAt: new \DateTime($data['updated_at'])
        );

        $instance->currentStepId = $data['current_step_id'] ?? null;
        $instance->completedSteps = $data['completed_steps'] ?? [];
        $instance->failedSteps = $data['failed_steps'] ?? [];
        $instance->errorMessage = $data['error_message'] ?? null;

        return $instance;
    }

    /**
     * Get workflow execution progress as a percentage.
     *
     * Calculates completion percentage based on the number of completed
     * steps versus total steps in the workflow definition.
     *
     * @return float Progress percentage (0.0 to 100.0)
     */
    public function getProgress(): float
    {
        $totalSteps = count($this->definition->getSteps());
        if ($totalSteps === 0) {
            return 100.0;
        }

        $completedSteps = count($this->completedSteps);

        return ($completedSteps / $totalSteps) * 100.0;
    }

    /**
     * Get the workflow execution context for the current step.
     *
     * Creates a WorkflowContext object that can be used for step execution,
     * containing the instance ID, current step ID, and workflow data.
     *
     * @return WorkflowContext The execution context
     */
    public function getContext(): WorkflowContext
    {
        return new WorkflowContext(
            $this->id,
            $this->currentStepId ?? '',
            $this->data
        );
    }

    /**
     * Get the workflow name from the definition.
     *
     * @return string The workflow name
     */
    public function getName(): string
    {
        return $this->definition->getName();
    }

    /**
     * Check if the workflow has failed.
     *
     * @return bool True if the workflow is in failed state
     */
    public function isFailed(): bool
    {
        return $this->state === WorkflowState::FAILED;
    }

    /**
     * Check if the workflow is completed.
     *
     * @return bool True if the workflow is in completed state
     */
    public function isCompleted(): bool
    {
        return $this->state === WorkflowState::COMPLETED;
    }

    /**
     * Check if the workflow is currently running.
     *
     * @return bool True if the workflow is in running state
     */
    public function isRunning(): bool
    {
        return $this->state === WorkflowState::RUNNING;
    }

    /**
     * Check if the workflow is pending execution.
     *
     * @return bool True if the workflow is in pending state
     */
    public function isPending(): bool
    {
        return $this->state === WorkflowState::PENDING;
    }

    /**
     * Get a summary of the workflow execution status.
     *
     * @return array<string, mixed> Status summary with key metrics
     */
    public function getStatusSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getName(),
            'state' => $this->state->value,
            'progress' => $this->getProgress(),
            'current_step' => $this->currentStepId,
            'completed_steps_count' => count($this->completedSteps),
            'failed_steps_count' => count($this->failedSteps),
            'total_steps' => count($this->definition->getSteps()),
            'has_errors' => ! empty($this->failedSteps) || ! empty($this->errorMessage),
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];
    }
}
