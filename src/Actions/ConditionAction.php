<?php

namespace SolutionForest\WorkflowEngine\Actions;

use SolutionForest\WorkflowEngine\Attributes\Condition;
use SolutionForest\WorkflowEngine\Attributes\WorkflowStep;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

/**
 * Condition evaluation action with advanced expression parsing
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
        $onTrue = $this->getConfig('on_true', null);
        $onFalse = $this->getConfig('on_false', null);

        if (! $condition) {
            return ActionResult::failure('Condition is required');
        }

        try {
            $result = $this->evaluateCondition($condition, $context->getData());

            return ActionResult::success([
                'condition' => $condition,
                'result' => $result,
                'next_action' => $result ? $onTrue : $onFalse,
            ]);

        } catch (\Exception $e) {
            return ActionResult::failure(
                "Condition evaluation failed: {$e->getMessage()}",
                ['condition' => $condition]
            );
        }
    }

    /**
     * Enhanced condition evaluation with PHP 8.3+ match expressions
     *
     * @param  array<string, mixed>  $data
     */
    private function evaluateCondition(string $condition, array $data): bool
    {
        // Simple expression parser for common patterns
        if (preg_match('/^(.+?)\s*(=|!=|>|<|>=|<=|is|is not)\s*(.+)$/', $condition, $matches)) {
            $left = trim($matches[1]);
            $operator = trim($matches[2]);
            $right = trim($matches[3]);

            $leftValue = $this->getValue($left, $data);
            $rightValue = $this->getValue($right, $data);

            return match ($operator) {
                '=' => $leftValue == $rightValue,
                '!=' => $leftValue != $rightValue,
                '>' => $leftValue > $rightValue,
                '<' => $leftValue < $rightValue,
                '>=' => $leftValue >= $rightValue,
                '<=' => $leftValue <= $rightValue,
                'is' => $leftValue === $rightValue,
                'is not' => $leftValue !== $rightValue,
                default => throw new \InvalidArgumentException("Unsupported operator: {$operator}")
            };
        }

        // Check for boolean values
        if (in_array(strtolower($condition), ['true', '1', 'yes'])) {
            return true;
        }

        if (in_array(strtolower($condition), ['false', '0', 'no'])) {
            return false;
        }

        // Direct data access
        return (bool) $this->getValue($condition, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function getValue(string $expression, array $data): mixed
    {
        // Remove quotes for string literals
        if (preg_match('/^["\'](.+)["\']$/', $expression, $matches)) {
            return $matches[1];
        }

        // Check for numeric values
        if (is_numeric($expression)) {
            return str_contains($expression, '.') ? (float) $expression : (int) $expression;
        }

        // Check for boolean literals
        return match (strtolower($expression)) {
            'true', 'yes' => true,
            'false', 'no' => false,
            'null', 'empty' => null,
            default => data_get($data, $expression)
        };
    }
}
