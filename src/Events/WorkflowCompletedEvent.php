<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

final readonly class WorkflowCompletedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
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
