<?php

namespace SolutionForest\WorkflowEngine\Exceptions;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Core\WorkflowState;

/**
 * Thrown when attempting to perform an invalid workflow state transition.
 *
 * This exception helps developers understand workflow lifecycle rules
 * and provides clear guidance on valid state transitions.
 */
final class InvalidWorkflowStateException extends WorkflowException
{
    /**
     * Create a new invalid workflow state exception.
     *
     * @param  string  $message  The error message
     * @param  WorkflowState  $currentState  The current workflow state
     * @param  WorkflowState  $attemptedState  The state that was attempted
     * @param  string  $instanceId  The workflow instance ID
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message,
        protected readonly WorkflowState $currentState,
        protected readonly WorkflowState $attemptedState,
        protected readonly string $instanceId,
        ?\Throwable $previous = null
    ) {
        $context = [
            'instance_id' => $instanceId,
            'current_state' => $currentState->value,
            'attempted_state' => $attemptedState->value,
            'current_state_label' => $currentState->label(),
            'attempted_state_label' => $attemptedState->label(),
            'valid_transitions' => $this->getValidTransitions($currentState),
        ];

        parent::__construct($message, $context, 0, $previous);
    }

    /**
     * Get the current workflow state.
     *
     * @return WorkflowState The current state
     */
    public function getCurrentState(): WorkflowState
    {
        return $this->currentState;
    }

    /**
     * Get the attempted workflow state.
     *
     * @return WorkflowState The attempted state
     */
    public function getAttemptedState(): WorkflowState
    {
        return $this->attemptedState;
    }

    /**
     * Get the workflow instance ID.
     *
     * @return string The instance ID
     */
    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    /**
     * Get valid state transitions from the current state.
     *
     * @param  WorkflowState  $state  The state to get transitions for
     * @return string[] Array of valid state values
     */
    private function getValidTransitions(WorkflowState $state): array
    {
        $validTransitions = [];

        foreach (WorkflowState::cases() as $targetState) {
            if ($state->canTransitionTo($targetState)) {
                $validTransitions[] = $targetState->value;
            }
        }

        return $validTransitions;
    }

    /**
     * {@inheritdoc}
     */
    public function getUserMessage(): string
    {
        $currentLabel = $this->currentState->label();
        $attemptedLabel = $this->attemptedState->label();

        return "Cannot transition workflow from '{$currentLabel}' state to '{$attemptedLabel}' state. ".
               'This transition is not allowed by the workflow lifecycle rules.';
    }

    /**
     * {@inheritdoc}
     */
    public function getSuggestions(): array
    {
        $validTransitions = $this->getContextValue('valid_transitions', []);
        $suggestions = [];

        if (! empty($validTransitions)) {
            $validStates = implode(', ', $validTransitions);
            $suggestions[] = "Valid transitions from '{$this->currentState->value}' are: {$validStates}";
        } else {
            $suggestions[] = "No valid transitions are available from the current state '{$this->currentState->value}'";
        }

        // State-specific suggestions
        switch ($this->currentState) {
            case WorkflowState::COMPLETED:
                $suggestions[] = 'Completed workflows cannot be modified - create a new workflow instance if needed';
                $suggestions[] = 'Consider using workflow versioning for updates to completed workflows';
                break;

            case WorkflowState::FAILED:
                $suggestions[] = 'Failed workflows can only be retried or cancelled';
                $suggestions[] = 'Review and fix the underlying issue before retrying';
                break;

            case WorkflowState::CANCELLED:
                $suggestions[] = 'Cancelled workflows cannot be resumed - create a new instance if needed';
                break;

            case WorkflowState::PENDING:
                $suggestions[] = 'Start the workflow execution to transition from pending state';
                break;

            case WorkflowState::RUNNING:
                $suggestions[] = 'Allow the workflow to complete naturally, or cancel if needed';
                break;
        }

        return $suggestions;
    }

    /**
     * Create an exception for attempting to resume a completed workflow.
     *
     * @param  string  $instanceId  The workflow instance ID
     */
    public static function cannotResumeCompleted(string $instanceId): static
    {
        return new self(
            "Cannot resume workflow '{$instanceId}' because it is already completed",
            WorkflowState::COMPLETED,
            WorkflowState::RUNNING,
            $instanceId
        );
    }

    /**
     * Create an exception for attempting to cancel a failed workflow.
     *
     * @param  string  $instanceId  The workflow instance ID
     */
    public static function cannotCancelFailed(string $instanceId): static
    {
        return new self(
            "Cannot cancel workflow '{$instanceId}' because it has already failed",
            WorkflowState::FAILED,
            WorkflowState::CANCELLED,
            $instanceId
        );
    }

    /**
     * Create an exception for attempting to start an already running workflow.
     *
     * @param  string  $instanceId  The workflow instance ID
     */
    public static function alreadyRunning(string $instanceId): static
    {
        return new self(
            "Cannot start workflow '{$instanceId}' because it is already running",
            WorkflowState::RUNNING,
            WorkflowState::RUNNING,
            $instanceId
        );
    }

    /**
     * Create an exception from a workflow instance with state transition details.
     *
     * @param  WorkflowInstance  $instance  The workflow instance
     * @param  WorkflowState  $attemptedState  The attempted state
     * @param  string  $operation  The operation that was attempted
     */
    public static function fromInstanceTransition(
        WorkflowInstance $instance,
        WorkflowState $attemptedState,
        string $operation
    ): static {
        $message = "Cannot {$operation} workflow '{$instance->getId()}' - invalid state transition";

        return new self(
            $message,
            $instance->getState(),
            $attemptedState,
            $instance->getId()
        );
    }
}
