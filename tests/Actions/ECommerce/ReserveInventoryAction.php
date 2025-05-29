<?php

namespace SolutionForest\WorkflowEngine\Tests\Actions\ECommerce;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class ReserveInventoryAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $order = $context->getData('order');

        // Mock inventory reservation
        $reservationId = 'res_'.uniqid();

        return ActionResult::success([
            'inventory.reservation_id' => $reservationId,
            'inventory.reserved' => true,
            'reservation_id' => $reservationId,
            'status' => 'reserved',
        ]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('order') &&
               $context->getData('order.valid') === true &&
               ($context->getData('fraud.risk') ?? 0) < 0.7;
    }

    public function getName(): string
    {
        return 'Reserve Inventory';
    }

    public function getDescription(): string
    {
        return 'Reserves inventory items for the order';
    }
}
