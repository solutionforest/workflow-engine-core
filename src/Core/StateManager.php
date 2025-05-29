<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;
use SolutionForest\WorkflowEngine\Exceptions\WorkflowInstanceNotFoundException;

/**
 * State manager for workflow instance persistence and state transitions.
 *
 * The StateManager acts as an abstraction layer between the workflow engine
 * and the underlying storage system. It handles workflow instance persistence,
 * state transitions, and provides methods for querying workflow data.
 *
 *
 * @example Basic state management
 * ```php
 * $stateManager = new StateManager($storageAdapter);
 *
 * // Save workflow instance
 * $stateManager->save($instance);
 *
 * // Load workflow instance
 * $instance = $stateManager->load('workflow-123');
 *
 * // Update workflow state
 * $stateManager->updateState($instance, WorkflowState::COMPLETED);
 * ```
 * @example Step management
 * ```php
 * // Mark step as completed
 * $stateManager->markStepCompleted($instance, 'send-email');
 *
 * // Mark step as failed
 * $stateManager->markStepFailed($instance, 'payment', 'Payment gateway timeout');
 *
 * // Set current step
 * $stateManager->setCurrentStep($instance, 'next-step');
 * ```
 */
class StateManager
{
    /**
     * The storage adapter for workflow persistence.
     */
    private readonly StorageAdapter $storage;

    /**
     * Create a new state manager.
     *
     * @param  StorageAdapter  $storage  The storage adapter for workflow persistence
     *
     * @example Creating with database storage
     * ```php
     * $stateManager = new StateManager(
     *     new DatabaseStorageAdapter($connection)
     * );
     * ```
     */
    public function __construct(StorageAdapter $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Save a workflow instance to storage.
     *
     * Persists the complete workflow instance state including current step,
     * completed steps, data, and metadata to the underlying storage system.
     *
     * @param  WorkflowInstance  $instance  The workflow instance to save
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Saving workflow state
     * ```php
     * $instance->setState(WorkflowState::RUNNING);
     * $instance->setCurrentStepId('process-payment');
     * $stateManager->save($instance);
     * ```
     */
    public function save(WorkflowInstance $instance): void
    {
        $this->storage->save($instance);
    }

    /**
     * Load a workflow instance from storage by ID.
     *
     * Retrieves a complete workflow instance including its definition,
     * current state, execution history, and context data.
     *
     * @param  string  $instanceId  The workflow instance ID to load
     * @return WorkflowInstance The loaded workflow instance
     *
     * @throws WorkflowInstanceNotFoundException If the workflow instance doesn't exist
     *
     * @example Loading a workflow
     * ```php
     * try {
     *     $instance = $stateManager->load('workflow-123');
     *     echo "Current state: " . $instance->getState()->value;
     * } catch (WorkflowInstanceNotFoundException $e) {
     *     echo "Workflow not found: " . $e->getUserMessage();
     * }
     * ```
     */
    public function load(string $instanceId): WorkflowInstance
    {
        if (! $this->storage->exists($instanceId)) {
            throw WorkflowInstanceNotFoundException::notFound($instanceId, $this->storage::class);
        }

        return $this->storage->load($instanceId);
    }

    /**
     * Update the state of a workflow instance.
     *
     * Changes the workflow state and persists the change to storage.
     * This method handles state transition validation and ensures
     * the change is properly recorded.
     *
     * @param  WorkflowInstance  $instance  The workflow instance to update
     * @param  WorkflowState  $newState  The new state to set
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Completing a workflow
     * ```php
     * $stateManager->updateState($instance, WorkflowState::COMPLETED);
     * ```
     */
    public function updateState(WorkflowInstance $instance, WorkflowState $newState): void
    {
        $instance->setState($newState);
        $this->save($instance);
    }

    /**
     * Update the data/context of a workflow instance.
     *
     * Merges new data with existing workflow context and persists
     * the updated instance to storage.
     *
     * @param  WorkflowInstance  $instance  The workflow instance to update
     * @param  array<string, mixed>  $data  The data to merge with existing context
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Adding user context
     * ```php
     * $stateManager->updateData($instance, [
     *     'user_id' => 123,
     *     'preferences' => ['email_notifications' => true]
     * ]);
     * ```
     */
    public function updateData(WorkflowInstance $instance, array $data): void
    {
        $instance->mergeData($data);
        $this->save($instance);
    }

    /**
     * Set the current step for a workflow instance.
     *
     * Updates the workflow's current step pointer and persists the change.
     * This is typically called during step transitions.
     *
     * @param  WorkflowInstance  $instance  The workflow instance to update
     * @param  string|null  $stepId  The step ID to set as current (null for no current step)
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Moving to next step
     * ```php
     * $stateManager->setCurrentStep($instance, 'process-payment');
     * ```
     */
    public function setCurrentStep(WorkflowInstance $instance, ?string $stepId): void
    {
        $instance->setCurrentStepId($stepId);
        $this->save($instance);
    }

    /**
     * Mark a workflow step as completed.
     *
     * Records step completion in the workflow instance and persists
     * the change to storage. This tracks execution progress.
     *
     * @param  WorkflowInstance  $instance  The workflow instance
     * @param  string  $stepId  The ID of the completed step
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Marking step completion
     * ```php
     * $stateManager->markStepCompleted($instance, 'send-welcome-email');
     * ```
     */
    public function markStepCompleted(WorkflowInstance $instance, string $stepId): void
    {
        $instance->addCompletedStep($stepId);
        $this->save($instance);
    }

    /**
     * Mark a workflow step as failed.
     *
     * Records step failure with error details in the workflow instance
     * and persists the change to storage. This maintains error history.
     *
     * @param  WorkflowInstance  $instance  The workflow instance
     * @param  string  $stepId  The ID of the failed step
     * @param  string  $error  The error message describing the failure
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Recording step failure
     * ```php
     * $stateManager->markStepFailed(
     *     $instance,
     *     'payment-processing',
     *     'Payment gateway timeout after 30 seconds'
     * );
     * ```
     */
    public function markStepFailed(WorkflowInstance $instance, string $stepId, string $error): void
    {
        $instance->addFailedStep($stepId, $error);
        $this->save($instance);
    }

    /**
     * Set an error message and fail the workflow.
     *
     * Updates the workflow with an error message and sets the state to FAILED.
     * This is typically called when a workflow encounters an unrecoverable error.
     *
     * @param  WorkflowInstance  $instance  The workflow instance
     * @param  string  $error  The error message describing the failure
     *
     * @throws \Exception If the storage operation fails
     *
     * @example Failing a workflow
     * ```php
     * $stateManager->setError($instance, 'Critical dependency service unavailable');
     * ```
     */
    public function setError(WorkflowInstance $instance, string $error): void
    {
        $instance->setErrorMessage($error);
        $instance->setState(WorkflowState::FAILED);
        $this->save($instance);
    }

    /**
     * Find workflow instances matching the given criteria.
     *
     * Searches for workflow instances based on filtering criteria such as
     * state, workflow name, creation date, etc. The exact criteria supported
     * depend on the storage adapter implementation.
     *
     * @param  array<string, mixed>  $criteria  Search criteria for filtering instances
     * @return WorkflowInstance[] Array of matching workflow instances
     *
     * @throws \Exception If the search operation fails
     *
     * @example Finding failed workflows
     * ```php
     * $failedWorkflows = $stateManager->findInstances([
     *     'state' => WorkflowState::FAILED,
     *     'created_after' => '2024-01-01'
     * ]);
     * ```
     */
    public function findInstances(array $criteria = []): array
    {
        return $this->storage->findInstances($criteria);
    }

    /**
     * Delete a workflow instance from storage.
     *
     * Permanently removes a workflow instance and all its associated data
     * from the storage system. This operation cannot be undone.
     *
     * @param  string  $instanceId  The workflow instance ID to delete
     *
     * @throws \Exception If the delete operation fails
     *
     * @example Cleaning up old workflows
     * ```php
     * $stateManager->delete('old-workflow-123');
     * ```
     */
    public function delete(string $instanceId): void
    {
        $this->storage->delete($instanceId);
    }

    /**
     * Check if a workflow instance exists in storage.
     *
     * Verifies whether a workflow instance with the given ID exists
     * without loading the full instance data.
     *
     * @param  string  $instanceId  The workflow instance ID to check
     * @return bool True if the instance exists, false otherwise
     *
     * @example Checking instance existence
     * ```php
     * if ($stateManager->exists('workflow-123')) {
     *     $instance = $stateManager->load('workflow-123');
     * } else {
     *     echo "Workflow not found";
     * }
     * ```
     */
    public function exists(string $instanceId): bool
    {
        return $this->storage->exists($instanceId);
    }
}
