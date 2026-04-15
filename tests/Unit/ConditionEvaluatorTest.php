<?php

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Support\ConditionEvaluator;

describe('ConditionEvaluator', function () {
    test('strict equality works against integer data', function () {
        $data = ['user' => ['age' => 25]];

        expect(ConditionEvaluator::evaluate('user.age === 25', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('user.age !== 25', $data))->toBeFalse();
        expect(ConditionEvaluator::evaluate('user.age === 26', $data))->toBeFalse();
    });

    test('strict equality works against boolean data', function () {
        $data = ['user' => ['premium' => true, 'verified' => false]];

        expect(ConditionEvaluator::evaluate('user.premium === true', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('user.verified === false', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('user.premium === false', $data))->toBeFalse();
    });

    test('strict equality works against null data', function () {
        $data = ['token' => null];

        expect(ConditionEvaluator::evaluate('token === null', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('token !== null', $data))->toBeFalse();
    });

    test('strict equality works against quoted strings', function () {
        $data = ['user' => ['plan' => 'premium']];

        expect(ConditionEvaluator::evaluate("user.plan === 'premium'", $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('user.plan === "premium"', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate("user.plan === 'free'", $data))->toBeFalse();
    });

    test('numeric comparison operators work with float data', function () {
        $data = ['order' => ['total' => 1500.50]];

        expect(ConditionEvaluator::evaluate('order.total > 1000', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('order.total > 1500.50', $data))->toBeFalse();
        expect(ConditionEvaluator::evaluate('order.total >= 1500.50', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('order.total < 2000', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('order.total <= 1500.50', $data))->toBeTrue();
    });

    test('truthy key form evaluates a dotted path to bool', function () {
        $data = ['review' => ['approved' => true, 'rejected' => false, 'notes' => '']];

        expect(ConditionEvaluator::evaluate('review.approved', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('review.rejected', $data))->toBeFalse();
        expect(ConditionEvaluator::evaluate('review.notes', $data))->toBeFalse();
        expect(ConditionEvaluator::evaluate('review.missing', $data))->toBeFalse();
    });

    test('negated truthy key form inverts the result', function () {
        $data = ['review' => ['approved' => false]];

        expect(ConditionEvaluator::evaluate('!review.approved', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('!review.missing', $data))->toBeTrue();
    });

    test('empty condition throws', function () {
        expect(fn () => ConditionEvaluator::evaluate('   ', []))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('malformed condition throws', function () {
        expect(fn () => ConditionEvaluator::evaluate('1 + 2', []))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('unquoted identifiers on RHS fall back to string comparison for backwards compatibility', function () {
        $data = ['status' => 'pending'];

        // RHS without quotes is treated as a string literal.
        expect(ConditionEvaluator::evaluate('status === pending', $data))->toBeTrue();
    });
});
