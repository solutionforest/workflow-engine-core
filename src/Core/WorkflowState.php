<?php

namespace SolutionForest\WorkflowEngine\Core;

/**
 * Represents the execution state of a workflow instance.
 *
 * WorkflowState defines all possible states that a workflow can be in during
 * its lifecycle, from initial creation to final completion or failure.
 * Each state represents a specific execution phase and determines what
 * operations are valid and what transitions are allowed.
 *
 * ## State Categories
 *
 * ### Active States (workflow can progress)
 * - **PENDING**: Workflow created but not yet started
 * - **RUNNING**: Workflow is actively executing steps
 * - **WAITING**: Workflow is waiting for external input or conditions
 * - **PAUSED**: Workflow execution temporarily suspended
 *
 * ### Terminal States (workflow execution finished)
 * - **COMPLETED**: Workflow finished successfully
 * - **FAILED**: Workflow terminated due to errors
 * - **CANCELLED**: Workflow terminated by user action
 *
 * ## State Transitions
 *
 * Valid state transitions follow these rules:
 * - `PENDING` → `RUNNING`, `CANCELLED`
 * - `RUNNING` → `WAITING`, `PAUSED`, `COMPLETED`, `FAILED`, `CANCELLED`
 * - `WAITING` → `RUNNING`, `FAILED`, `CANCELLED`
 * - `PAUSED` → `RUNNING`, `CANCELLED`
 * - Terminal states cannot transition to other states
 *
 * ## Usage Examples
 *
 * ### State Checking
 * ```php
 * $instance = new WorkflowInstance(...);
 *
 * if ($instance->getState()->isActive()) {
 *     // Workflow can still execute
 *     $nextSteps = $instance->getNextSteps();
 * }
 *
 * if ($instance->getState()->isFinished()) {
 *     // Workflow execution completed
 *     $result = $instance->getData();
 * }
 * ```
 *
 * ### State Transitions
 * ```php
 * $currentState = WorkflowState::PENDING;
 *
 * if ($currentState->canTransitionTo(WorkflowState::RUNNING)) {
 *     $instance->setState(WorkflowState::RUNNING);
 * }
 * ```
 *
 * ### UI Representation
 * ```php
 * $state = $instance->getState();
 *
 * echo "Status: {$state->icon()} {$state->label()}";
 * echo "<span style='color: {$state->color()}'>{$state->label()}</span>";
 * ```
 *
 * @see WorkflowInstance For workflow state management
 * @see WorkflowEngine For state transitions during execution
 */
enum WorkflowState: string
{
    /** Workflow created but not yet started execution */
    case PENDING = 'pending';

    /** Workflow is actively executing steps */
    case RUNNING = 'running';

    /** Workflow is waiting for external input or conditions */
    case WAITING = 'waiting';

    /** Workflow execution temporarily suspended by user */
    case PAUSED = 'paused';

    /** Workflow finished successfully with all steps completed */
    case COMPLETED = 'completed';

    /** Workflow terminated due to step failures or errors */
    case FAILED = 'failed';

    /** Workflow terminated by user action before completion */
    case CANCELLED = 'cancelled';

    /**
     * Check if the workflow is in an active state.
     *
     * Active states indicate that the workflow can potentially continue
     * execution and progress through its steps. This includes pending
     * workflows that haven't started yet.
     *
     * @return bool True if the workflow can potentially execute or continue
     *
     * @example Active state checking
     * ```php
     * $state = WorkflowState::RUNNING;
     *
     * if ($state->isActive()) {
     *     // Can execute next steps
     *     $executor->continueExecution($instance);
     * }
     * ```
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::RUNNING,
            self::WAITING,
            self::PAUSED,
        ]);
    }

    /**
     * Check if the workflow is in a finished state.
     *
     * Finished states indicate that the workflow execution has terminated
     * and cannot continue. No further steps will be executed.
     *
     * @return bool True if the workflow execution has finished
     *
     * @example Finished state checking
     * ```php
     * $state = $instance->getState();
     *
     * if ($state->isFinished()) {
     *     // Archive or cleanup workflow
     *     $archiver->archive($instance);
     * }
     * ```
     */
    public function isFinished(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::FAILED,
            self::CANCELLED,
        ]);
    }

    /**
     * Get color code for UI representation.
     *
     * Returns a semantic color name that can be used in web interfaces
     * to visually represent the workflow state.
     *
     * @return string Color name (gray, blue, yellow, orange, green, red, purple)
     *
     * @example UI color usage
     * ```php
     * $state = $instance->getState();
     * $color = $state->color();
     *
     * echo "<span class='text-{$color}-600'>{$state->label()}</span>";
     * ```
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'gray',      // Neutral, waiting to start
            self::RUNNING => 'blue',      // Active, in progress
            self::WAITING => 'yellow',    // Attention, waiting for input
            self::PAUSED => 'orange',     // Warning, temporarily stopped
            self::COMPLETED => 'green',   // Success, finished successfully
            self::FAILED => 'red',        // Error, terminated with failure
            self::CANCELLED => 'purple',  // Info, terminated by user
        };
    }

    /**
     * Get icon emoji for UI representation.
     *
     * Returns an emoji icon that visually represents the workflow state
     * for use in user interfaces, notifications, or logs.
     *
     * @return string Emoji icon representing the state
     *
     * @example Icon usage in notifications
     * ```php
     * $state = $instance->getState();
     *
     * $message = "Workflow {$instance->getName()} is {$state->icon()} {$state->label()}";
     * Notification::send($user, $message);
     * ```
     */
    public function icon(): string
    {
        return match ($this) {
            self::PENDING => '⏳',     // Hourglass - waiting to start
            self::RUNNING => '▶️',     // Play button - actively running
            self::WAITING => '⏸️',     // Pause button - waiting for input
            self::PAUSED => '⏸️',     // Pause button - user paused
            self::COMPLETED => '✅',   // Check mark - successfully completed
            self::FAILED => '❌',     // X mark - failed with errors
            self::CANCELLED => '🚫',  // Prohibited sign - cancelled by user
        };
    }

    /**
     * Check if this state can transition to another state.
     *
     * Validates whether a state transition is allowed according to the
     * workflow state machine rules. This helps prevent invalid state
     * changes and ensures workflow integrity.
     *
     * @param  self  $state  The target state to transition to
     * @return bool True if the transition is valid, false otherwise
     *
     * @example State transition validation
     * ```php
     * $currentState = WorkflowState::PENDING;
     * $targetState = WorkflowState::RUNNING;
     *
     * if ($currentState->canTransitionTo($targetState)) {
     *     $instance->setState($targetState);
     * } else {
     *     throw new InvalidStateTransitionException($currentState, $targetState);
     * }
     * ```
     * @example Checking multiple transitions
     * ```php
     * $currentState = WorkflowState::RUNNING;
     *
     * $validTransitions = [];
     * foreach (WorkflowState::cases() as $state) {
     *     if ($currentState->canTransitionTo($state)) {
     *         $validTransitions[] = $state;
     *     }
     * }
     * ```
     */
    public function canTransitionTo(self $state): bool
    {
        return match ($this) {
            // From PENDING: can start running or be cancelled
            self::PENDING => in_array($state, [self::RUNNING, self::CANCELLED]),

            // From RUNNING: can wait, pause, complete, fail, or be cancelled
            self::RUNNING => in_array($state, [
                self::WAITING,
                self::PAUSED,
                self::COMPLETED,
                self::FAILED,
                self::CANCELLED,
            ]),

            // From WAITING: can resume running, fail, or be cancelled
            self::WAITING => in_array($state, [self::RUNNING, self::FAILED, self::CANCELLED]),

            // From PAUSED: can resume running or be cancelled
            self::PAUSED => in_array($state, [self::RUNNING, self::CANCELLED]),

            // Terminal states cannot transition to other states
            default => false,
        };
    }

    /**
     * Get human-readable label for the state.
     *
     * Returns a capitalized, user-friendly name for the state that can
     * be displayed in user interfaces, reports, or logs.
     *
     * @return string Human-readable state label
     *
     * @example Display in status page
     * ```php
     * $instances = WorkflowInstance::all();
     *
     * foreach ($instances as $instance) {
     *     echo "Workflow: {$instance->getName()} - Status: {$instance->getState()->label()}\n";
     * }
     * ```
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::RUNNING => 'Running',
            self::WAITING => 'Waiting',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get a detailed description of what this state means.
     *
     * Returns a comprehensive explanation of the state, useful for
     * tooltips, help documentation, or detailed status reports.
     *
     * @return string Detailed state description
     *
     * @example Tooltip or help text
     * ```php
     * $state = $instance->getState();
     *
     * echo "<span title='{$state->description()}'>{$state->label()}</span>";
     * ```
     */
    public function description(): string
    {
        return match ($this) {
            self::PENDING => 'The workflow has been created but execution has not yet started. It is waiting to be triggered or scheduled.',
            self::RUNNING => 'The workflow is actively executing steps. One or more actions are currently being processed.',
            self::WAITING => 'The workflow is paused waiting for external input, conditions to be met, or scheduled delays to complete.',
            self::PAUSED => 'The workflow execution has been temporarily suspended by a user and can be resumed at any time.',
            self::COMPLETED => 'The workflow has finished successfully. All steps have been executed and the final state has been reached.',
            self::FAILED => 'The workflow execution has terminated due to errors, step failures, or unrecoverable conditions.',
            self::CANCELLED => 'The workflow execution was terminated by user action before it could complete naturally.',
        };
    }

    /**
     * Check if this is a successful terminal state.
     *
     * @return bool True if the workflow completed successfully
     */
    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if this is an error terminal state.
     *
     * @return bool True if the workflow ended with an error
     */
    public function isError(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Get all possible states that can be transitioned to from this state.
     *
     * @return array<self> Array of valid target states
     *
     * @example Get available transitions
     * ```php
     * $currentState = WorkflowState::RUNNING;
     * $availableStates = $currentState->getValidTransitions();
     *
     * // Show available actions to user
     * foreach ($availableStates as $state) {
     *     echo "Can transition to: {$state->label()}\n";
     * }
     * ```
     */
    public function getValidTransitions(): array
    {
        $validStates = [];

        foreach (self::cases() as $state) {
            if ($this->canTransitionTo($state)) {
                $validStates[] = $state;
            }
        }

        return $validStates;
    }
}
