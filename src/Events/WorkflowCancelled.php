<?php

namespace SolutionForest\WorkflowEngine\Events;

class WorkflowCancelled
{
    public function __construct(
        public readonly string $workflowId,
        public readonly string $name,
        public readonly string $reason = ''
    ) {}
}
