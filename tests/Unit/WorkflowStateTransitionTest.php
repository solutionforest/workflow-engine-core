<?php

use SolutionForest\WorkflowEngine\Core\WorkflowDefinition;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;

describe('WorkflowState Transitions', function () {
    // Helper to create a minimal instance in a given state
    function createInstance(WorkflowState $state): WorkflowInstance
    {
        $definition = new WorkflowDefinition(
            name: 'test',
            version: '1.0',
            steps: [['id' => 'step1', 'action' => 'log', 'parameters' => ['message' => 'test', 'level' => 'info']]],
        );

        return new WorkflowInstance(
            id: 'test-'.uniqid(),
            definition: $definition,
            state: $state,
        );
    }

    describe('valid transitions', function () {
        test('PENDING can transition to RUNNING', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            expect($instance->getState())->toBe(WorkflowState::RUNNING);
        });

        test('PENDING can transition to FAILED', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::FAILED);
            expect($instance->getState())->toBe(WorkflowState::FAILED);
        });

        test('PENDING can transition to CANCELLED', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::CANCELLED);
            expect($instance->getState())->toBe(WorkflowState::CANCELLED);
        });

        test('RUNNING can transition to COMPLETED', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::COMPLETED);
            expect($instance->getState())->toBe(WorkflowState::COMPLETED);
        });

        test('RUNNING can transition to FAILED', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::FAILED);
            expect($instance->getState())->toBe(WorkflowState::FAILED);
        });

        test('RUNNING can transition to PAUSED', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::PAUSED);
            expect($instance->getState())->toBe(WorkflowState::PAUSED);
        });

        test('RUNNING can transition to WAITING', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::WAITING);
            expect($instance->getState())->toBe(WorkflowState::WAITING);
        });

        test('RUNNING can transition to CANCELLED', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::CANCELLED);
            expect($instance->getState())->toBe(WorkflowState::CANCELLED);
        });

        test('PAUSED can transition to RUNNING', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::PAUSED);
            $instance->setState(WorkflowState::RUNNING);
            expect($instance->getState())->toBe(WorkflowState::RUNNING);
        });

        test('WAITING can transition to RUNNING', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::WAITING);
            $instance->setState(WorkflowState::RUNNING);
            expect($instance->getState())->toBe(WorkflowState::RUNNING);
        });
    });

    describe('invalid transitions', function () {
        test('COMPLETED cannot transition to RUNNING', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::RUNNING);
            $instance->setState(WorkflowState::COMPLETED);

            expect(fn () => $instance->setState(WorkflowState::RUNNING))
                ->toThrow(InvalidWorkflowStateException::class);
        });

        test('FAILED cannot transition to RUNNING', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::FAILED);

            expect(fn () => $instance->setState(WorkflowState::RUNNING))
                ->toThrow(InvalidWorkflowStateException::class);
        });

        test('CANCELLED cannot transition to any state', function () {
            $instance = createInstance(WorkflowState::PENDING);
            $instance->setState(WorkflowState::CANCELLED);

            expect(fn () => $instance->setState(WorkflowState::RUNNING))
                ->toThrow(InvalidWorkflowStateException::class);
        });

        test('PENDING cannot transition to COMPLETED directly', function () {
            $instance = createInstance(WorkflowState::PENDING);

            expect(fn () => $instance->setState(WorkflowState::COMPLETED))
                ->toThrow(InvalidWorkflowStateException::class);
        });

        test('PENDING cannot transition to PAUSED directly', function () {
            $instance = createInstance(WorkflowState::PENDING);

            expect(fn () => $instance->setState(WorkflowState::PAUSED))
                ->toThrow(InvalidWorkflowStateException::class);
        });
    });
});
