<?php

namespace SolutionForest\WorkflowEngine\Core;

use Exception;
use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;
use SolutionForest\WorkflowEngine\Contracts\Logger;
use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Events\StepCompletedEvent;
use SolutionForest\WorkflowEngine\Events\StepFailedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowCompletedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowFailedEvent;
use SolutionForest\WorkflowEngine\Exceptions\ActionNotFoundException;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;
use SolutionForest\WorkflowEngine\Support\NullEventDispatcher;
use SolutionForest\WorkflowEngine\Support\NullLogger;

/**
 * Workflow executor responsible for running workflow steps and managing execution flow.
 *
 * The Executor is the core component that handles the actual execution of workflow steps,
 * manages state transitions, handles errors, and dispatches events during workflow execution.
 * It ensures proper step sequencing, error handling, and state persistence.
 *
 *
 * @example Basic workflow execution
 * ```php
 * $executor = new Executor($stateManager, $eventDispatcher);
 *
 * // Execute a workflow instance
 * $executor->execute($workflowInstance);
 *
 * // The executor will:
 * // 1. Process all pending steps
 * // 2. Execute actions in sequence
 * // 3. Handle errors and retries
 * // 4. Update workflow state
 * // 5. Dispatch appropriate events
 * ```
 * @example Error handling during execution
 * ```php
 * try {
 *     $executor->execute($instance);
 * } catch (StepExecutionException $e) {
 *     // Handle step-specific errors
 *     echo "Step failed: " . $e->getStep()->getId();
 *     echo "Context: " . json_encode($e->getContext());
 * } catch (ActionNotFoundException $e) {
 *     // Handle missing action classes
 *     echo "Missing action: " . $e->getActionClass();
 * }
 * ```
 */
class Executor
{
    /**
     * State manager for persisting workflow state changes.
     */
    private readonly StateManager $stateManager;

    /**
     * Event dispatcher for workflow and step events.
     */
    private readonly EventDispatcher $eventDispatcher;

    /**
     * Logger for workflow execution messages.
     */
    private readonly Logger $logger;

    /**
     * Create a new workflow executor.
     *
     * @param  StateManager  $stateManager  The state manager for workflow persistence
     * @param  EventDispatcher|null  $eventDispatcher  Optional event dispatcher for workflow events
     * @param  Logger|null  $logger  Optional logger for workflow execution messages
     *
     * @example Basic setup
     * ```php
     * $executor = new Executor(
     *     new StateManager($storageAdapter),
     *     new CustomEventDispatcher(),
     *     new CustomLogger()
     * );
     * ```
     */
    public function __construct(
        StateManager $stateManager,
        ?EventDispatcher $eventDispatcher = null,
        ?Logger $logger = null
    ) {
        $this->stateManager = $stateManager;
        $this->eventDispatcher = $eventDispatcher ?? new NullEventDispatcher;
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * Execute a workflow instance by processing all pending steps.
     *
     * This method orchestrates the complete workflow execution, handling state transitions,
     * step execution, error handling, and event dispatching. It processes steps in sequence
     * and manages the workflow lifecycle from start to completion.
     *
     * @param  WorkflowInstance  $instance  The workflow instance to execute
     *
     * @throws StepExecutionException If a step fails during execution
     * @throws ActionNotFoundException If a required action class is not found
     *
     * @example Executing a workflow
     * ```php
     * $instance = $stateManager->load('workflow-123');
     * $executor->execute($instance);
     *
     * // The instance state will be updated automatically
     * echo $instance->getState()->value; // 'completed' or 'failed'
     * ```
     */
    public function execute(WorkflowInstance $instance): void
    {
        try {
            $this->processWorkflow($instance);
        } catch (Exception $e) {
            $this->logger->error('Workflow execution failed', [
                'workflow_id' => $instance->getId(),
                'workflow_name' => $instance->getDefinition()->getName(),
                'current_step' => $instance->getCurrentStepId(),
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->stateManager->setError($instance, $e->getMessage());
            $this->eventDispatcher->dispatch(new WorkflowFailedEvent($instance, $e));

            // Re-throw the original exception to maintain the error context
            throw $e;
        }
    }

    /**
     * Process workflow execution by managing state transitions and step execution.
     *
     * This private method handles the core workflow processing logic, including
     * state management, step scheduling, and completion detection.
     *
     * @param  WorkflowInstance  $instance  The workflow instance to process
     *
     * @throws StepExecutionException If step execution fails
     * @throws ActionNotFoundException If required action classes are missing
     */
    private function processWorkflow(WorkflowInstance $instance): void
    {
        // If workflow is not running, start it
        if ($instance->getState() === WorkflowState::PENDING) {
            $instance->setState(WorkflowState::RUNNING);
            $this->stateManager->save($instance);
        }

        // Get next steps to execute
        $nextSteps = $instance->getNextSteps();

        if (empty($nextSteps)) {
            // Workflow completed successfully
            $instance->setState(WorkflowState::COMPLETED);
            $this->stateManager->save($instance);
            $this->eventDispatcher->dispatch(new WorkflowCompletedEvent($instance));

            $this->logger->info('Workflow completed successfully', [
                'workflow_id' => $instance->getId(),
                'workflow_name' => $instance->getDefinition()->getName(),
                'completed_steps' => count($instance->getCompletedSteps()),
                'execution_time' => abs($instance->getUpdatedAt()->getTimestamp() - $instance->getCreatedAt()->getTimestamp()).'s',
            ]);

            return;
        }

        // Execute each next step
        foreach ($nextSteps as $step) {
            if ($instance->isStepCompleted($step->getId())) {
                continue; // Skip already completed steps
            }

            if (! $instance->canExecuteStep($step->getId())) {
                continue; // Skip steps that can't be executed yet
            }

            $this->executeStep($instance, $step);
        }
    }

    /**
     * Execute a single workflow step.
     *
     * Handles the complete lifecycle of step execution including action execution,
     * error handling, state updates, and event dispatching. Provides detailed
     * error context for debugging and monitoring.
     *
     * @param  WorkflowInstance  $instance  The workflow instance
     * @param  Step  $step  The step to execute
     *
     * @throws StepExecutionException If the step fails to execute
     * @throws ActionNotFoundException If the action class doesn't exist
     */
    private function executeStep(WorkflowInstance $instance, Step $step): void
    {
        $this->logger->info('Executing workflow step', [
            'workflow_id' => $instance->getId(),
            'workflow_name' => $instance->getDefinition()->getName(),
            'step_id' => $step->getId(),
            'action_class' => $step->getActionClass(),
            'step_config' => $step->getConfig(),
        ]);

        $instance->setCurrentStepId($step->getId());
        $this->stateManager->save($instance);

        try {
            if ($step->hasAction()) {
                $this->executeAction($instance, $step);
            }

            // Mark step as completed
            $this->stateManager->markStepCompleted($instance, $step->getId());
            $this->eventDispatcher->dispatch(new StepCompletedEvent($instance, $step));

            $this->logger->info('Workflow step completed successfully', [
                'workflow_id' => $instance->getId(),
                'step_id' => $step->getId(),
                'step_duration' => 'calculated_in_future_version', // TODO: Add timing
            ]);

            // Continue execution recursively
            $this->processWorkflow($instance);

        } catch (Exception $e) {
            $context = new WorkflowContext(
                workflowId: $instance->getId(),
                stepId: $step->getId(),
                data: $instance->getData(),
                config: $step->getConfig(),
                instance: $instance
            );

            // Create detailed step execution exception
            $stepException = match (true) {
                $e instanceof ActionNotFoundException => $e,
                str_contains($e->getMessage(), 'does not exist') => ActionNotFoundException::classNotFound($step->getActionClass(), $step, $context),
                str_contains($e->getMessage(), 'must implement') => ActionNotFoundException::invalidInterface($step->getActionClass(), $step, $context),
                default => StepExecutionException::fromException($e, $step, $context)
            };

            $this->logger->error('Workflow step execution failed', [
                'workflow_id' => $instance->getId(),
                'step_id' => $step->getId(),
                'action_class' => $step->getActionClass(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'step_config' => $step->getConfig(),
                'context_data' => $instance->getData(),
            ]);

            $this->stateManager->markStepFailed($instance, $step->getId(), $stepException->getMessage());
            $this->eventDispatcher->dispatch(new StepFailedEvent($instance, $step, $stepException));

            // Propagate the enhanced exception
            throw $stepException;
        }
    }

    /**
     * Execute the action associated with a workflow step.
     *
     * Handles action instantiation, validation, execution, and result processing.
     * Provides comprehensive error handling for missing classes, interface compliance,
     * and execution failures.
     *
     * @param  WorkflowInstance  $instance  The workflow instance
     * @param  Step  $step  The step containing the action to execute
     *
     * @throws ActionNotFoundException If the action class doesn't exist or implement the interface
     * @throws StepExecutionException If action execution fails
     */
    private function executeAction(WorkflowInstance $instance, Step $step): void
    {
        $actionClass = $step->getActionClass();

        if (! class_exists($actionClass)) {
            $context = new WorkflowContext(
                workflowId: $instance->getId(),
                stepId: $step->getId(),
                data: $instance->getData(),
                config: $step->getConfig(),
                instance: $instance
            );

            throw ActionNotFoundException::classNotFound($actionClass, $step, $context);
        }

        $action = new $actionClass($step->getConfig(), $this->logger);

        if (! $action instanceof WorkflowAction) {
            $context = new WorkflowContext(
                workflowId: $instance->getId(),
                stepId: $step->getId(),
                data: $instance->getData(),
                config: $step->getConfig(),
                instance: $instance
            );

            throw ActionNotFoundException::invalidInterface($actionClass, $step, $context);
        }

        $context = new WorkflowContext(
            workflowId: $instance->getId(),
            stepId: $step->getId(),
            data: $instance->getData(),
            config: $step->getConfig(),
            instance: $instance
        );

        $result = $action->execute($context);

        if ($result->isSuccess()) {
            // Merge any output data from the action
            if ($result->hasData()) {
                $instance->mergeData($result->getData());
                $this->stateManager->save($instance);
            }
        } else {
            throw StepExecutionException::actionFailed(
                $result->getErrorMessage() ?? 'Action execution failed without specific error message',
                $step,
                $context
            );
        }
    }
}
