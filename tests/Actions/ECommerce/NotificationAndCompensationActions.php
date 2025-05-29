<?php

namespace SolutionForest\WorkflowEngine\Tests\Actions\ECommerce;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class SendOrderConfirmationAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $order = $context->getData('order');
        $shipment = $context->getData('shipment');

        // Mock notification sending
        $notificationId = 'notif_'.uniqid();

        return ActionResult::success([
            'notification.id' => $notificationId,
            'notification.sent' => true,
            'notification.type' => 'order_confirmation',
            'notification_id' => $notificationId,
            'recipient' => $order['customer_email'] ?? 'customer@example.com',
            'tracking_number' => $shipment['tracking_number'] ?? null,
            'status' => 'sent',
        ]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('order') &&
               $context->getData('shipment.created') === true;
    }

    public function getName(): string
    {
        return 'Send Order Confirmation';
    }

    public function getDescription(): string
    {
        return 'Sends order confirmation email to customer';
    }
}

// Compensation Actions
class ReleaseInventoryAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $reservationId = $context->getData('inventory.reservation_id');

        if ($reservationId) {
            return ActionResult::success([
                'inventory.reserved' => false,
                'inventory.released' => true,
                'reservation_id' => $reservationId,
                'status' => 'released',
            ]);
        }

        return ActionResult::failure('No reservation ID found');
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('inventory.reservation_id');
    }

    public function getName(): string
    {
        return 'Release Inventory';
    }

    public function getDescription(): string
    {
        return 'Releases previously reserved inventory';
    }
}

class RefundPaymentAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $paymentId = $context->getData('payment.id');
        $amount = $context->getData('payment.amount');

        if ($paymentId) {
            $refundId = 'ref_'.uniqid();

            return ActionResult::success([
                'refund.id' => $refundId,
                'refund.amount' => $amount,
                'refund.processed' => true,
                'refund_id' => $refundId,
                'amount' => $amount,
                'status' => 'processed',
            ]);
        }

        return ActionResult::failure('No payment ID found');
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('payment.id');
    }

    public function getName(): string
    {
        return 'Refund Payment';
    }

    public function getDescription(): string
    {
        return 'Processes payment refund';
    }
}
