<?php

use SolutionForest\WorkflowEngine\Actions\BaseAction;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Tests\Support\InMemoryStorage;

/**
 * Records every execution so tests can assert execution order regardless of
 * where in a long chain a step lives.
 */
class SequenceAction extends BaseAction
{
    /** @var array<int, string> */
    public static array $log = [];

    public static function reset(): void
    {
        self::$log = [];
    }

    protected function doExecute(WorkflowContext $context): ActionResult
    {
        self::$log[] = $context->getStepId();

        return ActionResult::success();
    }

    public function getName(): string
    {
        return 'Sequence Action';
    }

    public function getDescription(): string
    {
        return 'Test action that logs the order of execution.';
    }
}

describe('Executor iteration', function () {
    beforeEach(function () {
        SequenceAction::reset();
    });

    test('iteratively executes a long chain of steps without stack overflow', function () {
        // 200 steps would blow the default xdebug stack if the executor were
        // still recursive. With iteration, it should run cleanly.
        $builder = WorkflowBuilder::create('long-chain');
        for ($i = 0; $i < 200; $i++) {
            $builder->addStep("s{$i}", SequenceAction::class);
        }

        $definition = $builder->build();
        $engine = new WorkflowEngine(new InMemoryStorage);

        $id = $engine->start('long-chain-1', $definition->toArray(), []);
        $instance = $engine->getInstance($id);

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect(count(SequenceAction::$log))->toBe(200);
        expect(SequenceAction::$log[0])->toBe('s0');
        expect(SequenceAction::$log[199])->toBe('s199');
    });
});
