<?php

use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;

beforeEach(function () {
    $this->engine = new WorkflowEngine($this->storage);
});

test('cicd pipeline workflow - successful deployment flow', function () {
    $definition = [
        'name' => 'CI/CD Pipeline Workflow',
        'version' => '3.0',
        'steps' => [
            [
                'id' => 'checkout_code',
                'name' => 'Code Checkout',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Checking out code from {{pipeline.repository}} branch {{pipeline.branch}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'run_unit_tests',
                'name' => 'Unit Tests',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running unit tests for {{pipeline.project_name}}',
                    'timeout' => '10m',
                    'parallel_group' => 'tests',
                ],
            ],
            [
                'id' => 'security_scan',
                'name' => 'Security Scan',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running security vulnerability scan',
                    'timeout' => '15m',
                    'parallel_group' => 'tests',
                ],
            ],
            [
                'id' => 'run_integration_tests',
                'name' => 'Integration Tests',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running integration tests for {{pipeline.project_name}}',
                    'timeout' => '20m',
                ],
            ],
            [
                'id' => 'build_artifacts',
                'name' => 'Build Artifacts',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Building deployment artifacts for {{pipeline.project_name}}',
                    'timeout' => '30m',
                ],
            ],
            [
                'id' => 'deploy_staging',
                'name' => 'Deploy to Staging',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Deploying {{pipeline.project_name}} to staging environment',
                    'compensation' => 'rollback_staging',
                ],
            ],
            [
                'id' => 'run_e2e_tests',
                'name' => 'End-to-End Tests',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running E2E tests on staging environment',
                    'timeout' => '45m',
                ],
            ],
            [
                'id' => 'approval_gate',
                'name' => 'Production Approval Gate',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Awaiting production deployment approval for {{pipeline.project_name}}',
                    'timeout' => '24h',
                    'assigned_to' => 'release_manager',
                ],
            ],
            [
                'id' => 'deploy_production',
                'name' => 'Deploy to Production',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Deploying {{pipeline.project_name}} to production environment',
                    'compensation' => 'rollback_production',
                ],
            ],
            [
                'id' => 'smoke_tests',
                'name' => 'Production Smoke Tests',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running smoke tests on production deployment',
                    'timeout' => '5m',
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'checkout_code', 'to' => 'run_unit_tests'],
            ['from' => 'checkout_code', 'to' => 'security_scan'],
            ['from' => 'run_unit_tests', 'to' => 'run_integration_tests'],
            ['from' => 'security_scan', 'to' => 'run_integration_tests'],
            ['from' => 'run_integration_tests', 'to' => 'build_artifacts'],
            ['from' => 'build_artifacts', 'to' => 'deploy_staging'],
            ['from' => 'deploy_staging', 'to' => 'run_e2e_tests'],
            ['from' => 'run_e2e_tests', 'to' => 'approval_gate'],
            ['from' => 'approval_gate', 'to' => 'deploy_production'],
            ['from' => 'deploy_production', 'to' => 'smoke_tests'],
        ],
    ];

    $pipelineContext = [
        'pipeline' => [
            'id' => 'PIPE-001',
            'project_name' => 'workflow-engine-api',
            'repository' => 'https://github.com/company/workflow-engine-api',
            'branch' => 'main',
            'commit_sha' => 'abc123def456',
            'triggered_by' => 'developer@company.com',
            'environment' => 'production',
        ],
    ];

    $workflowId = $this->engine->start('cicd-pipeline', $definition, $pipelineContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance)->not()->toBeNull();
    expect($instance->getState())->toBe(WorkflowState::COMPLETED);
    expect($instance->getContext()->getData()['pipeline']['project_name'])->toBe('workflow-engine-api');
});

test('cicd pipeline workflow - parallel testing stages', function () {
    $definition = [
        'name' => 'CI/CD Pipeline with Parallel Testing',
        'version' => '3.0',
        'steps' => [
            [
                'id' => 'checkout_code',
                'name' => 'Source Code Checkout',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Checking out source code for parallel testing pipeline',
                ],
            ],
            [
                'id' => 'unit_tests_parallel',
                'name' => 'Unit Tests (Parallel)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running unit tests in parallel - estimated 8 minutes',
                    'test_suite' => 'unit',
                    'parallel_workers' => 4,
                ],
            ],
            [
                'id' => 'security_scan_parallel',
                'name' => 'Security Scan (Parallel)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running security vulnerability scan in parallel - estimated 12 minutes',
                    'scan_type' => 'dependency_check',
                    'tools' => ['snyk', 'owasp-dependency-check'],
                ],
            ],
            [
                'id' => 'code_quality_parallel',
                'name' => 'Code Quality (Parallel)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running code quality analysis in parallel - estimated 5 minutes',
                    'tools' => ['sonarqube', 'phpstan'],
                    'quality_gates' => ['coverage', 'complexity', 'duplication'],
                ],
            ],
            [
                'id' => 'parallel_tests_complete',
                'name' => 'Parallel Tests Completion',
                'action' => 'log',
                'parameters' => [
                    'message' => 'All parallel test stages completed successfully',
                    'join_condition' => 'all_tests_passed',
                ],
            ],
            [
                'id' => 'integration_tests',
                'name' => 'Integration Test Suite',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running comprehensive integration tests',
                ],
            ],
        ],
        'parallel_groups' => [
            [
                'name' => 'testing_phase',
                'steps' => ['unit_tests_parallel', 'security_scan_parallel', 'code_quality_parallel'],
                'join_type' => 'all_success',
            ],
        ],
        'transitions' => [
            ['from' => 'checkout_code', 'to' => 'unit_tests_parallel'],
            ['from' => 'checkout_code', 'to' => 'security_scan_parallel'],
            ['from' => 'checkout_code', 'to' => 'code_quality_parallel'],
            ['from' => 'unit_tests_parallel', 'to' => 'parallel_tests_complete'],
            ['from' => 'security_scan_parallel', 'to' => 'parallel_tests_complete'],
            ['from' => 'code_quality_parallel', 'to' => 'parallel_tests_complete'],
            ['from' => 'parallel_tests_complete', 'to' => 'integration_tests'],
        ],
    ];

    $parallelPipelineContext = [
        'pipeline' => [
            'id' => 'PIPE-PARALLEL-001',
            'project_name' => 'microservice-api',
            'type' => 'parallel_testing',
            'test_configuration' => [
                'parallel_workers' => 4,
                'test_timeout' => '15m',
                'quality_threshold' => 80,
            ],
            'optimization' => 'parallel_execution',
        ],
    ];

    $workflowId = $this->engine->start('parallel-cicd', $definition, $parallelPipelineContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['pipeline']['type'])->toBe('parallel_testing');
    expect($instance->getContext()->getData()['pipeline']['optimization'])->toBe('parallel_execution');
});

test('cicd pipeline workflow - deployment failure and rollback', function () {
    $definition = [
        'name' => 'CI/CD Pipeline with Rollback',
        'version' => '3.0',
        'steps' => [
            [
                'id' => 'build_and_test',
                'name' => 'Build and Test',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Building and testing application before deployment',
                ],
            ],
            [
                'id' => 'deploy_staging',
                'name' => 'Deploy to Staging',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Deploying to staging environment',
                    'environment' => 'staging',
                ],
            ],
            [
                'id' => 'staging_tests',
                'name' => 'Staging Validation Tests',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running validation tests on staging deployment',
                ],
            ],
            [
                'id' => 'production_deployment',
                'name' => 'Production Deployment',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Deploying to production environment - simulating failure',
                    'environment' => 'production',
                    'failure_simulation' => true,
                ],
            ],
            [
                'id' => 'rollback_production',
                'name' => 'Production Rollback',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Rolling back production deployment due to failure',
                    'rollback_type' => 'automatic',
                    'target_version' => 'previous_stable',
                ],
            ],
            [
                'id' => 'incident_notification',
                'name' => 'Incident Notification',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Notifying teams about deployment failure and rollback',
                    'notification_channels' => ['slack', 'pagerduty', 'email'],
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'build_and_test', 'to' => 'deploy_staging'],
            ['from' => 'deploy_staging', 'to' => 'staging_tests'],
            ['from' => 'staging_tests', 'to' => 'production_deployment'],
            ['from' => 'production_deployment', 'to' => 'rollback_production', 'condition' => 'deployment.failed === true'],
            ['from' => 'rollback_production', 'to' => 'incident_notification'],
        ],
        'error_handling' => [
            'strategy' => 'rollback_and_notify',
            'rollback_triggers' => ['deployment_failure', 'health_check_failure'],
            'notification_channels' => ['slack', 'pagerduty'],
        ],
    ];

    $rollbackContext = [
        'pipeline' => [
            'id' => 'PIPE-ROLLBACK-001',
            'project_name' => 'critical-service',
            'deployment_strategy' => 'blue_green',
            'rollback_enabled' => true,
            'failure_scenario' => 'deployment_failure',
            'previous_version' => 'v2.1.0',
            'target_version' => 'v2.2.0',
        ],
    ];

    $workflowId = $this->engine->start('rollback-pipeline', $definition, $rollbackContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['pipeline']['rollback_enabled'])->toBe(true);
    expect($instance->getContext()->getData()['pipeline']['failure_scenario'])->toBe('deployment_failure');
});

test('cicd pipeline workflow - feature branch deployment', function () {
    $definition = [
        'name' => 'Feature Branch CI/CD Pipeline',
        'version' => '3.0',
        'steps' => [
            [
                'id' => 'feature_validation',
                'name' => 'Feature Branch Validation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Validating feature branch {{pipeline.feature_branch}}',
                ],
            ],
            [
                'id' => 'run_tests',
                'name' => 'Feature Tests',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running comprehensive tests for feature {{pipeline.feature_name}}',
                ],
            ],
            [
                'id' => 'deploy_preview',
                'name' => 'Deploy Preview Environment',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Creating preview environment for feature {{pipeline.feature_name}}',
                    'environment_type' => 'ephemeral',
                    'auto_cleanup' => '7d',
                ],
            ],
            [
                'id' => 'qa_validation',
                'name' => 'QA Validation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'QA team validating feature in preview environment',
                    'assigned_to' => 'qa_team',
                    'validation_checklist' => 'feature_requirements',
                ],
            ],
            [
                'id' => 'merge_approval',
                'name' => 'Merge Approval',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Requesting approval to merge feature {{pipeline.feature_name}} to main',
                    'reviewers' => ['tech_lead', 'product_owner'],
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'feature_validation', 'to' => 'run_tests'],
            ['from' => 'run_tests', 'to' => 'deploy_preview'],
            ['from' => 'deploy_preview', 'to' => 'qa_validation'],
            ['from' => 'qa_validation', 'to' => 'merge_approval'],
        ],
        'cleanup_rules' => [
            'preview_environment_ttl' => '7d',
            'auto_cleanup_on_merge' => true,
            'cleanup_on_branch_delete' => true,
        ],
    ];

    $featureBranchContext = [
        'pipeline' => [
            'id' => 'PIPE-FEATURE-001',
            'type' => 'feature_branch',
            'feature_name' => 'advanced-search-functionality',
            'feature_branch' => 'feature/advanced-search',
            'base_branch' => 'develop',
            'developer' => 'developer@company.com',
            'jira_ticket' => 'PROJ-1234',
            'preview_url' => 'https://feature-advanced-search.preview.company.com',
        ],
    ];

    $workflowId = $this->engine->start('feature-pipeline', $definition, $featureBranchContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['pipeline']['type'])->toBe('feature_branch');
    expect($instance->getContext()->getData()['pipeline']['feature_name'])->toBe('advanced-search-functionality');
    expect($instance->getContext()->getData()['pipeline']['jira_ticket'])->toBe('PROJ-1234');
});
