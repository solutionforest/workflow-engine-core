<?php

use SolutionForest\WorkflowEngine\Actions\BaseAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Tests\Support\InMemoryStorage;

/**
 * Test action that records every execution so tests can assert the exact
 * sequence of step executions produced by the builder.
 */
class RecordingAction extends BaseAction
{
    /** @var array<int, string> */
    public static array $executed = [];

    public static function reset(): void
    {
        self::$executed = [];
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        self::$executed[] = $this->getConfig('label', $context->getStepId());

        return ActionResult::success();
    }

    public function getName(): string
    {
        return 'Recording Action';
    }

    public function getDescription(): string
    {
        return 'Test action that records its execution order.';
    }
}

describe('WorkflowBuilder → Executor end-to-end', function () {
    beforeEach(function () {
        RecordingAction::reset();
        $this->storage = new InMemoryStorage;
        $this->engine = new WorkflowEngine($this->storage);
    });

    test('a builder-defined multi-step workflow executes every step in order', function () {
        $definition = WorkflowBuilder::create('multi-step')
            ->addStep('first', RecordingAction::class, ['label' => 'first'])
            ->addStep('second', RecordingAction::class, ['label' => 'second'])
            ->addStep('third', RecordingAction::class, ['label' => 'third'])
            ->build();

        $id = $this->engine->start('multi-step-1', $definition->toArray(), []);
        $instance = $this->engine->getInstance($id);

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect(RecordingAction::$executed)->toBe(['first', 'second', 'third']);
        expect($instance->getCompletedSteps())->toBe(['first', 'second', 'third']);
    });

    test('then() chains execute every step', function () {
        $definition = WorkflowBuilder::create('then-chain')
            ->startWith(RecordingAction::class, ['label' => 'a'])
            ->then(RecordingAction::class, ['label' => 'b'])
            ->then(RecordingAction::class, ['label' => 'c'])
            ->build();

        $id = $this->engine->start('then-chain-1', $definition->toArray(), []);
        $instance = $this->engine->getInstance($id);

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect(RecordingAction::$executed)->toBe(['a', 'b', 'c']);
    });

    test('when() conditions skip steps whose condition is false and run steps whose condition is true', function () {
        $definition = WorkflowBuilder::create('conditional')
            ->addStep('start', RecordingAction::class, ['label' => 'start'])
            ->when("user.plan === 'premium'", function ($builder) {
                $builder->addStep('premium_only', RecordingAction::class, ['label' => 'premium_only']);
            })
            ->when("user.plan === 'free'", function ($builder) {
                $builder->addStep('free_only', RecordingAction::class, ['label' => 'free_only']);
            })
            ->addStep('finish', RecordingAction::class, ['label' => 'finish'])
            ->build();

        $id = $this->engine->start('conditional-1', $definition->toArray(), [
            'user' => ['plan' => 'premium'],
        ]);
        $instance = $this->engine->getInstance($id);

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect(RecordingAction::$executed)->toBe(['start', 'premium_only', 'finish']);
        // Skipped steps are still marked completed so transitions flow.
        expect($instance->getCompletedSteps())->toContain('free_only');
    });

    test('truthy when() keys work against boolean data', function () {
        $definition = WorkflowBuilder::create('truthy-when')
            ->addStep('start', RecordingAction::class, ['label' => 'start'])
            ->when('review.approved', function ($builder) {
                $builder->addStep('approve', RecordingAction::class, ['label' => 'approve']);
            })
            ->when('!review.approved', function ($builder) {
                $builder->addStep('reject', RecordingAction::class, ['label' => 'reject']);
            })
            ->build();

        $id = $this->engine->start('truthy-when-1', $definition->toArray(), [
            'review' => ['approved' => true],
        ]);
        $instance = $this->engine->getInstance($id);

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect(RecordingAction::$executed)->toBe(['start', 'approve']);
    });
});
