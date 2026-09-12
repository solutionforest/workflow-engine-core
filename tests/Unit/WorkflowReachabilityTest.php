<?php

use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Core\DefinitionParser;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Storage\InMemoryStorage;

beforeEach(function () {
    $this->engine = new WorkflowEngine(new InMemoryStorage);
    $this->parser = new DefinitionParser;
});

describe('multi-root workflows', function () {

    /**
     * getFirstStep() used to return a single root, so a second independent
     * entry point was never executed — yet the workflow still reported
     * COMPLETED, with progress silently stuck below 100%.
     */
    test('every entry point runs', function () {
        $definition = [
            'name' => 'Multi Root',
            'steps' => [
                ['id' => 'root_a', 'action' => LogAction::class, 'config' => ['message' => 'a']],
                ['id' => 'root_b', 'action' => LogAction::class, 'config' => ['message' => 'b']],
                ['id' => 'join', 'action' => LogAction::class, 'config' => ['message' => 'join']],
            ],
            'transitions' => [
                ['from' => 'root_a', 'to' => 'join'],
                ['from' => 'root_b', 'to' => 'join'],
            ],
        ];

        $this->engine->start('multi', $definition);
        $instance = $this->engine->getInstance('multi');

        expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        expect($instance->getCompletedSteps())->toContain('root_a', 'root_b', 'join');
        expect($instance->getProgress())->toBe(100.0);
    });

    test('getFirstSteps reports all roots', function () {
        $definition = $this->parser->parse([
            'name' => 'Multi Root',
            'steps' => [
                ['id' => 'root_a', 'action' => LogAction::class],
                ['id' => 'root_b', 'action' => LogAction::class],
                ['id' => 'join', 'action' => LogAction::class],
            ],
            'transitions' => [
                ['from' => 'root_a', 'to' => 'join'],
                ['from' => 'root_b', 'to' => 'join'],
            ],
        ]);

        $ids = array_map(fn ($step) => $step->getId(), $definition->getFirstSteps());

        expect($ids)->toEqualCanonicalizing(['root_a', 'root_b']);
    });

    test('a single-root workflow still reports one entry point', function () {
        $definition = $this->parser->parse([
            'name' => 'Linear',
            'steps' => [
                ['id' => 'one', 'action' => LogAction::class],
                ['id' => 'two', 'action' => LogAction::class],
            ],
            'transitions' => [
                ['from' => 'one', 'to' => 'two'],
            ],
        ]);

        expect($definition->getFirstSteps())->toHaveCount(1);
        expect($definition->getFirstStep()->getId())->toBe('one');
    });

});

describe('unreachable step validation', function () {

    /**
     * A step with no incoming transition is an entry point, so it still runs.
     * What genuinely cannot be reached is a group of steps that only point at
     * each other: every one of them has an incoming transition, so none is a
     * root, and nothing outside the group leads in.
     */
    test('an isolated cycle is rejected at parse time', function () {
        expect(fn () => $this->parser->parse([
            'name' => 'Island',
            'steps' => [
                ['id' => 'start', 'action' => LogAction::class],
                ['id' => 'finish', 'action' => LogAction::class],
                ['id' => 'island_a', 'action' => LogAction::class],
                ['id' => 'island_b', 'action' => LogAction::class],
            ],
            'transitions' => [
                ['from' => 'start', 'to' => 'finish'],
                ['from' => 'island_a', 'to' => 'island_b'],
                ['from' => 'island_b', 'to' => 'island_a'],
            ],
        ]))->toThrow(InvalidWorkflowDefinitionException::class, 'unreachable');
    });

    test('the error names the unreachable steps', function () {
        try {
            $this->parser->parse([
                'name' => 'Island',
                'steps' => [
                    ['id' => 'start', 'action' => LogAction::class],
                    ['id' => 'finish', 'action' => LogAction::class],
                    ['id' => 'lost_a', 'action' => LogAction::class],
                    ['id' => 'lost_b', 'action' => LogAction::class],
                ],
                'transitions' => [
                    ['from' => 'start', 'to' => 'finish'],
                    ['from' => 'lost_a', 'to' => 'lost_b'],
                    ['from' => 'lost_b', 'to' => 'lost_a'],
                ],
            ]);

            $this->fail('Expected InvalidWorkflowDefinitionException');
        } catch (InvalidWorkflowDefinitionException $e) {
            expect($e->getMessage())->toContain("'lost_a'");
            expect($e->getMessage())->toContain("'lost_b'");
        }
    });

    test('a standalone step with no transitions is an entry point, not an orphan', function () {
        $definition = $this->parser->parse([
            'name' => 'Parallel Branch',
            'steps' => [
                ['id' => 'start', 'action' => LogAction::class],
                ['id' => 'middle', 'action' => LogAction::class],
                ['id' => 'standalone', 'action' => LogAction::class],
            ],
            'transitions' => [
                ['from' => 'start', 'to' => 'middle'],
            ],
        ]);

        $ids = array_map(fn ($step) => $step->getId(), $definition->getFirstSteps());

        expect($ids)->toEqualCanonicalizing(['start', 'standalone']);
    });

    test('a converging graph is reachable and accepted', function () {
        $definition = $this->parser->parse([
            'name' => 'Diamond',
            'steps' => [
                ['id' => 'start', 'action' => LogAction::class],
                ['id' => 'left', 'action' => LogAction::class],
                ['id' => 'right', 'action' => LogAction::class],
                ['id' => 'end', 'action' => LogAction::class],
            ],
            'transitions' => [
                ['from' => 'start', 'to' => 'left'],
                ['from' => 'start', 'to' => 'right'],
                ['from' => 'left', 'to' => 'end'],
                ['from' => 'right', 'to' => 'end'],
            ],
        ]);

        expect($definition->getSteps())->toHaveCount(4);
    });

    test('multiple roots are all treated as reachable', function () {
        $definition = $this->parser->parse([
            'name' => 'Two Roots',
            'steps' => [
                ['id' => 'root_a', 'action' => LogAction::class],
                ['id' => 'root_b', 'action' => LogAction::class],
                ['id' => 'join', 'action' => LogAction::class],
            ],
            'transitions' => [
                ['from' => 'root_a', 'to' => 'join'],
                ['from' => 'root_b', 'to' => 'join'],
            ],
        ]);

        expect($definition->getSteps())->toHaveCount(3);
    });

    test('a workflow without transitions is not subject to the check', function () {
        $definition = $this->parser->parse([
            'name' => 'Single',
            'steps' => [
                ['id' => 'only', 'action' => LogAction::class],
            ],
        ]);

        expect($definition->getSteps())->toHaveCount(1);
    });

});

describe('blocked workflows', function () {

    /**
     * A step gated on a prerequisite that never completes can never run. The
     * executor used to return leaving the instance in RUNNING, where a
     * permanently stuck workflow looks exactly like one still in flight.
     */
    test('a workflow blocked on an unmet prerequisite parks in WAITING', function () {
        $definition = [
            'name' => 'Blocked',
            'steps' => [
                ['id' => 'first', 'action' => LogAction::class, 'config' => ['message' => 'go']],
                [
                    'id' => 'second',
                    'action' => LogAction::class,
                    'config' => ['message' => 'never'],
                    'prerequisites' => ['first', 'absent_step'],
                ],
            ],
            'transitions' => [
                ['from' => 'first', 'to' => 'second'],
            ],
        ];

        $this->engine->start('blocked', $definition);
        $instance = $this->engine->getInstance('blocked');

        expect($instance->getState())->toBe(WorkflowState::WAITING);
        expect($instance->getCompletedSteps())->toContain('first');
        expect($instance->getCompletedSteps())->not->toContain('second');
    });

});
