<?php

use SolutionForest\WorkflowEngine\Actions\ConditionAction;
use SolutionForest\WorkflowEngine\Actions\HttpAction;
use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Core\DefinitionParser;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\WorkflowDefinition;
use SolutionForest\WorkflowEngine\Core\WorkflowInstance;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowStateException;

/**
 * Malformed input that used to be silently coerced (or crashed with a
 * TypeError) is now rejected with a clear, typed error.
 */
function malformedContext(): WorkflowContext
{
    return new WorkflowContext(workflowId: 'wf', stepId: 'step', data: ['user' => ['name' => 'Ada']]);
}

describe('built-in actions reject malformed config', function () {
    test('ConditionAction fails when the condition is not a string', function () {
        $result = (new ConditionAction(['condition' => ['user.name']]))->execute(malformedContext());

        expect($result->isSuccess())->toBeFalse();
        expect($result->getErrorMessage())->toBe('Condition must be a string expression');
    });

    test('LogAction fails when message or level is not a string', function () {
        $badMessage = (new LogAction(['message' => ['not', 'text']]))->execute(malformedContext());
        $badLevel = (new LogAction(['message' => 'hi', 'level' => 3]))->execute(malformedContext());

        expect($badMessage->isSuccess())->toBeFalse();
        expect($badMessage->getErrorMessage())->toBe('Log "message" must be a string');
        expect($badLevel->isSuccess())->toBeFalse();
        expect($badLevel->getErrorMessage())->toBe('Log "level" must be a string');
    });

    test('LogAction still interpolates placeholders for valid input', function () {
        $result = (new LogAction(['message' => 'Hello {user.name}']))->execute(malformedContext());

        expect($result->isSuccess())->toBeTrue();
        expect($result->getData()['logged_message'])->toBe('Hello Ada');
    });

    test('HttpAction fails on config of the wrong type', function (array $config, string $message) {
        $result = (new HttpAction($config))->execute(malformedContext());

        expect($result->isSuccess())->toBeFalse();
        expect($result->getErrorMessage())->toBe($message);
    })->with([
        'url' => [['url' => ['x']], 'HTTP action "url" must be a string'],
        'method' => [['url' => 'https://example.test', 'method' => 5], 'HTTP action "method" must be a non-empty string'],
        'data' => [['url' => 'https://example.test', 'data' => 'a=b'], 'HTTP action "data" must be an array'],
        'headers' => [['url' => 'https://example.test', 'headers' => 'X-A: b'], 'HTTP action "headers" must be an array'],
        'header value' => [['url' => 'https://example.test', 'headers' => ['X-A' => ['b']]], 'HTTP action header "X-A" must be a scalar value'],
        'timeout' => [['url' => 'https://example.test', 'timeout' => 'soon'], 'HTTP action "timeout" must be an integer'],
        'connect_timeout' => [['url' => 'https://example.test', 'connect_timeout' => []], 'HTTP action "connect_timeout" must be an integer'],
        'max_redirects' => [['url' => 'https://example.test', 'max_redirects' => '3x'], 'HTTP action "max_redirects" must be an integer'],
    ]);
});

describe('DefinitionParser rejects malformed definitions', function () {
    test('JSON that is not an object', function () {
        expect(fn () => (new DefinitionParser)->parse('"just a string"'))
            ->toThrow(InvalidWorkflowDefinitionException::class, 'the document must be a JSON object');
    });

    test('a non-string name', function () {
        expect(fn () => (new DefinitionParser)->parse(['name' => ['x'], 'steps' => [['id' => 'a']]]))
            ->toThrow(InvalidWorkflowDefinitionException::class, "Invalid workflow name: 'array'");
    });

    test('a transition that is not an array', function () {
        expect(fn () => (new DefinitionParser)->parse([
            'name' => 'wf',
            'steps' => [['id' => 'a']],
            'transitions' => ['a->b'],
        ]))->toThrow(InvalidWorkflowDefinitionException::class, 'Transition at index 0 must be an array');
    });

    test('a step with non-string keys', function () {
        expect(fn () => (new DefinitionParser)->parse([
            'name' => 'wf',
            'steps' => [['id' => 'a', 0 => 'log']],
        ]))->toThrow(InvalidWorkflowDefinitionException::class, 'must be an associative array of step fields');
    });

    test('a non-string step id', function () {
        expect(fn () => (new DefinitionParser)->parse([
            'name' => 'wf',
            'steps' => [['id' => ['a']]],
        ]))->toThrow(InvalidWorkflowDefinitionException::class, "Invalid step ID: 'array'");
    });

    test('a non-integer retry_attempts', function () {
        expect(fn () => (new DefinitionParser)->parse([
            'name' => 'wf',
            'steps' => [['id' => 'a', 'retry_attempts' => '3']],
        ]))->toThrow(InvalidWorkflowDefinitionException::class, "Step 'a' retry_attempts must be an integer");
    });

    test('a non-string step condition', function () {
        expect(fn () => (new DefinitionParser)->parse([
            'name' => 'wf',
            'steps' => [['id' => 'a', 'conditions' => [42]]],
        ]))->toThrow(InvalidWorkflowDefinitionException::class, "Invalid condition expression: '42'");
    });
});

describe('WorkflowDefinition::fromArray rejects malformed data', function () {
    test('throws a clear exception for each malformed field', function (array $data, string $message) {
        expect(fn () => WorkflowDefinition::fromArray($data))
            ->toThrow(InvalidWorkflowDefinitionException::class, $message);
    })->with([
        'missing name' => [['steps' => []], 'Workflow definition name must be a string'],
        'version' => [['name' => 'wf', 'version' => 2], 'Workflow version must be a string'],
        'metadata' => [['name' => 'wf', 'metadata' => 'x'], 'Workflow metadata must be an associative array'],
        'steps' => [['name' => 'wf', 'steps' => 'a'], 'Workflow definition steps must be an array'],
        'step entry' => [['name' => 'wf', 'steps' => ['a']], 'Step at index 0 must be a Step or an associative array'],
        'step id' => [['name' => 'wf', 'steps' => [['id' => 1.5]]], "Invalid step ID: '1.5'"],
        'action' => [['name' => 'wf', 'steps' => ['a' => ['action' => 5]]], 'action must be a string'],
        'config' => [['name' => 'wf', 'steps' => ['a' => ['config' => 'x']]], 'parameters/config must be an associative array'],
        'timeout' => [['name' => 'wf', 'steps' => ['a' => ['timeout' => 1.5]]], 'timeout must be a string'],
        'retry_attempts' => [['name' => 'wf', 'steps' => ['a' => ['retry_attempts' => '2']]], 'retry_attempts must be an integer'],
        'conditions' => [['name' => 'wf', 'steps' => ['a' => ['conditions' => [1]]]], 'conditions must be an array of strings'],
        'prerequisites' => [['name' => 'wf', 'steps' => ['a' => ['prerequisites' => 'b']]], 'prerequisites must be an array of step IDs'],
        'transitions' => [['name' => 'wf', 'transitions' => 'a->b'], 'Workflow transitions must be an array'],
        'transition fields' => [['name' => 'wf', 'transitions' => [['from' => 'a']]], "Transition at index 0 must have string 'from' and 'to' fields"],
        'transition condition' => [['name' => 'wf', 'transitions' => [['from' => 'a', 'to' => 'b', 'condition' => true]]], "Transition at index 0 must have string 'from' and 'to' fields"],
    ]);

    test('keeps integer timeouts and positional ids as strings, as before', function () {
        $definition = WorkflowDefinition::fromArray([
            'name' => 'wf',
            'steps' => [['timeout' => 30]],
        ]);

        expect($definition->getStep('0')?->getTimeout())->toBe('30');
    });

    test('preserves extra transition keys such as metadata', function () {
        $definition = WorkflowDefinition::fromArray([
            'name' => 'wf',
            'steps' => ['a' => [], 'b' => []],
            'transitions' => [['from' => 'a', 'to' => 'b', 'metadata' => ['x' => 1]]],
        ]);

        expect($definition->getTransitions())->toBe([['from' => 'a', 'to' => 'b', 'metadata' => ['x' => 1]]]);
    });
});

describe('WorkflowInstance::fromArray rejects malformed fields', function () {
    beforeEach(function () {
        $this->definition = (new DefinitionParser)->parse([
            'name' => 'restore-malformed',
            'steps' => [['id' => 'a', 'action' => 'log']],
        ]);
        $this->payload = [
            'id' => 'inst-1',
            'state' => 'running',
            'completed_steps' => [],
            'failed_steps' => [],
            'created_at' => '2026-01-01T00:00:00+00:00',
            'updated_at' => '2026-01-01T00:00:00+00:00',
        ];
    });

    test('throws a clear exception for each malformed field', function (array $override, string $message) {
        expect(fn () => WorkflowInstance::fromArray(array_merge($this->payload, $override), $this->definition))
            ->toThrow(InvalidWorkflowStateException::class, $message);
    })->with([
        'failed step entry' => [['failed_steps' => [['step_id' => 'a']]], 'every "failed_steps" entry needs string'],
        'data' => [['data' => 'x'], '"data" must be an associative array'],
        'error_message' => [['error_message' => ['boom']], '"error_message" must be a string or null'],
        'created_at' => [['created_at' => 1700000000], '"created_at" must be a date-time string'],
    ]);

    test('restores well-formed failed steps', function () {
        $failure = ['step_id' => 'a', 'error' => 'boom', 'failed_at' => '2026-01-01T00:00:00+00:00'];

        $instance = WorkflowInstance::fromArray(array_merge($this->payload, ['failed_steps' => [$failure]]), $this->definition);

        expect($instance->getFailedSteps())->toBe([$failure]);
    });

    test('defaults missing timestamps to now', function () {
        $payload = $this->payload;
        unset($payload['created_at'], $payload['updated_at']);

        $instance = WorkflowInstance::fromArray($payload, $this->definition);

        expect(abs($instance->getCreatedAt()->getTimestamp() - time()))->toBeLessThan(5);
    });
});
