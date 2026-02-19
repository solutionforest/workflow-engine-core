<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

final readonly class StepRetriedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public Step $step,
        public int $attempt,
        public int $maxAttempts,
        public \Throwable $lastError,
    ) {}

    public function getWorkflowId(): string
    {
        return $this->instance->getId();
    }

    public function getStepId(): string
    {
        return $this->step->getId();
    }
}
