<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

class WorkflowFailedEvent
{
    public WorkflowInstance $instance;

    public \Exception $exception;

    public function __construct(WorkflowInstance $instance, \Exception $exception)
    {
        $this->instance = $instance;
        $this->exception = $exception;
    }
}
