<?php

namespace SolutionForest\WorkflowEngine\Tests\Actions\ECommerce;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

class FraudCheckAction implements WorkflowAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $order = $context->getData('order');

        // Mock fraud detection logic
        $riskScore = $this->calculateRiskScore($order);

        return ActionResult::success([
            'fraud.risk' => $riskScore,
            'risk_score' => $riskScore,
            'status' => $riskScore < 0.7 ? 'safe' : 'flagged',
        ]);
    }

    private function calculateRiskScore(array $order): float
    {
        // Simple mock risk calculation
        $baseRisk = 0.1;

        if ($order['total'] > 10000) {
            $baseRisk += 0.3;
        }

        if ($order['total'] > 50000) {
            $baseRisk += 0.4;
        }

        return min($baseRisk, 1.0);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return $context->hasData('order') && $context->getData('order.valid') === true;
    }

    public function getName(): string
    {
        return 'Fraud Check';
    }

    public function getDescription(): string
    {
        return 'Analyzes order for potential fraud indicators';
    }
}
