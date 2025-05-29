<?php

use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowState;

beforeEach(function () {
    $this->engine = new WorkflowEngine($this->storage);
});

test('e-commerce order processing workflow - successful order flow', function () {
    // Create workflow definition based on ARCHITECTURE.md example
    $definition = [
        'name' => 'E-Commerce Order Processing',
        'version' => '2.0',
        'steps' => [
            [
                'id' => 'validate_order',
                'name' => 'Validate Order',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Validating order {{order.id}} with total {{order.total}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'check_fraud',
                'name' => 'Fraud Check',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running fraud check for order {{order.id}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'reserve_inventory',
                'name' => 'Reserve Inventory',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Reserving inventory for order {{order.id}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'process_payment',
                'name' => 'Process Payment',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Processing payment for order {{order.id}} amount {{order.total}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'create_shipment',
                'name' => 'Create Shipment',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Creating shipment for order {{order.id}}',
                    'level' => 'info',
                ],
            ],
            [
                'id' => 'send_notification',
                'name' => 'Send Order Confirmation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Sending order confirmation for {{order.id}} to {{order.customer_email}}',
                    'level' => 'info',
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'validate_order', 'to' => 'check_fraud'],
            ['from' => 'check_fraud', 'to' => 'reserve_inventory'],
            ['from' => 'reserve_inventory', 'to' => 'process_payment'],
            ['from' => 'process_payment', 'to' => 'create_shipment'],
            ['from' => 'create_shipment', 'to' => 'send_notification'],
        ],
    ];

    // Valid order data
    $orderContext = [
        'order' => [
            'id' => 'ORD-12345',
            'customer_email' => 'customer@example.com',
            'items' => [
                ['sku' => 'ITEM-001', 'quantity' => 2, 'price' => 50.00],
                ['sku' => 'ITEM-002', 'quantity' => 1, 'price' => 100.00],
            ],
            'total' => 200.00,
            'currency' => 'USD',
        ],
    ];

    // Start workflow
    $workflowId = $this->engine->start('ecommerce-order', $definition, $orderContext);

    expect($workflowId)->not()->toBeEmpty();

    // Get workflow instance to check state
    $instance = $this->engine->getInstance($workflowId);
    expect($instance)->not()->toBeNull();
    expect($instance->getState())->toBe(WorkflowState::COMPLETED);
});

test('e-commerce order processing workflow - workflow definition structure', function () {
    $definition = [
        'name' => 'E-Commerce Order Processing',
        'version' => '2.0',
        'steps' => [
            [
                'id' => 'validate_order',
                'name' => 'Validate Order',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Order validation: checking items, total, and customer data',
                    'conditions' => [
                        'order.items.count > 0',
                        'order.total > 0',
                        'order.customer_email is set',
                    ],
                ],
            ],
            [
                'id' => 'fraud_check',
                'name' => 'Fraud Detection',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Fraud check: analyzing order patterns and risk factors',
                    'timeout' => '2m',
                    'risk_threshold' => 0.7,
                ],
            ],
            [
                'id' => 'inventory_reservation',
                'name' => 'Inventory Management',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Inventory: reserving items for order fulfillment',
                    'compensation' => 'release_inventory_action',
                ],
            ],
            [
                'id' => 'payment_processing',
                'name' => 'Payment Gateway',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Payment: processing order payment through secure gateway',
                    'retry_attempts' => 3,
                    'retry_delay' => '30s',
                    'compensation' => 'refund_payment_action',
                ],
            ],
            [
                'id' => 'shipment_creation',
                'name' => 'Shipping Coordination',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Shipping: creating shipment and generating tracking info',
                ],
            ],
            [
                'id' => 'customer_notification',
                'name' => 'Customer Communication',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Notification: sending order confirmation and tracking details',
                    'async' => true,
                    'channels' => ['email', 'sms'],
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'validate_order', 'to' => 'fraud_check', 'condition' => 'order.valid === true'],
            ['from' => 'fraud_check', 'to' => 'inventory_reservation', 'condition' => 'fraud.risk < 0.7'],
            ['from' => 'inventory_reservation', 'to' => 'payment_processing'],
            ['from' => 'payment_processing', 'to' => 'shipment_creation', 'condition' => 'payment.success === true'],
            ['from' => 'shipment_creation', 'to' => 'customer_notification'],
        ],
        'error_handling' => [
            'on_failure' => 'compensate_and_notify',
            'notification_channels' => ['email', 'slack', 'webhook'],
        ],
    ];

    $context = [
        'order' => [
            'id' => 'ORD-COMPLEX-001',
            'customer_email' => 'test@ecommerce.com',
            'total' => 1500.00,
            'items' => [
                ['sku' => 'PREMIUM-ITEM', 'quantity' => 1, 'price' => 1500.00],
            ],
        ],
    ];

    $workflowId = $this->engine->start('ecommerce-complex', $definition, $context);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance)->not()->toBeNull();
    expect($instance->getContext()->getData()['order']['id'])->toBe('ORD-COMPLEX-001');
});

test('e-commerce order processing workflow - high value order scenarios', function () {
    $definition = [
        'name' => 'High Value Order Processing',
        'version' => '2.0',
        'steps' => [
            [
                'id' => 'order_validation',
                'name' => 'Enhanced Order Validation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'High-value order validation with enhanced security checks',
                    'security_level' => 'enhanced',
                ],
            ],
            [
                'id' => 'fraud_analysis',
                'name' => 'Advanced Fraud Analysis',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Running advanced fraud detection for high-value transaction',
                    'analysis_level' => 'advanced',
                    'manual_review_threshold' => 50000,
                ],
            ],
            [
                'id' => 'executive_approval',
                'name' => 'Executive Approval Gate',
                'action' => 'log',
                'parameters' => [
                    'message' => 'High-value order requiring executive approval',
                    'approval_required' => true,
                    'timeout' => '24h',
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'order_validation', 'to' => 'fraud_analysis'],
            ['from' => 'fraud_analysis', 'to' => 'executive_approval', 'condition' => 'order.total > 50000'],
        ],
    ];

    $highValueContext = [
        'order' => [
            'id' => 'ORD-HIGH-VALUE-001',
            'customer_email' => 'enterprise@bigcompany.com',
            'total' => 75000.00,
            'items' => [
                ['sku' => 'ENTERPRISE-LICENSE', 'quantity' => 1, 'price' => 75000.00],
            ],
            'approval_level' => 'executive',
        ],
    ];

    $workflowId = $this->engine->start('high-value-order', $definition, $highValueContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['order']['total'])->toBe(75000.0);
});

test('e-commerce order processing workflow - error handling scenarios', function () {
    $definition = [
        'name' => 'Order Processing with Error Handling',
        'version' => '2.0',
        'steps' => [
            [
                'id' => 'validate_order',
                'name' => 'Order Validation',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Validating order - simulating validation failure scenario',
                ],
            ],
            [
                'id' => 'error_notification',
                'name' => 'Error Notification',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Sending error notification to customer and operations team',
                ],
            ],
            [
                'id' => 'cleanup_resources',
                'name' => 'Resource Cleanup',
                'action' => 'log',
                'parameters' => [
                    'message' => 'Cleaning up any allocated resources due to order failure',
                ],
            ],
        ],
        'transitions' => [
            ['from' => 'validate_order', 'to' => 'error_notification'],
            ['from' => 'error_notification', 'to' => 'cleanup_resources'],
        ],
        'error_handling' => [
            'strategy' => 'compensate_and_retry',
            'max_retries' => 3,
            'retry_delay' => '5m',
            'compensation_actions' => [
                'release_inventory',
                'cancel_payment_authorization',
                'notify_customer',
            ],
        ],
    ];

    $failedOrderContext = [
        'order' => [
            'id' => 'ORD-FAILED-001',
            'customer_email' => 'test@failed-order.com',
            'total' => 0, // Invalid total to trigger failure
            'items' => [],
            'error_scenario' => true,
        ],
    ];

    $workflowId = $this->engine->start('failed-order', $definition, $failedOrderContext);

    expect($workflowId)->not()->toBeEmpty();

    $instance = $this->engine->getInstance($workflowId);
    expect($instance->getContext()->getData()['order']['error_scenario'])->toBe(true);
});
