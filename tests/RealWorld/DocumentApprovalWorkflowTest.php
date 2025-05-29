<?php

use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;

beforeEach(function () {
    $this->engine = new WorkflowEngine($this->storage);
});

test('document approval workflow - standard approval flow', function () {
    $definition = [
        'name' => 'Document Approval Workflow',
        'version' => '1.5',
        'steps' => [
            [
                'id' => 'submit_document',
                'name' => 'Submit Document',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Document {{document.title}} submitted for approval by {{document.author}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'initial_review',
                'name' => 'Manager Review',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Initial review by manager for document {{document.title}}',
                    'assigned_to' => 'manager_role',
                    'timeout' => '2d',
                ],
            ],
            [
                'id' => 'legal_review',
                'name' => 'Legal Review',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Legal team reviewing contract document {{document.title}}',
                    'assigned_to' => 'legal_team',
                    'timeout' => '5d',
                    'conditions' => 'document.type === "contract"',
                ],
            ],
            [
                'id' => 'compliance_review',
                'name' => 'Compliance Review',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Compliance review for high-value document {{document.title}}',
                    'assigned_to' => 'compliance_team',
                    'timeout' => '3d',
                    'conditions' => 'document.value > 100000',
                ],
            ],
            [
                'id' => 'final_approval',
                'name' => 'Executive Approval',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Executive final approval for document {{document.title}}',
                    'assigned_to' => 'executive_role',
                    'timeout' => '1d',
                ],
            ],
            [
                'id' => 'archive_document',
                'name' => 'Archive Document',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Archiving approved document {{document.title}} to secure repository',
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'submit_document', 'to' => 'initial_review'],
            ['from' => 'initial_review', 'to' => 'legal_review'],
            ['from' => 'legal_review', 'to' => 'compliance_review'],
            ['from' => 'compliance_review', 'to' => 'final_approval'],
            ['from' => 'final_approval', 'to' => 'archive_document'],
        ],
    ];

    $documentContext = [
        'document' => [
            'id' => 'DOC-001',
            'title' => 'Software License Agreement',
            'author' => 'legal@company.com',
            'type' => 'contract',
            'value' => 250000,
            'submitted_date' => '2024-01-15',
            'priority' => 'high',
        ],
    ];

    $workflowId = $this->engine->start('document-approval', $definition, $documentContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance)->not()->toBeNull();
    expect($instance->getState())->toBe(WorkflowState::COMPLETED);
    expect($instance->getContext()->getData()['document']['type'])->toBe('contract');
});

test('document approval workflow - parallel review process', function () {
    $definition = [
        'name' => 'Parallel Document Approval',
        'version' => '1.5',
        'steps' => [
            [
                'id' => 'submit_document',
                'name' => 'Document Submission',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Document submitted: {{document.title}} - initiating parallel reviews',
                ],
            ],
            [
                'id' => 'legal_review_parallel',
                'name' => 'Legal Review (Parallel)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Legal team parallel review for {{document.title}}',
                    'parallel_group' => 'reviews',
                    'estimated_duration' => '3-5 days',
                ],
            ],
            [
                'id' => 'compliance_review_parallel',
                'name' => 'Compliance Review (Parallel)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Compliance team parallel review for {{document.title}}',
                    'parallel_group' => 'reviews',
                    'estimated_duration' => '2-4 days',
                ],
            ],
            [
                'id' => 'technical_review_parallel',
                'name' => 'Technical Review (Parallel)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Technical team parallel review for {{document.title}}',
                    'parallel_group' => 'reviews',
                    'estimated_duration' => '1-2 days',
                ],
            ],
            [
                'id' => 'consolidate_reviews',
                'name' => 'Consolidate Reviews',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Consolidating all parallel reviews for {{document.title}}',
                    'join_condition' => 'all_reviews_complete',
                ],
            ],
            [
                'id' => 'final_decision',
                'name' => 'Final Decision',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Making final approval decision for {{document.title}}',
                ],
            ],
        ],
        'parallel_groups' => [
            [
                'name' => 'reviews',
                'steps' => ['legal_review_parallel', 'compliance_review_parallel', 'technical_review_parallel'],
                'join_type' => 'all_complete',
            ],
        ],
        'transitions' => [
            ['from' => 'submit_document', 'to' => 'legal_review_parallel'],
            ['from' => 'submit_document', 'to' => 'compliance_review_parallel'],
            ['from' => 'submit_document', 'to' => 'technical_review_parallel'],
            ['from' => 'legal_review_parallel', 'to' => 'consolidate_reviews'],
            ['from' => 'compliance_review_parallel', 'to' => 'consolidate_reviews'],
            ['from' => 'technical_review_parallel', 'to' => 'consolidate_reviews'],
            ['from' => 'consolidate_reviews', 'to' => 'final_decision'],
        ],
    ];

    $complexDocumentContext = [
        'document' => [
            'id' => 'DOC-COMPLEX-001',
            'title' => 'Multi-Million Dollar Partnership Agreement',
            'author' => 'partnerships@company.com',
            'type' => 'partnership_agreement',
            'value' => 5000000,
            'complexity' => 'high',
            'requires_parallel_review' => true,
            'review_teams' => ['legal', 'compliance', 'technical'],
        ],
    ];

    $workflowId = $this->engine->start('complex-document-approval', $definition, $complexDocumentContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['document']['requires_parallel_review'])->toBe(true);
    expect($instance->getContext()->getData()['document']['value'])->toBe(5000000);
});

test('document approval workflow - rejection and resubmission flow', function () {
    $definition = [
        'name' => 'Document Approval with Rejection',
        'version' => '1.5',
        'steps' => [
            [
                'id' => 'submit_document',
                'name' => 'Initial Submission',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Document {{document.title}} submitted for approval',
                ],
            ],
            [
                'id' => 'initial_review',
                'name' => 'Initial Review',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Reviewing document {{document.title}} for initial compliance',
                ],
            ],
            [
                'id' => 'rejection_notification',
                'name' => 'Rejection Notification',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Document {{document.title}} rejected - notifying author of required changes',
                    'rejection_reasons' => 'compliance_issues',
                    'action_required' => 'revision_needed',
                ],
            ],
            [
                'id' => 'revision_period',
                'name' => 'Revision Period',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Author has 7 days to revise and resubmit {{document.title}}',
                    'timeout' => '7d',
                    'auto_escalate' => true,
                ],
            ],
            [
                'id' => 'resubmission_review',
                'name' => 'Resubmission Review',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Reviewing resubmitted document {{document.title}}',
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'submit_document', 'to' => 'initial_review'],
            ['from' => 'initial_review', 'to' => 'rejection_notification', 'condition' => 'review.approved === false'],
            ['from' => 'rejection_notification', 'to' => 'revision_period'],
            ['from' => 'revision_period', 'to' => 'resubmission_review', 'condition' => 'document.resubmitted === true'],
        ],
        'error_handling' => [
            'rejection_workflow' => 'revision_and_resubmit',
            'max_revisions' => 3,
            'escalation_after_rejections' => 2,
        ],
    ];

    $rejectedDocumentContext = [
        'document' => [
            'id' => 'DOC-REVISION-001',
            'title' => 'Non-Compliant Service Agreement',
            'author' => 'sales@company.com',
            'type' => 'service_agreement',
            'value' => 50000,
            'initial_submission' => true,
            'compliance_issues' => [
                'missing_liability_clauses',
                'incorrect_termination_terms',
                'data_protection_gaps',
            ],
        ],
    ];

    $workflowId = $this->engine->start('rejection-flow', $definition, $rejectedDocumentContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['document']['initial_submission'])->toBe(true);
    expect(count($instance->getContext()->getData()['document']['compliance_issues']))->toBe(3);
});

test('document approval workflow - escalation and timeout handling', function () {
    $definition = [
        'name' => 'Document Approval with Escalation',
        'version' => '1.5',
        'steps' => [
            [
                'id' => 'submit_document',
                'name' => 'Document Submission',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Critical document {{document.title}} submitted with escalation rules',
                ],
            ],
            [
                'id' => 'manager_review',
                'name' => 'Manager Review (Timed)',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Manager reviewing {{document.title}} - must complete within 24 hours',
                    'timeout' => '24h',
                    'escalation_target' => 'director_level',
                ],
            ],
            [
                'id' => 'director_escalation',
                'name' => 'Director Escalation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Document {{document.title}} escalated to director due to timeout',
                    'escalation_reason' => 'manager_timeout',
                    'priority' => 'urgent',
                ],
            ],
            [
                'id' => 'executive_override',
                'name' => 'Executive Override',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Executive override for time-critical document {{document.title}}',
                    'override_reason' => 'business_critical',
                    'expedited_approval' => true,
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'submit_document', 'to' => 'manager_review'],
            ['from' => 'manager_review', 'to' => 'director_escalation', 'condition' => 'timeout_occurred'],
            ['from' => 'director_escalation', 'to' => 'executive_override', 'condition' => 'escalation_required'],
        ],
        'escalation_rules' => [
            'timeout_escalation' => true,
            'escalation_levels' => ['manager', 'director', 'executive'],
            'notification_channels' => ['email', 'slack', 'sms'],
        ],
    ];

    $urgentDocumentContext = [
        'document' => [
            'id' => 'DOC-URGENT-001',
            'title' => 'Emergency Vendor Contract',
            'author' => 'procurement@company.com',
            'type' => 'emergency_contract',
            'value' => 2000000,
            'priority' => 'critical',
            'business_impact' => 'production_blocker',
            'deadline' => '2024-01-20T23:59:59Z',
            'escalation_enabled' => true,
        ],
    ];

    $workflowId = $this->engine->start('escalation-flow', $definition, $urgentDocumentContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['document']['priority'])->toBe('critical');
    expect($instance->getContext()->getData()['document']['escalation_enabled'])->toBe(true);
    expect($instance->getContext()->getData()['document']['business_impact'])->toBe('production_blocker');
});
