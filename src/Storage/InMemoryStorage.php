<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Storage;

use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;
use SolutionForest\WorkflowEngine\Core\WorkflowDefinition;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Exceptions\ConcurrentModificationException;
use SolutionForest\WorkflowEngine\Exceptions\WorkflowInstanceNotFoundException;

/**
 * Stores workflow instances in process memory.
 *
 * This is the adapter to reach for in tests, in examples, and when trying the
 * engine out — it makes the package usable the moment it is installed, without
 * first having to implement {@see StorageAdapter} against a database.
 *
 * It behaves like a database: it keeps a snapshot of each save, every load returns a fresh
 * copy, and a save from a stale copy is refused ({@see ConcurrentModificationException}).
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
    /** @var array<string, array{row: array<string, mixed>, definition: WorkflowDefinition}> Snapshots keyed by workflow ID */
    private array $instances = [];

    /**
     * @throws ConcurrentModificationException If the instance was saved since this copy was loaded
     */
    public function save(WorkflowInstance $instance): void
    {
        $id = $instance->getId();
        $stored = isset($this->instances[$id]) ? $this->revisionOf($this->instances[$id]['row']) : 0;
        if ($stored !== $instance->getRevision()) {
            throw new ConcurrentModificationException($id, $instance->getRevision(), $stored);
        }

        $instance->setRevision($stored + 1);
        $this->instances[$id] = ['row' => $instance->toArray(), 'definition' => $instance->getDefinition()];
    }

    /**
     * @throws WorkflowInstanceNotFoundException If no instance has that ID
     */
    public function load(string $id): WorkflowInstance
    {
        if (! isset($this->instances[$id])) {
            throw WorkflowInstanceNotFoundException::notFound($id, self::class);
        }

        return WorkflowInstance::fromArray($this->instances[$id]['row'], $this->instances[$id]['definition']);
    }

    /**
     * Find instances matching simple criteria.
     *
     * Supported keys: `state` (string or WorkflowState), `definition_name`, `due_before`
     * (a DateTimeInterface: suspended instances whose wait time has passed), `limit` and
     * `offset`. Unknown keys are ignored.
     *
     * @param array<string, mixed> $criteria
     * @return array<int, WorkflowInstance>
     */
    public function findInstances(array $criteria = []): array
    {
        $results = array_map(fn (string $id): WorkflowInstance => $this->load($id), array_keys($this->instances));

        if (isset($criteria['state'])) {
            $state = $criteria['state'];
            $wanted = $state instanceof WorkflowState ? $state->value : $state;
            $results = array_filter($results, static fn (WorkflowInstance $i): bool => $i->getState()->value === $wanted);
        }

        if (isset($criteria['definition_name'])) {
            $name = $criteria['definition_name'];
            $results = array_filter($results, static fn (WorkflowInstance $i): bool => $i->getDefinition()->getName() === $name);
        }

        if (($criteria['due_before'] ?? null) instanceof \DateTimeInterface) {
            $now = $criteria['due_before'];
            $results = array_filter($results, static fn (WorkflowInstance $i): bool => $i->getState() === WorkflowState::WAITING
                && ($i->getWait()?->isDue($now) ?? false));
        }

        $results = array_values($results);
        $offset = is_int($criteria['offset'] ?? null) ? max(0, $criteria['offset']) : 0;
        $limit = is_int($criteria['limit'] ?? null) ? max(0, $criteria['limit']) : null;

        return $offset > 0 || $limit !== null ? array_slice($results, $offset, $limit) : $results;
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
     * Apply a partial update (`data` merged, `error_message`, `current_step_id`) and save.
     *
     * @param array<string, mixed> $updates
     */
    public function updateState(string $id, array $updates): void
    {
        $instance = $this->load($id);

        if (isset($updates['data']) && is_array($updates['data'])) {
            /** @var array<string, mixed> $data */
            $data = $updates['data'];
            $instance->mergeData($data);
        }

        if (array_key_exists('error_message', $updates)) {
            $errorMessage = $updates['error_message'];
            $instance->setErrorMessage(is_string($errorMessage) ? $errorMessage : null);
        }

        if (is_string($updates['current_step_id'] ?? null)) {
            $instance->setCurrentStepId($updates['current_step_id']);
        }

        $this->save($instance);
    }

    /** Remove every stored instance. */
    public function flush(): void
    {
        $this->instances = [];
    }

    /** Number of stored instances. */
    public function count(): int
    {
        return count($this->instances);
    }

    /** @param array<string, mixed> $row */
    private function revisionOf(array $row): int
    {
        return is_int($row['revision'] ?? null) ? $row['revision'] : 0;
    }
}
