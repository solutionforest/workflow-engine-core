<?php

namespace SolutionForest\WorkflowEngine\Attributes;

use Attribute;

/**
 * Timeout configuration attribute
 *
 * @example
 * #[Timeout(seconds: 30)]
 * #[Timeout(minutes: 5)]
 * #[Timeout(hours: 1)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class Timeout
{
    public readonly int $totalSeconds;

    public function __construct(
        ?int $seconds = null,
        ?int $minutes = null,
        ?int $hours = null
    ) {
        $this->totalSeconds = ($seconds ?? 0) + (($minutes ?? 0) * 60) + (($hours ?? 0) * 3600);
    }
}
