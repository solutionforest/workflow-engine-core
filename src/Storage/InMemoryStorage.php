<?php

namespace SolutionForest\WorkflowEngine\Storage;

use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Exceptions\WorkflowInstanceNotFoundException;

/**
 * Stores workflow instances in process memory.
 *
 * This is the adapter to reach for in tests, in examples, and when trying the
 * engine out — it makes the package usable the moment it is installed, without
 * first having to implement {@see StorageAdapter} against a database.
 *
 * ⚠️ State lives only for the lifetime of the PHP process. Nothing survives a
 * request, a worker restart, or a crash, and nothing is shared between
 * processes. Use a durable adapter for anything you care about.
 *
 * @example
 * ```php
 * $engine = new WorkflowEngine(new InMemoryStorage());
 * $engine->start('demo', $definition->toArray(), ['user_id' => 1]);
 * ```
 */
class InMemoryStorage implements StorageAdapter
{
    /** @var array<string, WorkflowInstance> Instances keyed by workflow ID */
    private array $instances = [];

    public function save(WorkflowInstance $instance): void
    {
        $this->instances[$instance->getId()] = $instance;
    }

    /**
     * @throws WorkflowInstanceNotFoundException If no instance has that ID
     */
    public function load(string $id): WorkflowInstance
    {
        if (! isset($this->instances[$id])) {
            throw WorkflowInstanceNotFoundException::notFound($id, self::class);
        }

        return $this->instances[$id];
    }

    /**
     * Find instances matching simple criteria.
     *
     * Supported keys: `state` (string or WorkflowState value), `definition_name`,
     * `limit` and `offset`. Unknown keys are ignored.
     *
     * @param array<string, mixed> $criteria
     * @return array<int, WorkflowInstance>
     */
    public function findInstances(array $criteria = []): array
    {
        $results = array_values($this->instances);

        if (isset($criteria['state'])) {
            $state = $criteria['state'];
            $wanted = is_string($state) ? $state : ($state->value ?? null);

            $results = array_values(array_filter(
                $results,
                static fn (WorkflowInstance $i): bool => $i->getState()->value === $wanted
            ));
        }

        if (isset($criteria['definition_name'])) {
            $name = $criteria['definition_name'];

            $results = array_values(array_filter(
                $results,
                static fn (WorkflowInstance $i): bool => $i->getDefinition()->getName() === $name
            ));
        }

        $offset = isset($criteria['offset']) ? max(0, (int) $criteria['offset']) : 0;
        $limit = isset($criteria['limit']) ? max(0, (int) $criteria['limit']) : null;

        if ($offset > 0 || $limit !== null) {
            $results = array_slice($results, $offset, $limit);
        }

        return $results;
    }

    public function delete(string $id): void
    {
        unset($this->instances[$id]);
    }

    public function exists(string $id): bool
    {
        return isset($this->instances[$id]);
    }

    /**
     * Apply partial updates to a stored instance.
     *
     * Instances are mutable objects held by reference, so the engine's own
     * writes are already visible here; this method exists to satisfy the
     * contract and to support callers that patch state directly.
     *
     * @param array<string, mixed> $updates
     *
     * @throws WorkflowInstanceNotFoundException If no instance has that ID
     */
    public function updateState(string $id, array $updates): void
    {
        $instance = $this->load($id);

        if (isset($updates['data']) && is_array($updates['data'])) {
            $instance->mergeData($updates['data']);
        }

        if (array_key_exists('error_message', $updates)) {
            $errorMessage = $updates['error_message'];
            $instance->setErrorMessage(is_string($errorMessage) ? $errorMessage : null);
        }

        if (isset($updates['current_step_id'])) {
            $instance->setCurrentStepId((string) $updates['current_step_id']);
        }

        $this->save($instance);
    }

    /**
     * Remove every stored instance.
     */
    public function flush(): void
    {
        $this->instances = [];
    }

    /**
     * Count the stored instances.
     */
    public function count(): int
    {
        return count($this->instances);
    }
}
