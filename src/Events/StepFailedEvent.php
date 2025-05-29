<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use Throwable;

/**
 * Event fired when a workflow step fails to execute.
 */
final readonly class StepFailedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public Step $step,
        public Throwable $exception
    ) {}

    public function getWorkflowId(): string
    {
        return $this->instance->getId();
    }

    public function getStepId(): string
    {
        return $this->step->getId();
    }

    public function getStepName(): string
    {
        return $this->step->getId();
    }

    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    public function getData(): array
    {
        return $this->instance->getData();
    }
}
