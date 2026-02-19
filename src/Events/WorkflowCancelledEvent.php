<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

final readonly class WorkflowCancelledEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public string $reason = '',
    ) {}

    public function getWorkflowId(): string
    {
        return $this->instance->getId();
    }

    public function getWorkflowName(): string
    {
        return $this->instance->getDefinition()->getName();
    }
}
