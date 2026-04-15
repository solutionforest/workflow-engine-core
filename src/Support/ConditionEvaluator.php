<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

final class ConditionEvaluator
{
    /**
     * Evaluate a condition expression against workflow data.
     *
     * Supports two forms:
     *  - Comparison: "key operator value" where operator is one of ===, !==, >=, <=, ==, !=, >, <
     *    and value is a boolean, null, integer, float, or quoted string literal.
     *  - Truthy key: "key" or "!key" to check whether the dotted key is truthy/falsy.
     *
     * @param string $condition Condition expression (e.g., "user.plan === 'premium'")
     * @param array<string, mixed> $data Workflow data to evaluate against
     * @return bool True if condition evaluates to true
     *
     * @throws InvalidWorkflowDefinitionException If condition format is invalid
     */
    public static function evaluate(string $condition, array $data): bool
    {
        $trimmed = trim($condition);

        if ($trimmed === '') {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $condition,
                'Condition cannot be empty.'
            );
        }

        // Comparison form: "key operator value".
        if (preg_match('/^(\w+(?:\.\w+)*)\s*(===|!==|>=|<=|==|!=|>|<)\s*(.+)$/', $trimmed, $matches)) {
            $key = $matches[1];
            $operator = $matches[2];
            $rawValue = trim($matches[3]);

            $value = self::parseLiteral($rawValue, $condition);
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

        // Truthy key form: "key" or "!key".
        if (preg_match('/^(!?)(\w+(?:\.\w+)*)$/', $trimmed, $matches)) {
            $negate = $matches[1] === '!';
            $dataValue = Arr::get($data, $matches[2]);

            return $negate ? ! $dataValue : (bool) $dataValue;
        }

        throw InvalidWorkflowDefinitionException::invalidCondition(
            $condition,
            'Condition must be a truthy key (e.g. "user.active") or "key operator value" (e.g. "user.plan === \'premium\'").'
        );
    }

    /**
     * Parse a literal value from its string form into a typed PHP value.
     *
     * Supports: true/false, null, integers, floats, and quoted strings.
     * Unquoted identifiers are returned as strings for backwards compatibility.
     */
    private static function parseLiteral(string $raw, string $condition): mixed
    {
        if ($raw === '') {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $condition,
                'Right-hand side of the comparison is empty.'
            );
        }

        $lower = strtolower($raw);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        // Quoted strings.
        $len = strlen($raw);
        if ($len >= 2) {
            $first = $raw[0];
            $last = $raw[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($raw, 1, -1);
            }
        }

        // Numeric literals.
        if (preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }
        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            return (float) $raw;
        }

        // Fallback: treat as an unquoted string (backwards compatible).
        return $raw;
    }
}
