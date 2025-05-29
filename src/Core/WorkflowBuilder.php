<?php

namespace SolutionForest\WorkflowEngine\Core;

use SolutionForest\WorkflowEngine\Contracts\WorkflowAction;
use SolutionForest\WorkflowEngine\Exceptions\InvalidWorkflowDefinitionException;

/**
 * Fluent workflow builder for creating workflow definitions with a declarative API.
 *
 * This builder provides a fluent interface for constructing complex workflows using method chaining.
 * It supports conditional steps, common patterns (email, delays, HTTP requests), and built-in validation.
 *
 * ## Basic Usage
 *
 * ```php
 * $workflow = WorkflowBuilder::create('user-onboarding')
 *     ->description('Complete user onboarding process')
 *     ->addStep('send_welcome', SendWelcomeEmailAction::class)
 *     ->addStep('create_profile', CreateUserProfileAction::class, ['template' => 'basic'])
 *     ->build();
 * ```
 *
 * ## Conditional Steps
 *
 * ```php
 * $workflow = WorkflowBuilder::create('order-processing')
 *     ->addStep('validate_order', ValidateOrderAction::class)
 *     ->when('order.type === "premium"', function($builder) {
 *         $builder->addStep('premium_processing', PremiumProcessingAction::class);
 *     })
 *     ->addStep('finalize_order', FinalizeOrderAction::class)
 *     ->build();
 * ```
 *
 * ## Common Patterns
 *
 * ```php
 * $workflow = WorkflowBuilder::create('newsletter')
 *     ->email('newsletter-template', '{{ user.email }}', 'Weekly Newsletter')
 *     ->delay(hours: 1)
 *     ->http('https://api.example.com/track', 'POST', ['user_id' => '{{ user.id }}'])
 *     ->build();
 * ```
 *
 * @see WorkflowDefinition For the resulting workflow definition structure
 * @see QuickWorkflowBuilder For pre-built common workflow patterns
 */
final class WorkflowBuilder
{
    /** @var string The unique workflow name/identifier */
    private string $name;

    /** @var string The workflow version for change tracking */
    private string $version = '1.0';

    /** @var string Human-readable workflow description */
    private string $description = '';

    /** @var array<int, array<string, mixed>> Array of step configurations */
    private array $steps = [];

    /** @var array<int, array<string, string>> Array of step transitions */
    private array $transitions = [];

    /** @var array<string, mixed> Additional workflow metadata */
    private array $metadata = [];

    /**
     * Private constructor to enforce factory pattern usage.
     *
     * @param  string  $name  The workflow name/identifier
     *
     * @throws InvalidWorkflowDefinitionException If name is empty or invalid
     */
    private function __construct(string $name)
    {
        if (empty(trim($name))) {
            throw InvalidWorkflowDefinitionException::invalidName($name);
        }

        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name)) {
            throw InvalidWorkflowDefinitionException::invalidName($name, 'Name must start with a letter and contain only letters, numbers, hyphens, and underscores');
        }

        $this->name = $name;
    }

    /**
     * Create a new workflow builder instance.
     *
     * @param  string  $name  The unique workflow name/identifier
     * @return static New builder instance for method chaining
     *
     * @throws InvalidWorkflowDefinitionException If name is invalid
     *
     * @example
     * ```php
     * $builder = WorkflowBuilder::create('user-registration');
     * ```
     */
    public static function create(string $name): static
    {
        return new self($name);
    }

    /**
     * Set the workflow description for documentation and debugging.
     *
     * @param  string  $description  Human-readable workflow description
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->description('Handles complete user onboarding process');
     * ```
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the workflow version for change tracking and compatibility.
     *
     * @param  string  $version  Semantic version string (e.g., "1.0.0", "2.1")
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->version('2.1.0');
     * ```
     */
    public function version(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Add a workflow step with comprehensive configuration options.
     *
     * @param  string  $id  Unique step identifier within the workflow
     * @param  string|WorkflowAction  $action  Action class name or instance
     * @param  array<string, mixed>  $config  Step-specific configuration parameters
     * @param  int|null  $timeout  Maximum execution time in seconds
     * @param  int  $retryAttempts  Number of retry attempts on failure (0-10)
     * @return $this For method chaining
     *
     * @throws InvalidWorkflowDefinitionException If step configuration is invalid
     *
     * @example
     * ```php
     * $builder->addStep(
     *     'send_email',
     *     SendEmailAction::class,
     *     ['template' => 'welcome', 'to' => '{{ user.email }}'],
     *     timeout: 30,
     *     retryAttempts: 3
     * );
     * ```
     */
    public function addStep(
        string $id,
        string|WorkflowAction $action,
        array $config = [],
        ?int $timeout = null,
        int $retryAttempts = 0
    ): self {
        if (empty(trim($id))) {
            throw InvalidWorkflowDefinitionException::invalidStepId($id);
        }

        if ($retryAttempts < 0 || $retryAttempts > 10) {
            throw InvalidWorkflowDefinitionException::invalidRetryAttempts($retryAttempts);
        }

        if ($timeout !== null && $timeout <= 0) {
            throw InvalidWorkflowDefinitionException::invalidTimeout($timeout);
        }

        // Check for duplicate step IDs
        foreach ($this->steps as $existingStep) {
            if ($existingStep['id'] === $id) {
                throw InvalidWorkflowDefinitionException::duplicateStepId($id);
            }
        }

        $this->steps[] = [
            'id' => $id,
            'action' => is_string($action) ? $action : $action::class,
            'config' => $config,
            'timeout' => $timeout,
            'retry_attempts' => $retryAttempts,
        ];

        return $this;
    }

    /**
     * Add the first step in a workflow (syntactic sugar for better readability).
     *
     * @param  string|WorkflowAction  $action  Action class name or instance
     * @param  array<string, mixed>  $config  Step-specific configuration parameters
     * @param  int|null  $timeout  Maximum execution time in seconds
     * @param  int  $retryAttempts  Number of retry attempts on failure
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->startWith(ValidateInputAction::class, ['strict' => true]);
     * ```
     */
    public function startWith(
        string|WorkflowAction $action,
        array $config = [],
        ?int $timeout = null,
        int $retryAttempts = 0
    ): self {
        $stepId = 'step_'.(count($this->steps) + 1);

        return $this->addStep($stepId, $action, $config, $timeout, $retryAttempts);
    }

    /**
     * Add a sequential step (syntactic sugar for better readability).
     *
     * @param  string|WorkflowAction  $action  Action class name or instance
     * @param  array<string, mixed>  $config  Step-specific configuration parameters
     * @param  int|null  $timeout  Maximum execution time in seconds
     * @param  int  $retryAttempts  Number of retry attempts on failure
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->then(ProcessDataAction::class)->then(SaveResultAction::class);
     * ```
     */
    public function then(
        string|WorkflowAction $action,
        array $config = [],
        ?int $timeout = null,
        int $retryAttempts = 0
    ): self {
        $stepId = 'step_'.(count($this->steps) + 1);

        return $this->addStep($stepId, $action, $config, $timeout, $retryAttempts);
    }

    /**
     * Add conditional steps that are only executed when a condition is met.
     *
     * @param  string  $condition  Condition expression to evaluate (e.g., "user.premium === true")
     * @param  callable(static): void  $callback  Callback that receives the builder to add conditional steps
     * @return $this For method chaining
     *
     * @throws InvalidWorkflowDefinitionException If condition is invalid
     *
     * @example
     * ```php
     * $builder->when('order.amount > 1000', function($builder) {
     *     $builder->addStep('fraud_check', FraudCheckAction::class);
     *     $builder->addStep('manager_approval', ManagerApprovalAction::class);
     * });
     * ```
     */
    public function when(string $condition, callable $callback): self
    {
        if (empty(trim($condition))) {
            throw InvalidWorkflowDefinitionException::invalidCondition($condition);
        }

        $originalStepsCount = count($this->steps);
        $callback($this);
        $newStepsCount = count($this->steps);

        // Mark new steps as conditional
        for ($i = $originalStepsCount; $i < $newStepsCount; $i++) {
            $this->steps[$i]['condition'] = $condition;
        }

        return $this;
    }

    /**
     * Add an email step using pre-configured email action (common pattern).
     *
     * @param  string  $template  Email template identifier
     * @param  string  $to  Recipient email address (supports placeholders like "{{ user.email }}")
     * @param  string  $subject  Email subject line (supports placeholders)
     * @param  array<string, mixed>  $data  Additional template data
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->email(
     *     'welcome-email',
     *     '{{ user.email }}',
     *     'Welcome to {{ app.name }}!',
     *     ['user_name' => '{{ user.name }}']
     * );
     * ```
     */
    public function email(
        string $template,
        string $to,
        string $subject,
        array $data = []
    ): self {
        return $this->addStep(
            'email_'.count($this->steps),
            'SolutionForest\\WorkflowEngine\\Actions\\EmailAction',
            [
                'template' => $template,
                'to' => $to,
                'subject' => $subject,
                'data' => $data,
            ]
        );
    }

    /**
     * Add a delay step to pause workflow execution (common pattern).
     *
     * @param  int|null  $seconds  Delay in seconds
     * @param  int|null  $minutes  Delay in minutes (converted to seconds)
     * @param  int|null  $hours  Delay in hours (converted to seconds)
     * @return $this For method chaining
     *
     * @throws InvalidWorkflowDefinitionException If no delay value is provided
     *
     * @example
     * ```php
     * $builder->delay(seconds: 30);           // 30 second delay
     * $builder->delay(minutes: 5);            // 5 minute delay
     * $builder->delay(hours: 1, minutes: 30); // 1.5 hour delay
     * ```
     */
    public function delay(?int $seconds = null, ?int $minutes = null, ?int $hours = null): self
    {
        $totalSeconds = $seconds ?? 0;
        $totalSeconds += ($minutes ?? 0) * 60;
        $totalSeconds += ($hours ?? 0) * 3600;

        if ($totalSeconds <= 0) {
            throw InvalidWorkflowDefinitionException::invalidDelay($seconds, $minutes, $hours);
        }

        return $this->addStep(
            'delay_'.count($this->steps),
            'SolutionForest\\WorkflowEngine\\Actions\\DelayAction',
            ['seconds' => $totalSeconds]
        );
    }

    /**
     * Add an HTTP request step for external API calls (common pattern).
     *
     * @param  string  $url  Target URL for the HTTP request
     * @param  string  $method  HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param  array<string, mixed>  $data  Request payload data
     * @param  array<string, string>  $headers  Additional HTTP headers
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->http(
     *     'https://api.example.com/users',
     *     'POST',
     *     ['name' => '{{ user.name }}', 'email' => '{{ user.email }}'],
     *     ['Authorization' => 'Bearer {{ api.token }}']
     * );
     * ```
     */
    public function http(
        string $url,
        string $method = 'GET',
        array $data = [],
        array $headers = []
    ): self {
        return $this->addStep(
            'http_'.count($this->steps),
            'SolutionForest\\WorkflowEngine\\Actions\\HttpAction',
            [
                'url' => $url,
                'method' => $method,
                'data' => $data,
                'headers' => $headers,
            ]
        );
    }

    /**
     * Add a condition check step for workflow branching (common pattern).
     *
     * @param  string  $condition  Condition expression to evaluate
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->condition('user.verified === true');
     * ```
     */
    public function condition(string $condition): self
    {
        return $this->addStep(
            'condition_'.count($this->steps),
            'SolutionForest\\WorkflowEngine\\Actions\\ConditionAction',
            ['condition' => $condition]
        );
    }

    /**
     * Add custom metadata to the workflow definition.
     *
     * @param  array<string, mixed>  $metadata  Additional metadata to merge
     * @return $this For method chaining
     *
     * @example
     * ```php
     * $builder->withMetadata([
     *     'author' => 'John Doe',
     *     'department' => 'Engineering',
     *     'priority' => 'high'
     * ]);
     * ```
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Build the final workflow definition from the configured steps and settings.
     *
     * @return WorkflowDefinition The complete workflow definition ready for execution
     *
     * @throws InvalidWorkflowDefinitionException If the workflow configuration is invalid
     *
     * @example
     * ```php
     * $workflow = WorkflowBuilder::create('user-registration')
     *     ->addStep('validate', ValidateUserAction::class)
     *     ->addStep('save', SaveUserAction::class)
     *     ->build();
     * ```
     */
    public function build(): WorkflowDefinition
    {
        if (empty($this->steps)) {
            throw InvalidWorkflowDefinitionException::emptyWorkflow($this->name);
        }

        // Convert builder format to Step objects
        $steps = [];
        foreach ($this->steps as $stepData) {
            $steps[] = new Step(
                id: $stepData['id'],
                actionClass: $stepData['action'],
                config: $stepData['config'],
                timeout: $stepData['timeout'] ? (string) $stepData['timeout'] : null,
                retryAttempts: $stepData['retry_attempts'],
                conditions: isset($stepData['condition']) ? [$stepData['condition']] : []
            );
        }

        // Add description to metadata
        if ($this->description) {
            $this->metadata['description'] = $this->description;
        }

        return new WorkflowDefinition(
            name: $this->name,
            version: $this->version,
            steps: $steps,
            transitions: $this->transitions,
            metadata: $this->metadata
        );
    }

    /**
     * Get access to quick workflow builder for common workflow patterns.
     *
     * @return QuickWorkflowBuilder Instance for creating pre-configured workflows
     *
     * @example
     * ```php
     * $workflow = WorkflowBuilder::quick()->userOnboarding('new-user-flow');
     * $workflow = WorkflowBuilder::quick()->orderProcessing();
     * ```
     */
    public static function quick(): QuickWorkflowBuilder
    {
        return new QuickWorkflowBuilder;
    }
}

/**
 * Pre-built workflow patterns for common business scenarios.
 *
 * This class provides ready-to-use workflow templates that can be customized
 * and extended for typical business processes.
 *
 * @see WorkflowBuilder For custom workflow creation
 */
class QuickWorkflowBuilder
{
    /**
     * Create a user onboarding workflow with standard steps.
     *
     * @param  string  $name  Workflow name (defaults to 'user-onboarding')
     * @return WorkflowBuilder Configured builder ready for customization
     *
     * @example
     * ```php
     * $workflow = WorkflowBuilder::quick()
     *     ->userOnboarding('premium-user-onboarding')
     *     ->then(SetupPremiumFeaturesAction::class)
     *     ->build();
     * ```
     */
    public function userOnboarding(string $name = 'user-onboarding'): WorkflowBuilder
    {
        return WorkflowBuilder::create($name)
            ->description('Standard user onboarding process')
            ->email(
                template: 'welcome',
                to: '{{ user.email }}',
                subject: 'Welcome to {{ app.name }}!'
            )
            ->delay(minutes: 5)
            ->addStep('create_profile', 'App\\Actions\\CreateUserProfileAction')
            ->addStep('assign_role', 'App\\Actions\\AssignDefaultRoleAction');
    }

    /**
     * Create an order processing workflow for e-commerce scenarios.
     *
     * @param  string  $name  Workflow name (defaults to 'order-processing')
     * @return WorkflowBuilder Configured builder ready for customization
     *
     * @example
     * ```php
     * $workflow = WorkflowBuilder::quick()
     *     ->orderProcessing('premium-order-flow')
     *     ->when('order.priority === "high"', function($builder) {
     *         $builder->addStep('priority_handling', PriorityHandlingAction::class);
     *     })
     *     ->build();
     * ```
     */
    public function orderProcessing(string $name = 'order-processing'): WorkflowBuilder
    {
        return WorkflowBuilder::create($name)
            ->description('E-commerce order processing workflow')
            ->addStep('validate_order', 'App\\Actions\\ValidateOrderAction')
            ->addStep('charge_payment', 'App\\Actions\\ChargePaymentAction')
            ->addStep('update_inventory', 'App\\Actions\\UpdateInventoryAction')
            ->email(
                template: 'order-confirmation',
                to: '{{ order.customer.email }}',
                subject: 'Order Confirmation #{{ order.id }}'
            );
    }

    /**
     * Create a document approval workflow for content management.
     *
     * @param  string  $name  Workflow name (defaults to 'document-approval')
     * @return WorkflowBuilder Configured builder ready for customization
     *
     * @example
     * ```php
     * $workflow = WorkflowBuilder::quick()
     *     ->documentApproval('legal-document-review')
     *     ->addStep('legal_review', LegalReviewAction::class)
     *     ->build();
     * ```
     */
    public function documentApproval(string $name = 'document-approval'): WorkflowBuilder
    {
        return WorkflowBuilder::create($name)
            ->description('Document approval process')
            ->addStep('submit_document', 'App\\Actions\\SubmitDocumentAction')
            ->addStep('assign_reviewer', 'App\\Actions\\AssignReviewerAction')
            ->email(
                template: 'review-request',
                to: '{{ reviewer.email }}',
                subject: 'Document Review Request'
            )
            ->addStep('review_document', 'App\\Actions\\ReviewDocumentAction')
            ->when('review.approved', function ($builder) {
                $builder->addStep('approve_document', 'App\\Actions\\ApproveDocumentAction');
            })
            ->when('review.rejected', function ($builder) {
                $builder->addStep('reject_document', 'App\\Actions\\RejectDocumentAction');
            });
    }
}
