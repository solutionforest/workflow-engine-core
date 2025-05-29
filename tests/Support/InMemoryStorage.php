<?php

namespace SolutionForest\WorkflowEngine\Tests\Support;

use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

class InMemoryStorage implements StorageAdapter
{
    private array $instances = [];

    public function save(WorkflowInstance $instance): void
    {
        $this->instances[$instance->getId()] = $instance;
    }

    public function load(string $id): WorkflowInstance
    {
        if (! isset($this->instances[$id])) {
            throw new \InvalidArgumentException("Workflow instance not found: {$id}");
        }

        return $this->instances[$id];
    }

    public function findInstances(array $criteria = []): array
    {
        if (empty($criteria)) {
            return array_values($this->instances);
        }

        return array_filter($this->instances, function ($instance) use ($criteria) {
            foreach ($criteria as $key => $value) {
                // Simple implementation for basic filtering
                if ($key === 'state' && $instance->getState()->value !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    public function delete(string $id): void
    {
        unset($this->instances[$id]);
    }

    public function exists(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    public function updateState(string $id, array $updates): void
    {
        if (! isset($this->instances[$id])) {
            throw new \InvalidArgumentException("Workflow instance not found: {$id}");
        }

        // Simple update implementation
        // In a real implementation, this would update specific fields
        $instance = $this->instances[$id];
        $this->instances[$id] = $instance;
    }
}
