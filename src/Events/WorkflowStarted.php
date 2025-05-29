<?php

namespace SolutionForest\WorkflowEngine\Events;

class WorkflowStarted
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $workflowId,
        public readonly string $name,
        public readonly array $context = []
    ) {}
}
