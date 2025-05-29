<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

/**
 * A simple log action that logs a message
 */
class LogAction extends BaseAction
{
    public function getName(): string
    {
        return 'Log Message';
    }

    public function getDescription(): string
    {
        return 'Logs a message to the application log';
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        $message = $this->getConfig('message', 'Default log message');
        $level = $this->getConfig('level', 'info');

        // Replace placeholders in message with workflow data
        $processedMessage = $this->processMessage($message, $context->getData());

        // Log with appropriate level using injected logger
        match (strtolower($level)) {
            'debug' => $this->logger->debug($processedMessage, $context->toArray()),
            'info' => $this->logger->info($processedMessage, $context->toArray()),
            'warning' => $this->logger->warning($processedMessage, $context->toArray()),
            'error' => $this->logger->error($processedMessage, $context->toArray()),
            default => $this->logger->info($processedMessage, $context->toArray()),
        };

        return ActionResult::success([
            'logged_message' => $processedMessage,
            'logged_at' => (new \DateTime)->format('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function processMessage(string $message, array $data): string
    {
        // Simple placeholder replacement: {key.subkey}
        return preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($data) {
            $key = $matches[1];
            $value = parent::getNestedValue($data, $key, $matches[0]); // Keep placeholder if key not found

            return is_scalar($value) ? (string) $value : json_encode($value);
        }, $message);
    }
}
