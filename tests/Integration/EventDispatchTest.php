<?php

use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Tests\Support\InMemoryStorage;
use SolutionForest\WorkflowEngine\Tests\Support\SpyEventDispatcher;

describe('Event Dispatching', function () {
    test('dispatches workflow started event on start', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = [
            'name' => 'test-workflow',
            'version' => '1.0',
            'steps' => [
                [
                    'id' => 'step1',
                    'action' => 'log',
                    'parameters' => ['message' => 'hello', 'level' => 'info'],
                ],
            ],
        ];

        $engine->start('event-test-1', $definition, ['key' => 'value']);

        // Check that at least one event was dispatched
        expect($spy->dispatched)->not->toBeEmpty();

        // First event should be workflow started
        $startEvents = array_filter($spy->dispatched, fn ($e) => str_contains(get_class($e), 'WorkflowStarted')
        );
        expect($startEvents)->not->toBeEmpty();
    });

    test('dispatches step completed event after step execution', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = [
            'name' => 'test-workflow',
            'version' => '1.0',
            'steps' => [
                [
                    'id' => 'step1',
                    'action' => 'log',
                    'parameters' => ['message' => 'test', 'level' => 'info'],
                ],
            ],
        ];

        $engine->start('event-test-2', $definition);

        $stepEvents = array_filter($spy->dispatched, fn ($e) => str_contains(get_class($e), 'StepCompleted')
        );
        expect($stepEvents)->not->toBeEmpty();
    });

    test('dispatches workflow completed event when all steps finish', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = [
            'name' => 'test-workflow',
            'version' => '1.0',
            'steps' => [
                [
                    'id' => 'step1',
                    'action' => 'log',
                    'parameters' => ['message' => 'test', 'level' => 'info'],
                ],
            ],
        ];

        $engine->start('event-test-3', $definition);

        $completedEvents = array_filter($spy->dispatched, fn ($e) => str_contains(get_class($e), 'WorkflowCompleted')
        );
        expect($completedEvents)->not->toBeEmpty();
    });

    test('dispatches cancelled event on cancel', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = [
            'name' => 'test-workflow',
            'version' => '1.0',
            'steps' => [
                [
                    'id' => 'step1',
                    'action' => 'log',
                    'parameters' => ['message' => 'test', 'level' => 'info'],
                ],
                [
                    'id' => 'step2',
                    'action' => 'log',
                    'parameters' => ['message' => 'test2', 'level' => 'info'],
                ],
            ],
            'transitions' => [
                ['from' => 'step1', 'to' => 'step2'],
            ],
        ];

        $id = $engine->start('event-test-4', $definition);

        $spy->reset();
        $engine->cancel($id, 'testing cancellation');

        $cancelEvents = array_filter($spy->dispatched, fn ($e) => str_contains(get_class($e), 'WorkflowCancelled') || str_contains(get_class($e), 'Cancelled')
        );
        expect($cancelEvents)->not->toBeEmpty();
    });

    test('dispatches events in correct order for full workflow', function () {
        $storage = new InMemoryStorage;
        $spy = new SpyEventDispatcher;
        $engine = new WorkflowEngine($storage, $spy);

        $definition = [
            'name' => 'test-workflow',
            'version' => '1.0',
            'steps' => [
                [
                    'id' => 'step1',
                    'action' => 'log',
                    'parameters' => ['message' => 'first', 'level' => 'info'],
                ],
            ],
        ];

        $engine->start('event-test-5', $definition);

        // Should have at least: WorkflowStarted, StepCompleted, WorkflowCompleted
        expect(count($spy->dispatched))->toBeGreaterThanOrEqual(3);

        // First event should be start, last should be workflow completed
        $classNames = array_map(fn ($e) => get_class($e), $spy->dispatched);

        // First is WorkflowStarted(Event)
        expect($classNames[0])->toContain('WorkflowStarted');

        // Last is WorkflowCompleted
        expect(end($classNames))->toContain('WorkflowCompleted');
    });
});
