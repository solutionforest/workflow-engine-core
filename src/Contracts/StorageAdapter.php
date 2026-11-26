<?php

namespace SolutionForest\WorkflowEngine\Contracts;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Exceptions\ConcurrentModificationException;

interface StorageAdapter
{
    /**
     * Save a workflow instance, as one compare-and-swap on its revision.
     *
     * The stored revision must equal $instance->getRevision() (0 when the instance was
     * never saved); otherwise someone saved it since this copy was loaded, and the
     * adapter writes nothing and throws ConcurrentModificationException. On success it
     * stores the instance, including getWait() and the definition, and calls
     * $instance->setRevision($stored + 1).
     *
     * @throws ConcurrentModificationException
     */
    public function save(WorkflowInstance $instance): void;

    /**
     * Load a workflow instance by ID
     */
    public function load(string $id): WorkflowInstance;

    /**
     * Find workflow instances by criteria
     *
     * Adapters support at least `state`, `definition_name`, `limit`, `offset` and
     * `due_before` (a DateTimeInterface: WAITING instances whose wait has a resume time
     * at or before it; WorkflowEngine::resumeDue() uses it).
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
