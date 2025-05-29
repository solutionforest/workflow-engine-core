<?php

namespace SolutionForest\WorkflowEngine\Tests\Actions\ECommerce;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class CreateShipmentAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $order = $context->getData('order');

        // Mock shipment creation
        $shipmentId = 'ship_'.uniqid();
        $trackingNumber = 'TRK'.mt_rand(100000, 999999);

        return ActionResult::success([
            'shipment.id' => $shipmentId,
            'shipment.tracking_number' => $trackingNumber,
            'shipment.created' => true,
            'shipment_id' => $shipmentId,
            'tracking_number' => $trackingNumber,
            'status' => 'created',
        ]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('order') &&
               $context->getData('payment.success') === true;
    }

    public function getName(): string
    {
        return 'Create Shipment';
    }

    public function getDescription(): string
    {
        return 'Creates shipment and generates tracking number';
    }
}
