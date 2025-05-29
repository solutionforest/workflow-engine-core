<?php

namespace SolutionForest\WorkflowEngine\Contracts;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

interface StorageAdapter
{
    /**
     * Save a workflow instance
     */
    public function save(WorkflowInstance $instance): void;

    /**
     * Load a workflow instance by ID
     */
    public function load(string $id): WorkflowInstance;

    /**
     * Find workflow instances by criteria
     *
     * @param array<string, mixed> $criteria
     * @return array<WorkflowInstance>
     */
    public function findInstances(array $criteria = []): array;

    /**
     * Delete a workflow instance
     */
    public function delete(string $id): void;

    /**
     * Check if a workflow instance exists
     */
    public function exists(string $id): bool;

    /**
     * Update workflow instance state
     *
     * @param array<string, mixed> $updates
     */
    public function updateState(string $id, array $updates): void;
}
