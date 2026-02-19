<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

final readonly class WorkflowFailedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public \Exception $exception,
    ) {}

    public function getWorkflowId(): string
    {
        return $this->instance->getId();
    }

    public function getWorkflowName(): string
    {
        return $this->instance->getDefinition()->getName();
    }

    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }
}
