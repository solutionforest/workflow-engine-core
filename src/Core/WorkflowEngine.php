<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;
use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;
use SolutionForest\WorkflowEngine\Events\WorkflowCancelled;
use SolutionForest\WorkflowEngine\Events\WorkflowStarted;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;
use SolutionForest\WorkflowEngine\Exceptions\WorkflowInstanceNotFoundException;

/**
 * Core workflow engine for managing workflow lifecycle and execution.
 *
 * The WorkflowEngine is the central component that orchestrates workflow
 * execution, state management, and event dispatching. It provides a clean
 * API for starting, resuming, and managing workflow instances.
 *
 *
 * @example Basic workflow execution
 * ```php
 * $engine = new WorkflowEngine($storageAdapter, $eventDispatcher);
 *
 * // Start a new workflow
 * $instanceId = $engine->start('user-onboarding', [
 *     'name' => 'User Onboarding',
 *     'steps' => [
 *         ['id' => 'welcome', 'action' => SendWelcomeEmailAction::class],
 *         ['id' => 'profile', 'action' => CreateProfileAction::class],
 *     ]
 * ], ['user_id' => 123]);
 *
 * // Resume execution later
 * $instance = $engine->resume($instanceId);
 * ```
 * @example With dependency injection
 * ```php
 * // In a Laravel service provider
 * $this->app->singleton(WorkflowEngine::class, function ($app) {
 *     return new WorkflowEngine(
 *         $app->make(StorageAdapter::class),
 *         $app->make(EventDispatcher::class)
 *     );
 * });
 * ```
 */
class WorkflowEngine
{
    /**
     * The definition parser for processing workflow definitions.
     */
    private readonly DefinitionParser $parser;

    /**
     * The state manager for persisting workflow state.
     */
    private readonly StateManager $stateManager;

    /**
     * The executor for running workflow steps.
     */
    private readonly Executor $executor;

    /**
     * Create a new workflow engine instance.
     *
     * @param  StorageAdapter  $storage  The storage adapter for persisting workflow data
     * @param  EventDispatcher|null  $eventDispatcher  Optional event dispatcher for workflow events
     *
     * @throws \InvalidArgumentException If the storage adapter is not properly configured
     */
    public function __construct(
        private readonly StorageAdapter $storage,
        private readonly ?EventDispatcher $eventDispatcher = null
    ) {
        $this->parser = new DefinitionParser;
        $this->stateManager = new StateManager($storage);
        $this->executor = new Executor($this->stateManager, $eventDispatcher);

        // If no event dispatcher is provided, we'll use a fallback approach
        if ($this->eventDispatcher === null) {
            // We'll handle this case in the methods that use the event dispatcher
        }
    }

    /**
     * Start a new workflow instance with the given definition and context.
     *
     * Creates a new workflow instance, saves it to storage, dispatches a start event,
     * and begins execution of the first step.
     *
     * @param  string  $workflowId  Unique identifier for this workflow instance
     * @param  array<string, mixed>  $definition  The workflow definition containing steps and configuration
     * @param  array<string, mixed>  $context  Initial context data for the workflow
     * @return string The workflow instance ID
     *
     * @throws InvalidWorkflowDefinitionException If the workflow definition is invalid
     * @throws \RuntimeException If the workflow cannot be started due to system issues
     *
     * @example Starting a simple workflow
     * ```php
     * $instanceId = $engine->start('order-processing', [
     *     'name' => 'Order Processing',
     *     'steps' => [
     *         ['id' => 'validate', 'action' => ValidateOrderAction::class],
     *         ['id' => 'payment', 'action' => ProcessPaymentAction::class],
     *         ['id' => 'fulfill', 'action' => FulfillOrderAction::class],
     *     ]
     * ], [
     *     'order_id' => 12345,
     *     'customer_id' => 67890
     * ]);
     * ```
     */
    public function start(string $workflowId, array $definition, array $context = []): string
    {
        // Parse definition
        $workflowDef = $this->parser->parse($definition);

        // Create instance
        $instance = new WorkflowInstance(
            id: $workflowId,
            definition: $workflowDef,
            state: WorkflowState::PENDING,
            data: $context,
            createdAt: new \DateTime,
            updatedAt: new \DateTime
        );

        // Save initial state
        $this->stateManager->save($instance);

        // Dispatch start event
        $this->dispatchEvent(new WorkflowStarted(
            $instance->getId(),
            $instance->getDefinition()->getName(),
            $context
        ));

        // Execute first step
        $this->executor->execute($instance);

        return $instance->getId();
    }

    /**
     * Resume execution of an existing workflow instance.
     *
     * Loads the workflow instance from storage and continues execution
     * from where it left off. Only works for workflows in PENDING or FAILED state.
     *
     * @param  string  $instanceId  The workflow instance ID to resume
     * @return WorkflowInstance The resumed workflow instance
     *
     * @throws WorkflowInstanceNotFoundException If the workflow instance doesn't exist
     * @throws InvalidWorkflowStateException If the workflow cannot be resumed (e.g., already completed)
     * @throws \RuntimeException If the workflow cannot be resumed due to system issues
     *
     * @example Resuming a workflow
     * ```php
     * try {
     *     $instance = $engine->resume('workflow-123');
     *     echo "Workflow resumed, current state: " . $instance->getState()->value;
     * } catch (InvalidWorkflowStateException $e) {
     *     echo "Cannot resume: " . $e->getUserMessage();
     * }
     * ```
     */
    public function resume(string $instanceId): WorkflowInstance
    {
        $instance = $this->stateManager->load($instanceId);

        if ($instance->getState() === WorkflowState::COMPLETED) {
            throw InvalidWorkflowStateException::cannotResumeCompleted($instanceId);
        }

        $this->executor->execute($instance);

        return $instance;
    }

    /**
     * Get a workflow instance by its ID.
     *
     * Retrieves the complete workflow instance including its current state,
     * execution history, and context data.
     *
     * @param  string  $instanceId  The workflow instance ID
     * @return WorkflowInstance The workflow instance
     *
     * @throws WorkflowInstanceNotFoundException If the workflow instance doesn't exist
     *
     * @example Getting workflow instance details
     * ```php
     * $instance = $engine->getInstance('workflow-123');
     *
     * echo "Workflow: " . $instance->getDefinition()->getName();
     * echo "State: " . $instance->getState()->label();
     * echo "Progress: " . $instance->getProgress() . "%";
     * ```
     */
    public function getInstance(string $instanceId): WorkflowInstance
    {
        return $this->stateManager->load($instanceId);
    }

    /**
     * Get all workflow instances with optional filtering.
     *
     * Retrieves workflow instances based on the provided filters.
     * Useful for building dashboards, monitoring, and reporting.
     *
     * @param  array<string, mixed>  $filters  Optional filters to apply
     *                                         - 'state': Filter by workflow state (e.g., 'running', 'completed')
     *                                         - 'definition_name': Filter by workflow definition name
     *                                         - 'created_after': Filter by creation date (DateTime or string)
     *                                         - 'created_before': Filter by creation date (DateTime or string)
     *                                         - 'limit': Maximum number of results to return
     *                                         - 'offset': Number of results to skip (for pagination)
     * @return WorkflowInstance[] Array of workflow instances matching the filters
     *
     * @throws \InvalidArgumentException If invalid filters are provided
     *
     * @example Getting recent failed workflows
     * ```php
     * $failedWorkflows = $engine->getInstances([
     *     'state' => 'failed',
     *     'created_after' => (new \DateTime())->modify('-7 days'),
     *     'limit' => 50
     * ]);
     *
     * foreach ($failedWorkflows as $workflow) {
     *     echo "Failed: " . $workflow->getId() . "\n";
     * }
     * ```
     */
    public function getInstances(array $filters = []): array
    {
        return $this->storage->findInstances($filters);
    }

    /**
     * Cancel a workflow instance
     */
    public function cancel(string $instanceId, string $reason = ''): WorkflowInstance
    {
        $instance = $this->stateManager->load($instanceId);
        $instance->setState(WorkflowState::CANCELLED);
        $this->stateManager->save($instance);

        // Dispatch cancel event
        $this->dispatchEvent(new WorkflowCancelled(
            $instance->getId(),
            $instance->getDefinition()->getName(),
            $reason
        ));

        return $instance;
    }

    /**
     * Get workflow instance by ID
     */
    public function getWorkflow(string $workflowId): WorkflowInstance
    {
        return $this->stateManager->load($workflowId);
    }

    /**
     * Get workflow status
     */
    public function getStatus(string $workflowId): array
    {
        $instance = $this->getWorkflow($workflowId);

        return [
            'workflow_id' => $instance->getId(),
            'name' => $instance->getDefinition()->getName(),
            'state' => $instance->getState()->value,
            'current_step' => $instance->getCurrentStepId(),
            'progress' => $instance->getProgress(),
            'created_at' => $instance->getCreatedAt(),
            'updated_at' => $instance->getUpdatedAt(),
        ];
    }

    /**
     * List workflows with optional filters
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function listWorkflows(array $filters = []): array
    {
        // Convert WorkflowState enum to string value for storage layer
        if (isset($filters['state']) && $filters['state'] instanceof WorkflowState) {
            $filters['state'] = $filters['state']->value;
        }

        $instances = $this->storage->findInstances($filters);

        return array_map(function (WorkflowInstance $instance) {
            return [
                'workflow_id' => $instance->getId(),
                'name' => $instance->getDefinition()->getName(),
                'state' => $instance->getState()->value,
                'current_step' => $instance->getCurrentStepId(),
                'progress' => $instance->getProgress(),
                'created_at' => $instance->getCreatedAt(),
                'updated_at' => $instance->getUpdatedAt(),
            ];
        }, $instances);
    }

    /**
     * Safely dispatch an event if event dispatcher is available
     */
    private function dispatchEvent(object $event): void
    {
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
