<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Attributes\WorkflowStep;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Support\ConditionEvaluator;

/**
 * Records the result of a condition expression in the workflow data.
 *
 * This action **evaluates** a condition; it does not branch. Branching is a
 * property of the graph, not of a step, so route the workflow with a condition
 * on the transition (or on the target step) instead:
 *
 * ```php
 * 'transitions' => [
 *     ['from' => 'check', 'to' => 'premium', 'condition' => 'order.total > 1000'],
 *     ['from' => 'check', 'to' => 'standard', 'condition' => 'order.total <= 1000'],
 * ]
 * ```
 *
 * The result is merged into the workflow data as `result`, so later steps and
 * transitions can read it.
 *
 * Conditions use the grammar documented on {@see ConditionEvaluator}.
 */
#[WorkflowStep(
    id: 'condition_check',
    name: 'Condition Check',
    description: 'Evaluates conditions against workflow data'
)]
class ConditionAction extends BaseAction
{
    public function getName(): string
    {
        return 'Condition Check';
    }

    public function getDescription(): string
    {
        return 'Evaluates boolean conditions against workflow data';
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        $condition = $this->getConfig('condition');

        if (! $condition) {
            return ActionResult::failure('Condition is required');
        }

        try {
            $result = $this->evaluateCondition($condition, $context->getData());

            return ActionResult::success([
                'condition' => $condition,
                'result' => $result,
            ]);

        } catch (\Throwable $e) {
            return ActionResult::failure(
                "Condition evaluation failed: {$e->getMessage()}",
                ['condition' => $condition]
            );
        }
    }

    /**
     * Enhanced condition evaluation with PHP 8.3+ match expressions
     *
     * @param array<string, mixed> $data
     */
    /**
     * Evaluate a condition expression against workflow data.
     *
     * Delegates to {@see ConditionEvaluator} so that every condition in the
     * library — step conditions, transition conditions and this action — speaks
     * exactly one grammar. This class previously carried its own parser that
     * accepted "=" and "is", which no other part of the engine understood.
     *
     * @param array<string, mixed> $data
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        return ConditionEvaluator::evaluate($condition, $data);
    }
}
