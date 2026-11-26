<?php

declare(strict_types=1);

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\Wait;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

/** A step suspended the workflow: it now waits for $wait->for, or until $wait->resumeAt. */
final readonly class WorkflowSuspendedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public Step $step,
        public Wait $wait,
    ) {}
}
