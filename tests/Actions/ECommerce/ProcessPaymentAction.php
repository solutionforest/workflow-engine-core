<?php

namespace SolutionForest\WorkflowEngine\Tests\Actions\ECommerce;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class ProcessPaymentAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $order = $context->getData('order');

        // Mock payment processing
        $paymentId = 'pay_'.uniqid();
        $success = $order['total'] < 100000; // Simulate payment failure for very large orders

        if ($success) {
            return ActionResult::success([
                'payment.id' => $paymentId,
                'payment.success' => true,
                'payment.amount' => $order['total'],
                'payment_id' => $paymentId,
                'amount' => $order['total'],
                'status' => 'completed',
            ]);
        } else {
            return ActionResult::failure(
                'Payment processing failed',
                [
                    'payment.success' => false,
                    'payment.error' => 'Payment declined',
                    'payment_id' => null,
                    'amount' => $order['total'],
                    'status' => 'failed',
                ]
            );
        }
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('order') &&
               $context->getData('inventory.reserved') === true;
    }

    public function getName(): string
    {
        return 'Process Payment';
    }

    public function getDescription(): string
    {
        return 'Processes payment for the order';
    }
}
