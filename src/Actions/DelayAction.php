<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

/**
 * A delay action that can be used to pause workflow execution
 */
class DelayAction extends BaseAction
{
    public function getName(): string
    {
        return 'Delay';
    }

    public function getDescription(): string
    {
        return 'Adds a delay to workflow execution';
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        $seconds = $this->getConfig('seconds', 1);
        $microseconds = $this->getConfig('microseconds', 0);

        if (! is_numeric($seconds) || $seconds < 0) {
            return ActionResult::failure('Invalid delay seconds specified');
        }

        if (! is_numeric($microseconds) || $microseconds < 0) {
            return ActionResult::failure('Invalid delay microseconds specified');
        }

        // Convert to total microseconds
        $totalMicroseconds = ($seconds * 1000000) + $microseconds;

        if ($totalMicroseconds > 0) {
            usleep((int) $totalMicroseconds);
        }

        return ActionResult::success([
            'delayed_seconds' => $seconds,
            'delayed_microseconds' => $microseconds,
            'delayed_at' => (new \DateTime)->format('c'),
        ]);
    }
}
