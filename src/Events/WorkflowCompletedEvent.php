<?php

namespace SolutionForest\WorkflowEngine\Events;

use SolutionForest\WorkflowEngine\Core\WorkflowInstance;

class WorkflowCompletedEvent
{
    public WorkflowInstance $instance;

    public function __construct(WorkflowInstance $instance)
    {
        $this->instance = $instance;
    }
}
