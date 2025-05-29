<?php

use SolutionForest\WorkflowEngine\Actions\DelayAction;
use SolutionForest\WorkflowEngine\Actions\LogAction;
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;
use SolutionForest\WorkflowEngine\Core\WorkflowState;

describe('PHP 8.3+ Features', function () {

    it('can use enhanced workflow state enum methods', function () {
        $state = WorkflowState::RUNNING;

        expect($state->color())->toBe('blue');
        expect($state->icon())->toBe('▶️');
        expect($state->label())->toBe('Running');
        expect($state->canTransitionTo(WorkflowState::COMPLETED))->toBeTrue();
        expect($state->canTransitionTo(WorkflowState::PENDING))->toBeFalse();
    });

    it('can create workflow with fluent builder API', function () {
        $workflow = WorkflowBuilder::create('test-workflow')
            ->description('Test workflow with fluent API')
            ->version('2.0')
            ->startWith(LogAction::class, ['message' => 'Starting workflow'])
            ->then(DelayAction::class, ['seconds' => 1])
            ->email(
                template: 'test',
                to: '{{ user.email }}',
                subject: 'Test Email'
            )
            ->withMetadata(['created_by' => 'test'])
            ->build();

        expect($workflow->getName())->toBe('test-workflow');
        expect($workflow->getVersion())->toBe('2.0');
        expect($workflow->getMetadata())->toHaveKey('description');
        expect($workflow->getMetadata())->toHaveKey('created_by');
        expect($workflow->getSteps())->toHaveCount(3);
    });

    it('can use conditional workflow building', function () {
        $workflow = WorkflowBuilder::create('conditional-test')
            ->startWith(LogAction::class, ['message' => 'Start'])
            ->when('user.premium', function ($builder) {
                $builder->then(LogAction::class, ['message' => 'Premium user step']);
            })
            ->then(LogAction::class, ['message' => 'Final step'])
            ->build();

        expect($workflow->getSteps())->toHaveCount(3);
    });

    it('can create quick template workflows', function () {
        $builder = WorkflowBuilder::quick()->userOnboarding();
        $workflow = $builder->build();

        expect($workflow->getName())->toBe('user-onboarding');
        expect($workflow->getSteps())->not()->toBeEmpty();
    });

    it('validates state transitions correctly', function () {
        // Test valid transitions
        expect(WorkflowState::PENDING->canTransitionTo(WorkflowState::RUNNING))->toBeTrue();
        expect(WorkflowState::RUNNING->canTransitionTo(WorkflowState::COMPLETED))->toBeTrue();
        expect(WorkflowState::RUNNING->canTransitionTo(WorkflowState::FAILED))->toBeTrue();

        // Test invalid transitions
        expect(WorkflowState::COMPLETED->canTransitionTo(WorkflowState::RUNNING))->toBeFalse();
        expect(WorkflowState::FAILED->canTransitionTo(WorkflowState::RUNNING))->toBeFalse();
        expect(WorkflowState::CANCELLED->canTransitionTo(WorkflowState::RUNNING))->toBeFalse();
    });

    it('provides UI-friendly state information', function () {
        $testCases = [
            [WorkflowState::PENDING, 'gray', '⏳', 'Pending'],
            [WorkflowState::RUNNING, 'blue', '▶️', 'Running'],
            [WorkflowState::COMPLETED, 'green', '✅', 'Completed'],
            [WorkflowState::FAILED, 'red', '❌', 'Failed'],
        ];

        foreach ($testCases as [$state, $expectedColor, $expectedIcon, $expectedLabel]) {
            expect($state->color())->toBe($expectedColor);
            expect($state->icon())->toBe($expectedIcon);
            expect($state->label())->toBe($expectedLabel);
        }
    });

});

describe('Simplified Learning Curve', function () {

    it('can create workflow with common patterns using helper methods', function () {
        $workflow = WorkflowBuilder::create('helper-test')
            ->email(
                template: 'welcome',
                to: 'user@example.com',
                subject: 'Welcome!'
            )
            ->delay(minutes: 5)
            ->http(
                url: 'https://api.example.com/webhook',
                method: 'POST',
                data: ['event' => 'user_registered']
            )
            ->condition('user.verified')
            ->build();

        $steps = array_values($workflow->getSteps()); // Convert to numeric array
        expect($steps)->toHaveCount(4);

        // Check email step
        expect($steps[0]->getActionClass())->toBe('SolutionForest\\WorkflowEngine\\Actions\\EmailAction');
        expect($steps[0]->getConfig()['template'])->toBe('welcome');

        // Check delay step
        expect($steps[1]->getActionClass())->toBe('SolutionForest\\WorkflowEngine\\Actions\\DelayAction');

        // Check HTTP step
        expect($steps[2]->getActionClass())->toBe('SolutionForest\\WorkflowEngine\\Actions\\HttpAction');
        expect($steps[2]->getConfig()['method'])->toBe('POST');

        // Check condition step
        expect($steps[3]->getActionClass())->toBe('SolutionForest\\WorkflowEngine\\Actions\\ConditionAction');
        expect($steps[3]->getConfig()['condition'])->toBe('user.verified');
    });

    it('provides quick workflow templates', function () {
        $templates = ['userOnboarding', 'orderProcessing', 'documentApproval'];

        foreach ($templates as $template) {
            $workflow = WorkflowBuilder::quick()->$template()->build();
            expect($workflow->getSteps())->not()->toBeEmpty();
            expect($workflow->getMetadata()['description'])->not()->toBeEmpty();
        }
    });

    it('can use named arguments for better readability', function () {
        // This test demonstrates the improved API readability
        // The actual testing is implicit in the successful execution
        $workflow = WorkflowBuilder::create(name: 'named-args-test')
            ->description(description: 'Testing named arguments')
            ->version(version: '1.0')
            ->email(
                template: 'test',
                to: 'test@example.com',
                subject: 'Test Subject',
                data: ['key' => 'value']
            )
            ->delay(
                minutes: 5
            )
            ->build();

        expect($workflow->getName())->toBe('named-args-test');
        expect($workflow->getVersion())->toBe('1.0');
    });

});
