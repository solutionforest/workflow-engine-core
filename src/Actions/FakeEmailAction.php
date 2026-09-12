<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Contracts\Logger;
use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Support\NullLogger;

/**
 * A stand-in email step that records what *would* have been sent.
 *
 * ⚠️ **This action does not send email.** This library is framework-agnostic and
 * ships no mail transport, so there is nothing here to deliver a message with.
 * The action exists so workflows can be wired up and tested end to end before a
 * real mailer is available.
 *
 * The result payload is deliberately explicit — `sent` is always `false` and
 * `mock` is always `true` — so that a fake step can never be mistaken for a
 * delivered email by downstream steps, dashboards, or assertions. It also logs a
 * warning on every execution.
 *
 * To actually send mail, implement WorkflowAction yourself and call your own
 * mailer (Symfony Mailer, Laravel's Mail facade, PHPMailer, an API client, …).
 *
 * @example
 * ```php
 * class SendOrderEmailAction implements WorkflowAction
 * {
 *     public function execute(WorkflowContext $context): ActionResult
 *     {
 *         $this->mailer->send(...);
 *
 *         return ActionResult::success(['sent' => true]);
 *     }
 * }
 * ```
 */
class FakeEmailAction implements WorkflowAction
{
    private readonly Logger $logger;

    /**
     * @param array<string, mixed> $config Step configuration
     * @param Logger|null $logger Logger used to warn that no mail is sent
     */
    public function __construct(
        private readonly array $config = [],
        ?Logger $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger;
    }

    public function execute(WorkflowContext $context): ActionResult
    {
        $template = $context->getConfig('template', 'default');
        $to = $context->getConfig('to', '');
        $subject = $context->getConfig('subject', '');
        $data = $context->getConfig('data', []);

        $this->logger->warning('FakeEmailAction did not send an email', [
            'workflow_id' => $context->getWorkflowId(),
            'step_id' => $context->getStepId(),
            'to' => $to,
            'hint' => 'Implement WorkflowAction with a real mail transport to send email.',
        ]);

        return ActionResult::success([
            'email' => [
                'template' => $template,
                'to' => $to,
                'subject' => $subject,
                'data' => $data,
                'recorded_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('c'),
                // Never report a delivery that did not happen.
                'sent' => false,
                'mock' => true,
            ],
        ]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return ! empty($context->getConfig('to'));
    }

    public function getName(): string
    {
        return 'Fake Send Email';
    }

    public function getDescription(): string
    {
        return 'Records the email that would be sent; does not deliver anything';
    }

    /**
     * Get the step configuration this action was constructed with.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
