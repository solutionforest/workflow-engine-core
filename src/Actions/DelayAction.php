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
        // `minutes` and `hours` are accepted alongside `seconds` because the
        // builder's delay() sugar and the documentation both offer them; they
        // used to be silently ignored, turning delay(hours: 2) into one second.
        $units = [
            'hours' => 3_600_000_000,
            'minutes' => 60_000_000,
            'seconds' => 1_000_000,
            'microseconds' => 1,
        ];

        $provided = array_filter(
            array_keys($units),
            fn (string $unit): bool => $this->getConfig($unit) !== null
        );

        $totalMicroseconds = 0;

        foreach ($provided as $unit) {
            $value = $this->getConfig($unit);

            if (! is_numeric($value) || $value < 0) {
                return ActionResult::failure("Invalid delay {$unit} specified");
            }

            $totalMicroseconds += $value * $units[$unit];
        }

        // Preserve the historical default of a one second pause when no unit
        // is configured at all.
        if ($provided === []) {
            $totalMicroseconds = $units['seconds'];
        }

        if ($totalMicroseconds > 0) {
            usleep((int) $totalMicroseconds);
        }

        return ActionResult::success([
            'delayed_seconds' => $totalMicroseconds / 1_000_000,
            'delayed_microseconds' => (int) $totalMicroseconds,
            'delayed_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('c'),
        ]);
    }
}
