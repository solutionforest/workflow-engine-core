<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\Wait;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

/**
 * A suspended step finished: a signal arrived ($signal is its name) or its time passed
 * ($signal is Wait::TIMER). The workflow carries on with the next steps.
 *
 * @phpstan-type Payload array<string, mixed>
 */
final readonly class WorkflowResumedEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public WorkflowInstance $instance,
        public Step $step,
        public Wait $wait,
        public string $signal,
        public array $payload = [],
    ) {}
}
