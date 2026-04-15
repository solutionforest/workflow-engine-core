<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;
use SolutionForest\WorkflowEngine\Contracts\Logger;
use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Events\StepCompletedEvent;
use SolutionForest\WorkflowEngine\Events\StepFailedEvent;
use SolutionForest\WorkflowEngine\Events\StepRetriedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowCompletedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowFailedEvent;
use SolutionForest\WorkflowEngine\Exceptions\ActionNotFoundException;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;
use SolutionForest\WorkflowEngine\Support\NullEventDispatcher;
use SolutionForest\WorkflowEngine\Support\NullLogger;
use SolutionForest\WorkflowEngine\Support\Timeout;

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
     * @param StateManager $stateManager The state manager for workflow persistence
     * @param EventDispatcher|null $eventDispatcher Optional event dispatcher for workflow events
     * @param Logger|null $logger Optional logger for workflow execution messages
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
     * @param WorkflowInstance $instance The workflow instance to execute
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
        } catch (\Throwable $e) {
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

            // Re-throw the original throwable to maintain the error context
            throw $e;
        }
    }

    /**
     * Process workflow execution by iterating over runnable steps until none remain.
     *
     * Each iteration asks the instance for its next runnable steps, executes them,
     * and loops again. The loop is bounded by the total number of steps in the
     * definition (x2 to allow for conditional skips) to defend against
     * pathological definitions that would otherwise loop forever.
     *
     * @param WorkflowInstance $instance The workflow instance to process
     *
     * @throws StepExecutionException If step execution fails
     * @throws ActionNotFoundException If required action classes are missing
     */
    private function processWorkflow(WorkflowInstance $instance): void
    {
        // If workflow is not running, transition it to running
        if (in_array($instance->getState(), [WorkflowState::PENDING, WorkflowState::PAUSED, WorkflowState::WAITING])) {
            $instance->setState(WorkflowState::RUNNING);
            $this->stateManager->save($instance);
        }

        $totalSteps = count($instance->getDefinition()->getSteps());
        // Upper bound on iterations: each step can be visited at most once as a
        // runnable step and once as a skip. The +1 guards the degenerate zero-step
        // workflow from tripping the safety check immediately.
        $maxIterations = max(1, $totalSteps * 2 + 1);
        $iterations = 0;

        while (true) {
            if (++$iterations > $maxIterations) {
                throw new \RuntimeException(
                    "Workflow '{$instance->getId()}' exceeded maximum execution iterations ({$maxIterations}); ".
                    'this usually indicates a cycle in the transition graph.'
                );
            }

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

            $progressed = false;

            foreach ($nextSteps as $step) {
                if ($instance->isStepCompleted($step->getId())) {
                    continue; // Skip already completed steps
                }

                if (! $instance->canExecuteStep($step->getId())) {
                    continue; // Skip steps that can't be executed yet
                }

                $this->executeStep($instance, $step);
                $progressed = true;
            }

            // If no steps made progress this iteration, the workflow is stuck
            // (e.g. all next steps were blocked on unmet prerequisites). Exit
            // the loop and let the next resume() reattempt.
            if (! $progressed) {
                return;
            }
        }
    }

    /**
     * Execute a single workflow step.
     *
     * Handles the complete lifecycle of step execution including action execution,
     * error handling, state updates, and event dispatching. Provides detailed
     * error context for debugging and monitoring.
     *
     * @param WorkflowInstance $instance The workflow instance
     * @param Step $step The step to execute
     *
     * @throws StepExecutionException If the step fails to execute
     * @throws ActionNotFoundException If the action class doesn't exist
     */
    private function executeStep(WorkflowInstance $instance, Step $step): void
    {
        // Evaluate step conditions. Steps whose conditions don't match the current
        // workflow data are skipped (marked completed without running the action)
        // so that downstream transitions continue to flow.
        if (! $step->canExecute($instance->getData())) {
            $this->logger->info('Skipping workflow step; conditions not met', [
                'workflow_id' => $instance->getId(),
                'step_id' => $step->getId(),
                'conditions' => $step->getConditions(),
            ]);

            $instance->setCurrentStepId($step->getId());
            $this->stateManager->markStepCompleted($instance, $step->getId());

            return;
        }

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
                $this->executeActionWithRetry($instance, $step);
            }

            // Mark step as completed
            $this->stateManager->markStepCompleted($instance, $step->getId());
            $this->eventDispatcher->dispatch(new StepCompletedEvent($instance, $step));

            $this->logger->info('Workflow step completed successfully', [
                'workflow_id' => $instance->getId(),
                'step_id' => $step->getId(),
            ]);
        } catch (\Throwable $e) {
            $context = new WorkflowContext(
                workflowId: $instance->getId(),
                stepId: $step->getId(),
                data: $instance->getData(),
                config: $step->getConfig(),
                instance: $instance
            );

            // Wrap non-typed throwables in a StepExecutionException while preserving
            // ActionNotFoundException (and other domain exceptions) as-is.
            $stepException = $e instanceof ActionNotFoundException
                ? $e
                : StepExecutionException::fromException($e, $step, $context);

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
     * Execute a step's action with retry logic.
     *
     * @param WorkflowInstance $instance The workflow instance
     * @param Step $step The step to execute
     *
     * @throws ActionNotFoundException If the action class doesn't exist
     * @throws StepExecutionException If all retry attempts are exhausted
     */
    /**
     * Maximum backoff sleep between retries, in microseconds. Caps the
     * exponential growth so a misconfigured step cannot block a worker for
     * minutes.
     */
    private const MAX_BACKOFF_MICROSECONDS = 2_000_000; // 2 seconds

    private function executeActionWithRetry(WorkflowInstance $instance, Step $step): void
    {
        $maxAttempts = $step->getRetryAttempts() + 1; // +1 for initial attempt

        if ($maxAttempts <= 1) {
            // No retries configured, execute directly
            $this->executeAction($instance, $step);

            return;
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->executeAction($instance, $step);

                return; // Success — exit retry loop
            } catch (\Throwable $e) {
                if ($attempt === $maxAttempts) {
                    $this->logger->error('Step failed after all retry attempts', [
                        'workflow_id' => $instance->getId(),
                        'step_id' => $step->getId(),
                        'attempts' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e; // Final attempt failed — propagate
                }

                $backoffMicroseconds = $this->calculateBackoff($attempt);

                $this->logger->warning('Step failed, retrying', [
                    'workflow_id' => $instance->getId(),
                    'step_id' => $step->getId(),
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'backoff_ms' => (int) ($backoffMicroseconds / 1000),
                    'error' => $e->getMessage(),
                ]);

                $this->eventDispatcher->dispatch(new StepRetriedEvent(
                    $instance,
                    $step,
                    $attempt,
                    $maxAttempts,
                    $e
                ));

                usleep($backoffMicroseconds);
            }
        }
    }

    /**
     * Calculate exponential backoff delay between retry attempts.
     *
     * Doubles each attempt starting at 100ms, capped at MAX_BACKOFF_MICROSECONDS
     * to prevent runaway worker blocking.
     *
     * @param int $attempt 1-based attempt number
     * @return int Delay in microseconds
     */
    private function calculateBackoff(int $attempt): int
    {
        $base = 100_000; // 100ms
        $delay = (int) ($base * (2 ** ($attempt - 1)));

        return min($delay, self::MAX_BACKOFF_MICROSECONDS);
    }

    /**
     * Execute a callback with a timeout constraint.
     *
     * Uses pcntl_alarm when the pcntl extension is loaded. pcntl is generally
     * only available under the CLI SAPI, so for web/FPM contexts this method
     * logs a warning and runs the callback unbounded — long-running workflow
     * steps should be dispatched via a queue worker instead.
     *
     * @param callable $callback The callback to execute
     * @param int $timeoutSeconds Maximum execution time in seconds
     * @return mixed The callback's return value
     *
     * @throws \RuntimeException If the timeout is exceeded while running under pcntl
     */
    private function executeWithTimeout(callable $callback, int $timeoutSeconds): mixed
    {
        if (! function_exists('pcntl_alarm') || ! function_exists('pcntl_signal') || ! function_exists('pcntl_async_signals')) {
            $this->logger->warning('pcntl extension not available, timeout not enforced', [
                'timeout_seconds' => $timeoutSeconds,
                'hint' => 'Execute workflows via CLI or queue workers to enforce step timeouts.',
            ]);

            return $callback();
        }

        // Ensure the signal handler runs at the VM tick rather than waiting for
        // an explicit pcntl_signal_dispatch() call. Without this, SIGALRM can
        // be delivered unpredictably or not at all.
        $previousAsync = pcntl_async_signals(true);
        $previousHandler = pcntl_signal_get_handler(SIGALRM);

        pcntl_signal(SIGALRM, function () use ($timeoutSeconds): never {
            throw new \RuntimeException("Step execution timed out after {$timeoutSeconds} seconds");
        });

        pcntl_alarm($timeoutSeconds);

        try {
            return $callback();
        } finally {
            // Always clear the alarm and restore the previous signal handler,
            // even if the callback threw or the alarm fired.
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, $previousHandler ?: SIG_DFL);
            pcntl_async_signals($previousAsync);
        }
    }

    /**
     * Execute the action associated with a workflow step.
     *
     * Handles action instantiation, validation, execution, and result processing.
     * Provides comprehensive error handling for missing classes, interface compliance,
     * and execution failures.
     *
     * @param WorkflowInstance $instance The workflow instance
     * @param Step $step The step containing the action to execute
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

        $timeoutValue = $step->getTimeout();
        if ($timeoutValue !== null) {
            $timeoutSeconds = Timeout::toSeconds($timeoutValue);
            $result = $this->executeWithTimeout(
                fn () => $action->execute($context),
                $timeoutSeconds
            );
        } else {
            $result = $action->execute($context);
        }

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
