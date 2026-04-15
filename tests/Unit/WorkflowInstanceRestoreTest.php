<?php

use SolutionForest\WorkflowEngine\Core\DefinitionParser;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Core\WorkflowState;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;

beforeEach(function () {
    $parser = new DefinitionParser;
    $this->definition = $parser->parse([
        'name' => 'restore-test',
        'steps' => [
            ['id' => 'a', 'action' => 'log'],
            ['id' => 'b', 'action' => 'log'],
        ],
        'transitions' => [
            ['from' => 'a', 'to' => 'b'],
        ],
    ]);
});

describe('WorkflowInstance::fromArray validation', function () {
    test('restores a valid payload round-trip', function () {
        $restored = WorkflowInstance::fromArray([
            'id' => 'inst-1',
            'state' => 'running',
            'data' => ['foo' => 'bar'],
            'current_step_id' => 'b',
            'completed_steps' => ['a'],
            'failed_steps' => [],
            'error_message' => null,
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-02T00:00:00+00:00',
        ], $this->definition);

        expect($restored->getId())->toBe('inst-1');
        expect($restored->getState())->toBe(WorkflowState::RUNNING);
        expect($restored->getCurrentStepId())->toBe('b');
        expect($restored->getCompletedSteps())->toBe(['a']);
    });

    test('rejects payloads with unknown state values', function () {
        expect(fn () => WorkflowInstance::fromArray([
            'id' => 'inst-bad-state',
            'state' => 'not-a-real-state',
            'completed_steps' => [],
            'failed_steps' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ], $this->definition))->toThrow(InvalidWorkflowStateException::class);
    });

    test('rejects payloads that reference unknown completed steps', function () {
        expect(fn () => WorkflowInstance::fromArray([
            'id' => 'inst-bad-step',
            'state' => 'running',
            'completed_steps' => ['a', 'ghost_step'],
            'failed_steps' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ], $this->definition))->toThrow(InvalidWorkflowStateException::class);
    });

    test('rejects payloads with a current step that is not defined', function () {
        expect(fn () => WorkflowInstance::fromArray([
            'id' => 'inst-bad-current',
            'state' => 'running',
            'current_step_id' => 'nonexistent',
            'completed_steps' => [],
            'failed_steps' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ], $this->definition))->toThrow(InvalidWorkflowStateException::class);
    });

    test('rejects payloads missing required id field', function () {
        expect(fn () => WorkflowInstance::fromArray([
            'state' => 'running',
            'completed_steps' => [],
            'failed_steps' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ], $this->definition))->toThrow(InvalidWorkflowStateException::class);
    });

    test('rejects payloads whose completed_steps is not a list', function () {
        expect(fn () => WorkflowInstance::fromArray([
            'id' => 'inst-non-list',
            'state' => 'running',
            'completed_steps' => ['a' => true],
            'failed_steps' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ], $this->definition))->toThrow(InvalidWorkflowStateException::class);
    });
});
