<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\Step;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

/**
 * Event fired when a workflow step is successfully completed.
 */
final readonly class StepCompletedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public Step $step,
        public mixed $result = null
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
        return $this->step->getId(); // Use ID as name since Step doesn't have a name property
    }

    public function getData(): array
    {
        return $this->instance->getData();
    }
}
