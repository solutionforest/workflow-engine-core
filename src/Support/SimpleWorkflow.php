<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;
use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;

/**
 * Simple workflow helper for quick workflow creation and execution.
 *
 * This class provides a simplified API for common workflow operations,
 * reducing the learning curve for new users. It offers fluent methods
 * for creating and executing workflows without dealing with the underlying
 * complexity of the workflow engine.
 *
 * @example Basic usage
 * ```php
 * $simple = new SimpleWorkflow($storage);
 *
 * // Create and execute a sequential workflow
 * $instanceId = $simple->sequential('user-onboarding', [
 *     SendWelcomeEmailAction::class,
 *     CreateUserProfileAction::class,
 *     AssignDefaultRoleAction::class,
 * ], ['user_id' => 123]);
 * ```
 * @example Using static methods
 * ```php
 * // Quick single action execution
 * SimpleWorkflow::runAction(SendEmailAction::class, [
 *     'to' => 'user@example.com',
 *     'subject' => 'Welcome!'
 * ]);
 * ```
 */
class SimpleWorkflow
{
    /**
     * The underlying workflow engine for execution.
     */
    private WorkflowEngine $engine;

    /**
     * Create a new SimpleWorkflow instance.
     *
     * @param  StorageAdapter  $storage  Storage adapter for workflow persistence
     * @param  Dispatcher|null  $eventDispatcher  Optional event dispatcher for workflow events
     */
    public function __construct(StorageAdapter $storage, ?EventDispatcher $eventDispatcher = null)
    {
        $this->engine = new WorkflowEngine($storage, $eventDispatcher);
    }

    /**
     * Create and execute a simple sequential workflow.
     *
     * Creates a workflow where actions execute one after another
     * in the order they are provided.
     *
     * @param  string  $name  Workflow name/identifier
     * @param  array<string>  $actions  Array of action class names
     * @param  array<string, mixed>  $context  Initial workflow context data
     * @return string The workflow instance ID
     *
     * @example Sequential workflow
     * ```php
     * $instanceId = $simple->sequential('user-onboarding', [
     *     SendWelcomeEmailAction::class,
     *     CreateUserProfileAction::class,
     *     AssignDefaultRoleAction::class,
     * ], ['user_id' => 123]);
     * ```
     */
    public function sequential(string $name, array $actions, array $context = []): string
    {
        $builder = WorkflowBuilder::create($name);

        // Use the fluent 'then' method for sequential execution
        foreach ($actions as $action) {
            $builder->then($action);
        }

        $workflow = $builder->build();

        return $this->engine->start($name.'_'.uniqid(), $workflow->toArray(), $context);
    }

    /**
     * Run a single action as a complete workflow.
     *
     * Convenience method for executing a single action with
     * the full workflow infrastructure.
     *
     * @param  string  $actionClass  The action class to execute
     * @param  array<string, mixed>  $context  Context data for the action
     * @return string The workflow instance ID
     *
     * @example Single action execution
     * ```php
     * $instanceId = $simple->runAction(SendEmailAction::class, [
     *     'to' => 'user@example.com',
     *     'subject' => 'Welcome to our platform!'
     * ]);
     * ```
     */
    public function runAction(string $actionClass, array $context = []): string
    {
        return $this->sequential(
            'single_action_'.class_basename($actionClass),
            [$actionClass],
            $context
        );
    }

    /**
     * Get the underlying workflow engine instance.
     *
     * Provides access to the full WorkflowEngine API when
     * the simplified methods are insufficient.
     *
     * @return WorkflowEngine The workflow engine instance
     *
     * @example Advanced usage
     * ```php
     * $simple = new SimpleWorkflow($storage);
     *
     * // Use the engine directly for advanced operations
     * $engine = $simple->getEngine();
     * $instances = $engine->listInstances(['state' => 'running']);
     * ```
     */
    public function getEngine(): WorkflowEngine
    {
        return $this->engine;
    }

    /**
     * Create and execute a workflow from a builder.
     *
     * Allows using the full WorkflowBuilder API while still
     * benefiting from the simple execution interface.
     *
     * @param  WorkflowBuilder  $builder  Configured workflow builder
     * @param  array<string, mixed>  $context  Initial workflow context
     * @return string The workflow instance ID
     *
     * @example Custom workflow with builder
     * ```php
     * $builder = WorkflowBuilder::create('complex-workflow')
     *     ->addStep('validate', ValidateAction::class)
     *     ->addStep('process', ProcessAction::class)
     *     ->addConditionalStep('notify', NotifyAction::class, 'success === true')
     *     ->addTransition('validate', 'process')
     *     ->addTransition('process', 'notify');
     *
     * $instanceId = $simple->executeBuilder($builder, $context);
     * ```
     */
    public function executeBuilder(WorkflowBuilder $builder, array $context = []): string
    {
        $workflow = $builder->build();

        return $this->engine->start(
            $workflow->getName().'_'.uniqid(),
            $workflow->toArray(),
            $context
        );
    }

    /**
     * Resume a paused workflow instance.
     *
     * Convenience method for resuming workflow execution.
     *
     * @param  string  $instanceId  The workflow instance ID to resume
     */
    public function resume(string $instanceId): void
    {
        $this->engine->resume($instanceId);
    }

    /**
     * Get the status of a workflow instance.
     *
     * @param  string  $instanceId  The workflow instance ID
     * @return array<string, mixed> Workflow instance status information
     */
    public function getStatus(string $instanceId): array
    {
        $instance = $this->engine->getInstance($instanceId);

        return [
            'id' => $instance->getId(),
            'state' => $instance->getState()->value,
            'current_step' => $instance->getCurrentStepId(),
            'progress' => $instance->getProgress(),
            'completed_steps' => $instance->getCompletedSteps(),
            'failed_steps' => $instance->getFailedSteps(),
            'error_message' => $instance->getErrorMessage(),
            'created_at' => $instance->getCreatedAt(),
            'updated_at' => $instance->getUpdatedAt(),
        ];
    }
}
