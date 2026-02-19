<?php

use SolutionForest\WorkflowEngine\Actions\BaseAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;
use SolutionForest\WorkflowEngine\Tests\Support\InMemoryStorage;
use SolutionForest\WorkflowEngine\Tests\Support\SpyEventDispatcher;

// A test action that fails N times then succeeds
class FailNTimesAction extends BaseAction
{
    private static int $callCount = 0;

    public static function reset(): void
    {
        self::$callCount = 0;
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        self::$callCount++;
        $failCount = $this->getConfig('fail_count', 0);

        if (self::$callCount <= $failCount) {
            throw new \RuntimeException('Intentional failure #'.self::$callCount);
        }

        return ActionResult::success(['attempts' => self::$callCount]);
    }

    public function getName(): string
    {
        return 'Fail N Times';
    }

    public function getDescription(): string
    {
        return 'Test action that fails a configurable number of times';
    }
}

// A test action that always fails
class AlwaysFailAction extends BaseAction
{
    protected function doExecute(WorkflowContext $context): ActionResult
    {
        throw new \RuntimeException('Always fails');
    }

    public function getName(): string
    {
        return 'Always Fail';
    }

    public function getDescription(): string
    {
        return 'Test action that always fails';
    }
}

describe('Executor Retry Logic', function () {
    beforeEach(function () {
        FailNTimesAction::reset();
    });

    test('retries and succeeds after transient failure', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = WorkflowBuilder::create('retry-test')
            ->addStep('flaky_step', FailNTimesAction::class, ['fail_count' => 2], retryAttempts: 3)
            ->build();

        $id = $engine->start('retry-success', $definition->toArray(), []);
        $instance = $engine->getInstance($id);

        expect($instance->getState()->value)->toBe('completed');

        // Should have dispatched StepRetried events
        $retryEvents = array_filter($spy->dispatched, fn ($e) => str_contains(get_class($e), 'StepRetried'));
        expect(count($retryEvents))->toBe(2); // Failed twice before succeeding
    });

    test('fails after exhausting all retry attempts', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = WorkflowBuilder::create('retry-exhaust')
            ->addStep('always_fail', AlwaysFailAction::class, retryAttempts: 2)
            ->build();

        expect(fn () => $engine->start('retry-fail', $definition->toArray(), []))
            ->toThrow(StepExecutionException::class);
    });

    test('does not retry when retryAttempts is 0', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = WorkflowBuilder::create('no-retry')
            ->addStep('fail_once', AlwaysFailAction::class, retryAttempts: 0)
            ->build();

        expect(fn () => $engine->start('no-retry', $definition->toArray(), []))
            ->toThrow(StepExecutionException::class);

        $retryEvents = array_filter($spy->dispatched, fn ($e) => str_contains(get_class($e), 'StepRetried'));
        expect(count($retryEvents))->toBe(0);
    });
});
