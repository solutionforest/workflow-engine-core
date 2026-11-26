<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Exceptions;

/**
 * Storage refused a save: someone else saved the instance since it was loaded (its revision
 * moved on). Nothing was written. Load the instance again and repeat the operation; for
 * signal() and resumeDue() that is always safe.
 */
final class ConcurrentModificationException extends WorkflowException
{
    public function __construct(
        public readonly string $instanceId,
        public readonly int $expectedRevision,
        public readonly int $storedRevision,
    ) {
        parent::__construct(
            "Workflow instance '{$instanceId}' was saved by someone else (revision {$storedRevision}, this copy has {$expectedRevision}).",
            ['instance_id' => $instanceId, 'expected_revision' => $expectedRevision, 'stored_revision' => $storedRevision],
        );
    }

    public function getUserMessage(): string
    {
        return 'This workflow was changed at the same time by someone else. Try again.';
    }
}
