<?php

namespace SolutionForest\WorkflowEngine\Support;

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

/**
 * Evaluates workflow condition expressions against workflow data.
 *
 * The grammar is deliberately small and is *not* PHP code — expressions are
 * parsed, never `eval()`d:
 *
 * ```
 * expression := or_expression
 * or_expression  := and_expression ( "||" and_expression )*
 * and_expression := term ( "&&" term )*
 * term := "(" expression ")" | comparison | truthy
 * comparison := key operator literal
 * truthy := [ "!" ] key
 * key := word ( "." word )*
 * operator := "===" | "!==" | ">=" | "<=" | "==" | "!=" | ">" | "<"
 * literal := "true" | "false" | "null" | quoted-string | number | bare-word
 * ```
 *
 * Anything outside this grammar throws InvalidWorkflowDefinitionException.
 * The evaluator never guesses: a malformed expression is a definition bug and
 * is surfaced loudly rather than silently collapsing to a boolean.
 *
 * @example
 * ```php
 * ConditionEvaluator::evaluate('order.total > 1000', ['order' => ['total' => 1500]]); // true
 * ConditionEvaluator::evaluate('user.active && user.plan === "premium"', $data);
 * ConditionEvaluator::evaluate('a.b > 1 || (c === "x" && !d)', $data);
 * ```
 */
final class ConditionEvaluator
{
    /**
     * Comparison operators, longest-first so that "===" wins over "==" and
     * ">=" over ">" when scanning.
     *
     * @var array<int, string>
     */
    private const OPERATORS = ['===', '!==', '>=', '<=', '==', '!=', '>', '<'];

    /**
     * Evaluate a condition expression against the supplied workflow data.
     *
     * @param string $condition The condition expression
     * @param array<string, mixed> $data Workflow data to evaluate against
     * @return bool The result of the expression
     *
     * @throws InvalidWorkflowDefinitionException If the expression is malformed
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

        return self::evaluateExpression($trimmed, $data, $condition);
    }

    /**
     * Evaluate a full expression, honouring `||` (lowest precedence) then `&&`.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidWorkflowDefinitionException
     */
    private static function evaluateExpression(string $expression, array $data, string $original): bool
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $original,
                'Encountered an empty sub-expression; check for a dangling "&&" or "||".'
            );
        }

        // `||` binds loosest, so split on it first: the operands are then
        // whole `&&` chains.
        $orParts = self::splitOnOperator($expression, '||');
        if (count($orParts) > 1) {
            foreach ($orParts as $part) {
                if (self::evaluateExpression($part, $data, $original)) {
                    return true; // Short-circuit.
                }
            }

            return false;
        }

        $andParts = self::splitOnOperator($expression, '&&');
        if (count($andParts) > 1) {
            foreach ($andParts as $part) {
                if (! self::evaluateExpression($part, $data, $original)) {
                    return false; // Short-circuit.
                }
            }

            return true;
        }

        return self::evaluateTerm($expression, $data, $original);
    }

    /**
     * Split an expression on a boolean operator at paren depth zero, ignoring
     * occurrences inside quoted strings.
     *
     * @return array<int, string> The operands; a single-element array means the
     *                            operator was not present at the top level.
     *
     * @throws InvalidWorkflowDefinitionException If parentheses are unbalanced
     */
    private static function splitOnOperator(string $expression, string $operator): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($expression);
        $operatorLength = strlen($operator);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
                $current .= $char;

                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    throw InvalidWorkflowDefinitionException::invalidCondition(
                        $expression,
                        'Unbalanced parentheses: unexpected ")".'
                    );
                }
                $current .= $char;

                continue;
            }

            if ($depth === 0 && substr($expression, $i, $operatorLength) === $operator) {
                $parts[] = $current;
                $current = '';
                $i += $operatorLength - 1;

                continue;
            }

            $current .= $char;
        }

        if ($quote !== null) {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $expression,
                'Unterminated quoted string.'
            );
        }

        if ($depth !== 0) {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $expression,
                'Unbalanced parentheses: missing ")".'
            );
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * Evaluate a single term: a parenthesised expression, a comparison, or a
     * truthy key check.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidWorkflowDefinitionException
     */
    private static function evaluateTerm(string $term, array $data, string $original): bool
    {
        $term = trim($term);

        // Parenthesised sub-expression — only when the leading "(" actually
        // closes at the very end, so "(a) && (b)" isn't mistaken for a group.
        if (str_starts_with($term, '(') && self::closesAtEnd($term)) {
            return self::evaluateExpression(substr($term, 1, -1), $data, $original);
        }

        // Negated group: !(...)
        if (str_starts_with($term, '!(')) {
            $inner = substr($term, 1);
            if (self::closesAtEnd($inner)) {
                return ! self::evaluateExpression(substr($inner, 1, -1), $data, $original);
            }
        }

        // Comparison form: "key operator literal".
        if (preg_match('/^(\w+(?:\.\w+)*)\s*(===|!==|>=|<=|==|!=|>|<)\s*(.+)$/s', $term, $matches)) {
            $key = $matches[1];
            $operator = $matches[2];
            $rawValue = trim($matches[3]);

            $value = self::parseLiteral($rawValue, $original);
            $dataValue = Arr::get($data, $key);

            // Relational operators against a missing key are meaningless: PHP
            // would coerce null to 0/"" and quietly report that `missing < 10`
            // is true. A condition on data that isn't there has not been met.
            if (self::isRelational($operator) && ! Arr::has($data, $key)) {
                return false;
            }

            return match ($operator) {
                '===' => $dataValue === $value,
                '!==' => $dataValue !== $value,
                '>=' => $dataValue >= $value,
                '<=' => $dataValue <= $value,
                '==' => $dataValue == $value,
                '!=' => $dataValue != $value,
                '>' => $dataValue > $value,
                '<' => $dataValue < $value,
            };
        }

        // Truthy key form: "key" or "!key".
        if (preg_match('/^(!?)(\w+(?:\.\w+)*)$/', $term, $matches)) {
            $negate = $matches[1] === '!';
            $dataValue = Arr::get($data, $matches[2]);

            return $negate ? ! $dataValue : (bool) $dataValue;
        }

        throw InvalidWorkflowDefinitionException::invalidCondition(
            $original,
            sprintf(
                'Could not parse "%s". A term must be a truthy key (e.g. "user.active"), '.
                'a comparison (e.g. "user.plan === \'premium\'"), or a parenthesised group. '.
                'Combine terms with "&&" and "||".',
                $term
            )
        );
    }

    /**
     * Determine whether a leading "(" is closed by the final character, i.e.
     * the whole term is one parenthesised group.
     */
    private static function closesAtEnd(string $term): bool
    {
        if (! str_starts_with($term, '(') || ! str_ends_with($term, ')')) {
            return false;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($term);

        for ($i = 0; $i < $length; $i++) {
            $char = $term[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;

                // Closed before the end: this is "(a) && (b)", not a group.
                if ($depth === 0 && $i !== $length - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    /**
     * Whether an operator compares magnitude (as opposed to equality).
     */
    private static function isRelational(string $operator): bool
    {
        return in_array($operator, ['>', '<', '>=', '<='], true);
    }

    /**
     * Parse the right-hand side of a comparison into a PHP value.
     *
     * Bare words are accepted as strings for convenience ("status == active"),
     * but anything that is neither a recognised literal nor a single bare word
     * is rejected. That rejection is what stops an unsupported expression such
     * as "total > 1000 && vip" from being silently treated as a string
     * comparison against the literal "1000 && vip".
     *
     * @throws InvalidWorkflowDefinitionException
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

        // Quoted strings — the quote must close at the very end, otherwise
        // "'a' && b" would be read as a single string value.
        $len = strlen($raw);
        if ($len >= 2) {
            $first = $raw[0];
            $last = $raw[$len - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $inner = substr($raw, 1, -1);

                if (! str_contains($inner, $first)) {
                    return $inner;
                }
            }
        }

        // Numeric literals.
        if (preg_match('/^-?\d+$/', $raw)) {
            return (int) $raw;
        }

        if (preg_match('/^-?\d+\.\d+$/', $raw)) {
            return (float) $raw;
        }

        // Bare word (an unquoted string such as "active" or "in_progress").
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $raw)) {
            return $raw;
        }

        foreach (self::OPERATORS as $operator) {
            if (str_contains($raw, $operator)) {
                throw InvalidWorkflowDefinitionException::invalidCondition(
                    $condition,
                    sprintf(
                        'Right-hand side "%s" contains the operator "%s". '.
                        'Chained comparisons are not supported — combine separate '.
                        'comparisons with "&&" or "||" instead.',
                        $raw,
                        $operator
                    )
                );
            }
        }

        throw InvalidWorkflowDefinitionException::invalidCondition(
            $condition,
            sprintf(
                'Could not parse "%s" as a value. Expected true, false, null, a number, '.
                'a quoted string, or a bare word.',
                $raw
            )
        );
    }
}
