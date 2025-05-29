<?php

namespace SolutionForest\WorkflowEngine\Tests\Actions\ECommerce;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class ValidateOrderAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $order = $context->getData('order');

        // Mock validation logic
        $isValid = isset($order['items']) &&
                  count($order['items']) > 0 &&
                  isset($order['total']) &&
                  $order['total'] > 0;

        if ($isValid) {
            return ActionResult::success([
                'order.valid' => true,
                'validation_result' => 'passed',
            ]);
        } else {
            return ActionResult::failure(
                'Order validation failed',
                [
                    'order.valid' => false,
                    'validation_result' => 'failed',
                ]
            );
        }
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('order');
    }

    public function getName(): string
    {
        return 'Validate Order';
    }

    public function getDescription(): string
    {
        return 'Validates order data including items and total amount';
    }
}
