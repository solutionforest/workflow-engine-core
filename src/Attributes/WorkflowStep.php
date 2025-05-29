<?php

namespace SolutionForest\WorkflowEngine\Attributes;

use Attribute;

/**
 * Workflow step configuration attribute
 *
 * @example
 * #[WorkflowStep(
 *     id: 'send_email',
 *     name: 'Send Welcome Email',
 *     description: 'Sends a welcome email to the new user'
 * )]
 * class SendWelcomeEmailAction implements WorkflowAction
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class WorkflowStep
{
    public function __construct(
        public string $id,
        public string $name = '',
        public string $description = '',
        /** @var array<string, mixed> */
        public array $config = [],
        public bool $required = true,
        public int $order = 0
    ) {}
}
