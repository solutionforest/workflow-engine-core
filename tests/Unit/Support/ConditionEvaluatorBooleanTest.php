<?php

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Support\ConditionEvaluator;

describe('ConditionEvaluator boolean operators', function () {

    test('&& requires every operand to hold', function () {
        $data = ['order' => ['total' => 1500, 'vip' => true]];

        expect(ConditionEvaluator::evaluate('order.total > 1000 && order.vip === true', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('order.total > 2000 && order.vip === true', $data))->toBeFalse();
        expect(ConditionEvaluator::evaluate('order.total > 1000 && order.vip === false', $data))->toBeFalse();
    });

    /**
     * The regression this whole grammar exists for: the old evaluator swallowed
     * "&& order.vip === true" into the right-hand side and string-compared
     * "500" against "1000 && ...", which reported TRUE for a 500 dollar order.
     */
    test('&& does not silently pass when the first comparison fails', function () {
        $data = ['order' => ['total' => 500, 'vip' => true]];

        expect(ConditionEvaluator::evaluate('order.total > 1000 && order.vip === true', $data))->toBeFalse();
    });

    test('|| requires only one operand to hold', function () {
        $data = ['order' => ['total' => 500, 'vip' => true]];

        expect(ConditionEvaluator::evaluate('order.total > 1000 || order.vip === true', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('order.total > 1000 || order.vip === false', $data))->toBeFalse();
    });

    test('&& binds tighter than ||', function () {
        // false && false || true  =>  (false && false) || true  =>  true
        $data = ['a' => false, 'b' => false, 'c' => true];

        expect(ConditionEvaluator::evaluate('a && b || c', $data))->toBeTrue();
    });

    test('parentheses override precedence', function () {
        // a && (b || c) => false && true => false
        $data = ['a' => false, 'b' => false, 'c' => true];

        expect(ConditionEvaluator::evaluate('a && (b || c)', $data))->toBeFalse();
        expect(ConditionEvaluator::evaluate('(a || c) && c', $data))->toBeTrue();
    });

    test('negation applies to groups', function () {
        $data = ['a' => false, 'c' => true];

        expect(ConditionEvaluator::evaluate('!(a && c)', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('!(a || c)', $data))->toBeFalse();
    });

    test('boolean operators inside quoted strings are not treated as operators', function () {
        $data = ['label' => 'fish && chips'];

        expect(ConditionEvaluator::evaluate('label === "fish && chips"', $data))->toBeTrue();
    });

    test('short-circuits without evaluating the rest', function () {
        // The right operand references a key that is absent; && must not need it.
        $data = ['enabled' => false];

        expect(ConditionEvaluator::evaluate('enabled && missing.key === "x"', $data))->toBeFalse();
    });

    test('rejects a dangling operator', function () {
        expect(fn () => ConditionEvaluator::evaluate('a && ', ['a' => true]))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('rejects unbalanced parentheses', function () {
        expect(fn () => ConditionEvaluator::evaluate('(a && b', ['a' => true, 'b' => true]))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('rejects a chained comparison rather than guessing', function () {
        expect(fn () => ConditionEvaluator::evaluate('a > 1 > 2', ['a' => 5]))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

});

describe('ConditionEvaluator missing keys', function () {

    /**
     * null coerces to 0/"" in PHP, so the old evaluator reported that an absent
     * key was less than 1000 — quietly running steps gated on data that was
     * never set.
     */
    test('relational comparisons against an absent key are false', function () {
        expect(ConditionEvaluator::evaluate('missing.key < 1000', []))->toBeFalse();
        expect(ConditionEvaluator::evaluate('missing.key > 1000', []))->toBeFalse();
        expect(ConditionEvaluator::evaluate('missing.key >= 0', []))->toBeFalse();
        expect(ConditionEvaluator::evaluate('missing.key <= 0', []))->toBeFalse();
    });

    test('a key explicitly set to a value still compares normally', function () {
        expect(ConditionEvaluator::evaluate('order.total < 1000', ['order' => ['total' => 500]]))->toBeTrue();
        expect(ConditionEvaluator::evaluate('order.total >= 500', ['order' => ['total' => 500]]))->toBeTrue();
    });

    test('equality against an absent key still works', function () {
        expect(ConditionEvaluator::evaluate('missing.key === null', []))->toBeTrue();
        expect(ConditionEvaluator::evaluate('missing.key !== "x"', []))->toBeTrue();
    });

});
