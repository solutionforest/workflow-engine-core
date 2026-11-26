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
use SolutionForest\WorkflowEngine\Events\WorkflowResumedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowSuspendedEvent;
use SolutionForest\WorkflowEngine\Exceptions\ActionNotFoundException;
use SolutionForest\WorkflowEngine\Exceptions\ConcurrentModificationException;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;
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
        ?Logger $logger = null,
        private readonly bool $signalTimeouts = false,
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
        } catch (ConcurrentModificationException $e) {
            // Someone else advanced the instance; nothing failed. The caller reloads and retries.
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Workflow execution failed', [
                'workflow_id' => $instance->getId(),
                'workflow_name' => $instance->getDefinition()->getName(),
                'current_step' => $instance->getCurrentStepId(),
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Recording the failure must never replace the failure itself. If
            // the instance is in a state that cannot legally move to FAILED
            // (or storage rejects the write), swallow that secondary error and
            // log it — the caller needs the original cause, not the bookkeeping
            // exception that happened while reacting to it.
            try {
                $this->stateManager->setError($instance, $e->getMessage());
            } catch (\Throwable $bookkeepingError) {
                $this->logger->error('Failed to record workflow failure state', [
                    'workflow_id' => $instance->getId(),
                    'state' => $instance->getState()->value,
                    'original_error' => $e->getMessage(),
                    'bookkeeping_error' => $bookkeepingError->getMessage(),
                ]);
            }

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
    /**
     * Finish the suspended step of $instance because $signal arrived (or Wait::TIMER, when its
     * time passed), then carry on with the next steps.
     *
     * What resumed it is kept in the workflow data under `steps.<step id>`: `resumed_by`
     * (the signal name), `payload` and `resumed_at`, so later conditions can branch on it,
     * for example `steps.approve.payload.decision === "approved"`.
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidWorkflowStateException If the instance is not waiting for $signal
     */
    public function resumeWait(WorkflowInstance $instance, string $signal, array $payload = []): void
    {
        $wait = $instance->getWait();
        if ($wait === null || $instance->getState() !== WorkflowState::WAITING) {
            throw new InvalidWorkflowStateException(
                "Workflow '{$instance->getId()}' is not waiting for anything",
                $instance->getState(),
                WorkflowState::RUNNING,
                $instance->getId()
            );
        }
        if ($signal !== $wait->for && $signal !== Wait::TIMER) {
            throw new InvalidWorkflowStateException(
                "Workflow '{$instance->getId()}' waits for '{$wait->for}' in step '{$wait->stepId}', not for '{$signal}'",
                $instance->getState(),
                WorkflowState::RUNNING,
                $instance->getId()
            );
        }
        if ($signal === Wait::TIMER && $wait->resumeAt === null) {
            throw new InvalidWorkflowStateException(
                "Workflow '{$instance->getId()}' waits for '{$wait->for}' with no time limit",
                $instance->getState(),
                WorkflowState::RUNNING,
                $instance->getId()
            );
        }
        $step = $instance->getDefinition()->getStep($wait->stepId)
            ?? throw new InvalidWorkflowStateException(
                "Workflow '{$instance->getId()}' waits in step '{$wait->stepId}', which its definition does not have",
                $instance->getState(),
                WorkflowState::RUNNING,
                $instance->getId()
            );

        $data = $instance->getData();
        $steps = is_array($data['steps'] ?? null) ? $data['steps'] : [];
        $steps[$wait->stepId] = [
            'resumed_by' => $signal,
            'payload' => $payload,
            'resumed_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
        ];
        $data['steps'] = $steps;
        $instance->setData($data);
        $instance->setWait(null);
        $this->stateManager->markStepCompleted($instance, $wait->stepId);

        $this->eventDispatcher->dispatch(new WorkflowResumedEvent($instance, $step, $wait, $signal, $payload));
        $this->eventDispatcher->dispatch(new StepCompletedEvent($instance, $step));
        $this->logger->info('Workflow resumed', [
            'workflow_id' => $instance->getId(),
            'step_id' => $wait->stepId,
            'signal' => $signal,
        ]);

        $this->execute($instance);
    }

    private function processWorkflow(WorkflowInstance $instance): void
    {
        // A suspended step finishes only through resumeWait(): running the workflow again
        // would run that step's action a second time.
        if ($instance->isSuspended()) {
            throw new InvalidWorkflowStateException(
                "Workflow '{$instance->getId()}' waits for '{$instance->getWait()?->for}'; deliver it with WorkflowEngine::signal()",
                $instance->getState(),
                WorkflowState::RUNNING,
                $instance->getId()
            );
        }

        // If workflow is not running, transition it to running. FAILED is
        // included so that resuming a failed workflow actually retries it
        // instead of executing steps while still marked failed (which would
        // then trip the state machine on the final FAILED -> COMPLETED hop).
        if (in_array($instance->getState(), [
            WorkflowState::PENDING,
            WorkflowState::PAUSED,
            WorkflowState::WAITING,
            WorkflowState::FAILED,
        ])) {
            $instance->setState(WorkflowState::RUNNING);
            // Clear the previous failure so a recovered run doesn't keep
            // reporting a stale error message.
            $instance->setErrorMessage(null);
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

                if ($this->executeStep($instance, $step)) {
                    return; // the step suspended the workflow
                }
                $progressed = true;
            }

            // No step made progress: every candidate was blocked on an unmet
            // prerequisite. Park the workflow in WAITING rather than leaving it
            // in RUNNING, where a permanently stuck instance is indistinguishable
            // from one that is still executing.
            if (! $progressed) {
                $blocked = array_map(static fn (Step $s): string => $s->getId(), $nextSteps);

                $this->logger->warning('Workflow is waiting on unmet prerequisites', [
                    'workflow_id' => $instance->getId(),
                    'blocked_steps' => $blocked,
                ]);

                if ($instance->getState()->canTransitionTo(WorkflowState::WAITING)) {
                    $instance->setState(WorkflowState::WAITING);
                    $this->stateManager->save($instance);
                }

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
    private function executeStep(WorkflowInstance $instance, Step $step): bool
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

            return false;
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
            $suspension = $step->hasAction() ? $this->executeActionWithRetry($instance, $step) : null;
            if ($suspension !== null) {
                $wait = new Wait(
                    $step->getId(),
                    $suspension->for,
                    $suspension->resumeAt,
                    new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                );
                $instance->setWait($wait);
                $instance->setState(WorkflowState::WAITING);
                $this->stateManager->save($instance);
                $this->eventDispatcher->dispatch(new WorkflowSuspendedEvent($instance, $step, $wait));
                $this->logger->info('Workflow step suspended', [
                    'workflow_id' => $instance->getId(),
                    'step_id' => $step->getId(),
                    'waits_for' => $wait->for,
                    'resume_at' => $wait->resumeAt?->format(\DateTimeInterface::ATOM),
                ]);

                return true;
            }

            // Mark step as completed
            $this->stateManager->markStepCompleted($instance, $step->getId());
            $this->eventDispatcher->dispatch(new StepCompletedEvent($instance, $step));

            $this->logger->info('Workflow step completed successfully', [
                'workflow_id' => $instance->getId(),
                'step_id' => $step->getId(),
            ]);

            return false;
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

    private function executeActionWithRetry(WorkflowInstance $instance, Step $step): ?Suspension
    {
        $maxAttempts = $step->getRetryAttempts() + 1; // +1 for initial attempt

        if ($maxAttempts <= 1) {
            // No retries configured, execute directly
            return $this->executeAction($instance, $step);
        }

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->executeAction($instance, $step); // Success (or suspended) — exit retry loop
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

        return null;
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
    private function executeAction(WorkflowInstance $instance, Step $step): ?Suspension
    {
        $actionClass = $step->getActionClass();

        // A step without an action has nothing to execute (callers already
        // guard on hasAction(); this keeps the method total).
        if ($actionClass === null) {
            return null;
        }

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

        // Timeouts by SIGALRM are only safe in a CLI worker that runs one workflow at a time;
        // elsewhere (web requests, FrankenPHP / Octane workers) the adapter enforces them, for
        // example with the queue job's timeout.
        $timeoutValue = $this->signalTimeouts ? $step->getTimeout() : null;
        if ($timeoutValue !== null) {
            $timeoutSeconds = Timeout::toSeconds($timeoutValue);
            $result = $this->executeWithTimeout(
                fn () => $action->execute($context),
                $timeoutSeconds
            );
        } else {
            $result = $action->execute($context);
        }

        if (! $result instanceof ActionResult) {
            throw StepExecutionException::actionFailed('The action did not return an ActionResult', $step, $context);
        }

        if ($result->isSuccess() || $result->isSuspended()) {
            // Merge any output data from the action
            if ($result->hasData()) {
                $instance->mergeData($result->getData());
                $this->stateManager->save($instance);
            }

            return $result->getSuspension();
        }

        throw StepExecutionException::actionFailed(
            $result->getErrorMessage() ?? 'Action execution failed without specific error message',
            $step,
            $context
        );
    }
}
