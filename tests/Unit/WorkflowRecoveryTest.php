<?php

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;
use SolutionForest\WorkflowEngine\Exceptions\StepExecutionException;
use SolutionForest\WorkflowEngine\Storage\InMemoryStorage;

/**
 * An action whose failure can be switched off, standing in for a transient
 * outage that an operator fixes before resuming.
 */
class RecoverableAction implements WorkflowAction
{
    public static bool $shouldFail = true;

    public static int $attempts = 0;

    /** @param array<string, mixed> $config */
    public function __construct(public array $config = [], public mixed $logger = null) {}

    public function execute(WorkflowContext $context): ActionResult
    {
        self::$attempts++;

        if (self::$shouldFail) {
            throw new RuntimeException('transient outage');
        }

        return ActionResult::success(['recovered' => true]);
    }

    public function canExecute(WorkflowContext $context): bool
    {
        return true;
    }

    public function getName(): string
    {
        return 'recoverable';
    }

    public function getDescription(): string
    {
        return 'Fails until told otherwise';
    }
}

beforeEach(function () {
    RecoverableAction::$shouldFail = true;
    RecoverableAction::$attempts = 0;

    $this->storage = new InMemoryStorage;
    $this->engine = new WorkflowEngine($this->storage);
    $this->definition = [
        'name' => 'Recovery',
        'steps' => [
            ['id' => 'flaky', 'action' => RecoverableAction::class],
        ],
    ];
});

describe('resuming a failed workflow', function () {

    test('a failing step leaves the workflow in FAILED with the real error', function () {
        expect(fn () => $this->engine->start('wf-1', $this->definition))
            ->toThrow(StepExecutionException::class);

        $instance = $this->engine->getInstance('wf-1');

        expect($instance->getState())->toBe(WorkflowState::FAILED);
        expect($instance->getErrorMessage())->toContain('transient outage');
    });

    /**
     * Previously this threw "Cannot transition workflow from 'failed' to
     * 'failed'" — a bookkeeping error raised inside the catch block, which
     * destroyed the original cause and made recovery impossible.
     */
    test('resume retries the failed step and completes', function () {
        expect(fn () => $this->engine->start('wf-1', $this->definition))
            ->toThrow(StepExecutionException::class);

        RecoverableAction::$shouldFail = false;

        $instance = $this->engine->resume('wf-1');

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect($instance->getData())->toHaveKey('recovered');
        expect(RecoverableAction::$attempts)->toBe(2);
    });

    test('resume clears the stale error message', function () {
        expect(fn () => $this->engine->start('wf-1', $this->definition))
            ->toThrow(StepExecutionException::class);

        RecoverableAction::$shouldFail = false;
        $instance = $this->engine->resume('wf-1');

        expect($instance->getErrorMessage())->toBeNull();
    });

    test('resume that fails again still reports the real error', function () {
        expect(fn () => $this->engine->start('wf-1', $this->definition))
            ->toThrow(StepExecutionException::class);

        // Still broken: the second failure must surface as the step error, not
        // as an illegal-transition error from the failure handler.
        expect(fn () => $this->engine->resume('wf-1'))
            ->toThrow(StepExecutionException::class, "Step 'flaky' failed: transient outage");

        expect($this->engine->getInstance('wf-1')->getState())->toBe(WorkflowState::FAILED);
    });

    test('a failed workflow can be cancelled instead of retried', function () {
        expect(fn () => $this->engine->start('wf-1', $this->definition))
            ->toThrow(StepExecutionException::class);

        $instance = $this->engine->cancel('wf-1', 'giving up');

        expect($instance->getState())->toBe(WorkflowState::CANCELLED);
    });

    test('a cancelled workflow cannot be resumed', function () {
        expect(fn () => $this->engine->start('wf-1', $this->definition))
            ->toThrow(StepExecutionException::class);

        $this->engine->cancel('wf-1', 'giving up');

        expect(fn () => $this->engine->resume('wf-1'))
            ->toThrow(InvalidWorkflowStateException::class);
    });

    test('a completed workflow cannot be resumed', function () {
        RecoverableAction::$shouldFail = false;
        $this->engine->start('wf-2', $this->definition);

        expect(fn () => $this->engine->resume('wf-2'))
            ->toThrow(InvalidWorkflowStateException::class);
    });

});

describe('instance ID collisions', function () {

    /**
     * start() used to overwrite the stored instance, discarding the earlier
     * run's state and history with nothing to indicate it had happened.
     */
    test('starting a second workflow with an existing ID is rejected', function () {
        RecoverableAction::$shouldFail = false;
        $this->engine->start('wf-dup', $this->definition, ['run' => 1]);

        expect(fn () => $this->engine->start('wf-dup', $this->definition, ['run' => 2]))
            ->toThrow(InvalidWorkflowStateException::class);

        // The original run is intact.
        expect($this->engine->getInstance('wf-dup')->getData()['run'])->toBe(1);
    });

    test('the ID is free again after the instance is deleted', function () {
        RecoverableAction::$shouldFail = false;
        $this->engine->start('wf-dup', $this->definition, ['run' => 1]);
        $this->storage->delete('wf-dup');

        $this->engine->start('wf-dup', $this->definition, ['run' => 2]);

        expect($this->engine->getInstance('wf-dup')->getData()['run'])->toBe(2);
    });

});
