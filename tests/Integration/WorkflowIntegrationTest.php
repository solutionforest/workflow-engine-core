<?php

use SolutionForest\WorkflowEngine\Core\WorkflowState;

test('it can execute a complete workflow', function () {
    // Create a more complex workflow with multiple steps
    $definition = [
        'name' => 'User Onboarding Workflow',
        'version' => '1.0',
        'steps' => [
            [
                'id' => 'welcome',
                'name' => 'Welcome User',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Welcome {{name}} to our platform!',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'setup_profile',
                'name' => 'Setup User Profile',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Setting up profile for {{name}} with email {{email}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'send_confirmation',
                'name' => 'Send Confirmation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Sending confirmation email to {{email}}',
                    'level' => 'info',
                ],
            ],
        ],
        'transitions' => [
            [
                'from' => 'welcome',
                'to' => 'setup_profile',
            ],
            [
                'from' => 'setup_profile',
                'to' => 'send_confirmation',
            ],
        ],
    ];

    $context = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'userId' => 123,
    ];

    // Start the workflow using helper function
    $workflowId = $this->engine->start('user-onboarding-123', $definition, $context);

    // Verify workflow was created and completed
    expect($workflowId)->not->toBeEmpty();
    expect($workflowId)->toBe('user-onboarding-123');

    // Get workflow instance using helper
    $instance = $this->engine->getInstance($workflowId);

    // Verify the workflow completed successfully
    expect($instance->getState())->toBe(WorkflowState::COMPLETED);
    expect($instance->getName())->toBe('User Onboarding Workflow');

    // Verify the context contains original data plus step outputs
    $workflowData = $instance->getContext()->getData();
    expect($workflowData['name'])->toBe('John Doe');
    expect($workflowData['email'])->toBe('john@example.com');
    expect($workflowData['userId'])->toBe(123);

    // Verify that all steps completed successfully
    expect($instance->getCompletedSteps())->toHaveCount(3);
    expect($instance->getCompletedSteps())->toContain('welcome');
    expect($instance->getCompletedSteps())->toContain('setup_profile');
    expect($instance->getCompletedSteps())->toContain('send_confirmation');

    // Verify no failed steps
    expect($instance->getFailedSteps())->toBeEmpty();

    // Check workflow status
    $status = $this->engine->getStatus($workflowId);
    expect($status['state'])->toBe('completed');
    expect($status['name'])->toBe('User Onboarding Workflow');
    expect($status['progress'])->toBe(100.0); // 100% complete
});

test('it can handle workflow cancellation', function () {
    $definition = [
        'name' => 'Cancellable Workflow',
        'steps' => [
            [
                'id' => 'step1',
                'name' => 'First Step',
                'action' => 'log',
                'parameters' => ['message' => 'Starting process'],
            ],
        ],
    ];

    // Start workflow
    $workflowId = $this->engine->start('cancellable-workflow', $definition);

    // Cancel workflow
    $this->engine->cancel($workflowId, 'User requested cancellation');

    // Verify cancellation
    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getState())->toBe(WorkflowState::CANCELLED);
});

test('it can list and filter workflows', function () {
    $definition1 = [
        'name' => 'Workflow 1',
        'steps' => [
            ['id' => 'step1', 'action' => 'log', 'parameters' => ['message' => 'Test']],
        ],
    ];

    $definition2 = [
        'name' => 'Workflow 2',
        'steps' => [
            ['id' => 'step1', 'action' => 'log', 'parameters' => ['message' => 'Test']],
        ],
    ];

    // Start two workflows
    $workflow1Id = $this->engine->start('list-test-1', $definition1);
    $workflow2Id = $this->engine->start('list-test-2', $definition2);

    // Cancel one
    $this->engine->cancel($workflow2Id);

    // List all workflows
    $allWorkflows = $this->engine->listWorkflows();
    expect(count($allWorkflows))->toBeGreaterThanOrEqual(2);

    // Filter by state
    $completedWorkflows = $this->engine->listWorkflows(['state' => WorkflowState::COMPLETED]);
    $cancelledWorkflows = $this->engine->listWorkflows(['state' => WorkflowState::CANCELLED]);

    expect(count($completedWorkflows))->toBeGreaterThanOrEqual(1);
    expect(count($cancelledWorkflows))->toBeGreaterThanOrEqual(1);

    // Verify specific workflows exist in filtered results
    $completedIds = array_column($completedWorkflows, 'workflow_id');
    $cancelledIds = array_column($cancelledWorkflows, 'workflow_id');

    expect($completedIds)->toContain($workflow1Id);
    expect($cancelledIds)->toContain($workflow2Id);
});

test('it can execute conditional workflows', function () {
    $definition = [
        'name' => 'Conditional Approval Workflow',
        'version' => '1.0',
        'steps' => [
            [
                'id' => 'validate_request',
                'name' => 'Validate Request',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Validating request from {{user}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'auto_approve',
                'name' => 'Auto Approve',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Auto-approving request for premium user {{user}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'manual_review',
                'name' => 'Manual Review Required',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Manual review required for user {{user}}',
                    'level' => 'warning',
                ],
            ],
            [
                'id' => 'notify_completion',
                'name' => 'Notify Completion',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Process completed for {{user}}',
                    'level' => 'info',
                ],
            ],
        ],
        'transitions' => [
            [
                'from' => 'validate_request',
                'to' => 'auto_approve',
                'condition' => 'tier === premium',
            ],
            [
                'from' => 'validate_request',
                'to' => 'manual_review',
                'condition' => 'tier !== premium',
            ],
            [
                'from' => 'auto_approve',
                'to' => 'notify_completion',
            ],
            [
                'from' => 'manual_review',
                'to' => 'notify_completion',
            ],
        ],
    ];

    // Test premium user path (should auto-approve)
    $premiumContext = [
        'user' => 'Alice Premium',
        'tier' => 'premium',
        'amount' => 1000,
    ];

    $premiumWorkflowId = $this->engine->start('premium-approval-123', $definition, $premiumContext);
    $premiumInstance = $this->engine->getInstance($premiumWorkflowId);

    // Verify premium workflow took auto-approval path
    expect($premiumInstance->getState())->toBe(WorkflowState::COMPLETED);
    expect($premiumInstance->getCompletedSteps())->toContain('validate_request');
    expect($premiumInstance->getCompletedSteps())->toContain('auto_approve');
    expect($premiumInstance->getCompletedSteps())->toContain('notify_completion');
    expect($premiumInstance->getCompletedSteps())->not->toContain('manual_review');

    // Test regular user path (should require manual review)
    $regularContext = [
        'user' => 'Bob Regular',
        'tier' => 'basic',
        'amount' => 5000,
    ];

    $regularWorkflowId = $this->engine->start('regular-approval-456', $definition, $regularContext);
    $regularInstance = $this->engine->getInstance($regularWorkflowId);

    // Verify regular workflow took manual review path
    expect($regularInstance->getState())->toBe(WorkflowState::COMPLETED);
    expect($regularInstance->getCompletedSteps())->toContain('validate_request');
    expect($regularInstance->getCompletedSteps())->toContain('manual_review');
    expect($regularInstance->getCompletedSteps())->toContain('notify_completion');
    expect($regularInstance->getCompletedSteps())->not->toContain('auto_approve');
});
