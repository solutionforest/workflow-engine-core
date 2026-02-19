<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

final class ConditionEvaluator
{
    /**
     * Evaluate a condition expression against workflow data.
     *
     * @param string $condition Condition expression (e.g., "user.plan === premium")
     * @param array<string, mixed> $data Workflow data to evaluate against
     * @return bool True if condition evaluates to true
     *
     * @throws InvalidWorkflowDefinitionException If condition format is invalid
     */
    public static function evaluate(string $condition, array $data): bool
    {
        if (! preg_match('/(\w+(?:\.\w+)*)\s*(===|!==|>=|<=|==|!=|>|<)\s*(.+)/', $condition, $matches)) {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $condition,
                'Condition must be in format: "key operator value" (e.g., "user.plan === premium")'
            );
        }

        $key = $matches[1];
        $operator = $matches[2];
        $value = trim($matches[3], '"\'');

        $dataValue = Arr::get($data, $key);

        return match ($operator) {
            '===' => $dataValue === $value,
            '!==' => $dataValue !== $value,
            '>=' => $dataValue >= $value,
            '<=' => $dataValue <= $value,
            '==' => $dataValue == $value,
            '!=' => $dataValue != $value,
            '>' => $dataValue > $value,
            '<' => $dataValue < $value,
            default => false,
        };
    }
}
