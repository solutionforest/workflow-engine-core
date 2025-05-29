<?php

namespace SolutionForest\WorkflowEngine\Attributes;

use Attribute;

/**
 * Retry configuration attribute
 *
 * @example
 * #[Retry(attempts: 3)]
 * #[Retry(attempts: 5, backoff: 'exponential')]
 * #[Retry(attempts: 3, backoff: 'linear', delay: 1000)]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class Retry
{
    public function __construct(
        public int $attempts = 3,
        public string $backoff = 'linear', // 'linear', 'exponential', 'fixed'
        public int $delay = 1000, // milliseconds
        public int $maxDelay = 30000 // maximum delay in milliseconds
    ) {}
}
