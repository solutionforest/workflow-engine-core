<?php

use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;

describe('WorkflowBuilder auto-generated step IDs', function () {
    test('does not collide with explicit step IDs that look auto-generated', function () {
        // Explicit "step_1" followed by then() must not re-emit "step_1".
        $definition = WorkflowBuilder::create('mixed-ids')
            ->addStep('step_1', LogAction::class, ['message' => 'first'])
            ->then(LogAction::class, ['message' => 'second'])
            ->then(LogAction::class, ['message' => 'third'])
            ->build();

        $ids = array_keys($definition->getSteps());

        expect($ids)->toHaveCount(3);
        expect($ids)->toContain('step_1');
        // The auto-generated IDs must not reuse "step_1"; the helper advances
        // the counter past any colliding explicit IDs.
        expect(array_filter($ids, fn ($id) => $id === 'step_1'))->toHaveCount(1);
    });

    test('email/delay/http/condition sugar methods use monotonic counters', function () {
        $definition = WorkflowBuilder::create('sugar-ids')
            ->email('welcome', 'user@example.com', 'Hi')
            ->email('followup', 'user@example.com', 'Hi again')
            ->delay(seconds: 30)
            ->delay(seconds: 60)
            ->build();

        $ids = array_keys($definition->getSteps());

        expect($ids)->toHaveCount(4);
        expect(count(array_unique($ids)))->toBe(4);
    });
});
