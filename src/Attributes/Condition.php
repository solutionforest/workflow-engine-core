<?php

namespace SolutionForest\WorkflowEngine\Attributes;

use Attribute;

/**
 * Condition attribute for conditional execution
 *
 * @example
 * #[Condition('user.email is not null')]
 * #[Condition('order.amount > 100')]
 * #[Condition('user.premium = true')]
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Condition
{
    public function __construct(
        public string $expression,
        public string $operator = 'and' // 'and', 'or'
    ) {}
}
