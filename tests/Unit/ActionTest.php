<?php

use SolutionForest\WorkflowEngine\Actions\DelayAction;
use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\Wait;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;

test('log action can execute', function () {
    $action = new LogAction(['message' => 'Hello {{name}}']);
    $context = new WorkflowContext('test-workflow', 'test-step', ['name' => 'John']);

    $result = $action->execute($context);

    expect($result)->toBeInstanceOf(ActionResult::class);
    expect($result->isSuccess())->toBeTrue();
    expect($result->getErrorMessage())->toBeNull();
});

test('delay action suspends until its time instead of sleeping', function () {
    $action = new DelayAction(['seconds' => 30]);
    $context = new WorkflowContext('test-workflow', 'test-step');

    $start = microtime(true);
    $result = $action->execute($context);

    expect($result)->toBeInstanceOf(ActionResult::class);
    expect($result->isSuspended())->toBeTrue();
    expect($result->isSuccess())->toBeFalse();
    expect($result->isFailure())->toBeFalse();
    expect($result->getSuspension()?->for)->toBe(Wait::TIMER);
    expect($result->getSuspension()?->resumeAt?->getTimestamp())->toBeGreaterThanOrEqual(time() + 29);
    expect(microtime(true) - $start)->toBeLessThan(0.5);
});

test('log action handles invalid template', function () {
    $action = new LogAction(['message' => 'Hello {{invalid_variable}}']);
    $context = new WorkflowContext('test-workflow', 'test-step', ['name' => 'John']);

    $result = $action->execute($context);

    expect($result->isSuccess())->toBeTrue();
});

test('delay action handles invalid seconds', function () {
    $action = new DelayAction(['seconds' => 'invalid']);
    $context = new WorkflowContext('test-workflow', 'test-step');

    $result = $action->execute($context);

    expect($result->isSuccess())->toBeFalse();
    expect($result->getErrorMessage())->toContain('Invalid delay seconds');
});
