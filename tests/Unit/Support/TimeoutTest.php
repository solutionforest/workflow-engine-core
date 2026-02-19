<?php

use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Support\Timeout;

describe('Timeout', function () {
    test('parses integer seconds', function () {
        expect(Timeout::toSeconds(30))->toBe(30);
        expect(Timeout::toSeconds(0))->toBe(0);
    });

    test('parses numeric string', function () {
        expect(Timeout::toSeconds('300'))->toBe(300);
    });

    test('parses seconds suffix', function () {
        expect(Timeout::toSeconds('30s'))->toBe(30);
    });

    test('parses minutes suffix', function () {
        expect(Timeout::toSeconds('5m'))->toBe(300);
    });

    test('parses hours suffix', function () {
        expect(Timeout::toSeconds('2h'))->toBe(7200);
    });

    test('parses days suffix', function () {
        expect(Timeout::toSeconds('1d'))->toBe(86400);
    });

    test('throws on invalid format', function () {
        expect(fn () => Timeout::toSeconds('invalid'))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });

    test('throws on unsupported unit', function () {
        expect(fn () => Timeout::toSeconds('5w'))
            ->toThrow(InvalidWorkflowDefinitionException::class);
    });
});
