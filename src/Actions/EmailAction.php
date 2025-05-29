<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class EmailAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $template = $context->getConfig('template', 'default');
        $to = $context->getConfig('to', '');
        $subject = $context->getConfig('subject', '');
        $data = $context->getConfig('data', []);

        // Mock email sending - in real implementation this would send actual emails
        $emailData = [
            'template' => $template,
            'to' => $to,
            'subject' => $subject,
            'data' => $data,
            'sent_at' => date('Y-m-d H:i:s'),
            'status' => 'sent',
        ];

        return ActionResult::success(['email_sent' => $emailData]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return ! empty($context->getConfig('to'));
    }

    public function getName(): string
    {
        return 'Send Email';
    }

    public function getDescription(): string
    {
        return 'Sends an email using the specified template and configuration';
    }
}
