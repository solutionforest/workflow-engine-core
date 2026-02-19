<?php

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Support\ConditionEvaluator;

describe('ConditionEvaluator', function () {
    test('evaluates strict equality', function () {
        $data = ['plan' => 'premium'];
        expect(ConditionEvaluator::evaluate('plan === "premium"', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('plan === "basic"', $data))->toBeFalse();
    });

    test('evaluates strict inequality', function () {
        $data = ['plan' => 'premium'];
        expect(ConditionEvaluator::evaluate('plan !== "basic"', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('plan !== "premium"', $data))->toBeFalse();
    });

    test('evaluates loose equality', function () {
        $data = ['count' => '5'];
        expect(ConditionEvaluator::evaluate('count == 5', $data))->toBeTrue();
    });

    test('evaluates greater than', function () {
        $data = ['score' => '90'];
        expect(ConditionEvaluator::evaluate('score > 50', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('score > 100', $data))->toBeFalse();
    });

    test('evaluates less than', function () {
        $data = ['score' => '30'];
        expect(ConditionEvaluator::evaluate('score < 50', $data))->toBeTrue();
    });

    test('evaluates greater than or equal', function () {
        $data = ['score' => '50'];
        expect(ConditionEvaluator::evaluate('score >= 50', $data))->toBeTrue();
    });

    test('evaluates less than or equal', function () {
        $data = ['score' => '50'];
        expect(ConditionEvaluator::evaluate('score <= 50', $data))->toBeTrue();
    });

    test('handles dot notation for nested data', function () {
        $data = ['user' => ['profile' => ['tier' => 'gold']]];
        expect(ConditionEvaluator::evaluate('user.profile.tier === "gold"', $data))->toBeTrue();
        expect(ConditionEvaluator::evaluate('user.profile.tier === "silver"', $data))->toBeFalse();
    });

    test('throws on unparseable condition', function () {
        expect(fn () => ConditionEvaluator::evaluate('not a valid condition', ['key' => 'val']))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('throws on empty condition', function () {
        expect(fn () => ConditionEvaluator::evaluate('', []))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('handles missing data key gracefully', function () {
        $data = ['name' => 'John'];
        expect(ConditionEvaluator::evaluate('age === "30"', $data))->toBeFalse();
    });
});
