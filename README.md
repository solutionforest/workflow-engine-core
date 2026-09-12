# Workflow Engine Core

[![PHPStan](https://img.shields.io/github/actions/workflow/status/solutionforest/workflow-engine-core/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/solutionforest/workflow-engine-core/actions?query=workflow%3Aphpstan+branch%3Amain)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/solutionforest/workflow-engine-core/run-tests.yml?branch=main&label=Test&style=flat-square)](https://github.com/solutionforest/workflow-engine-core/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/solutionforest/workflow-engine-core/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/solutionforest/workflow-engine-core/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/solution-forest/workflow-engine-core.svg?style=flat-square)](https://packagist.org/packages/solution-forest/workflow-engine-core)

A powerful, framework-agnostic workflow engine for PHP applications. This core library provides comprehensive workflow definition, execution, and state management capabilities without any framework dependencies.

> ⚠️ **WARNING: DEVELOPMENT STATUS**⚠️ 
> 
> This package is currently under active development and is **NOT READY FOR PRODUCTION USE**. 
> 
> Features may be incomplete, APIs might change, and there could be breaking changes. Use at your own risk in development environments only.


## 📋 Requirements

- **PHP 8.3+** - Leverages modern PHP features for type safety and performance
- **Composer** - For dependency management
- **No framework dependencies** - Works with any PHP project

## ✨ Features

- **🚀 Framework Agnostic**: Works with any PHP framework or standalone applications
- **🔒 Type Safe**: Full PHP 8.3+ type safety with strict typing and generics
- **🔧 Extensible**: Plugin architecture for custom actions and storage adapters
- **📊 State Management**: Robust workflow instance state tracking and persistence
- **⚡ Performance**: Optimized for high-throughput workflow execution
- **🛡️ Error Handling**: Comprehensive exception handling with detailed context
- **🔄 Retry Logic**: Built-in retry mechanisms with configurable strategies
- **⏱️ Timeouts**: Step-level timeout controls for reliable execution
- **📋 Conditions**: Conditional workflow execution based on runtime data
- **🎯 Events**: Rich event system for monitoring and integration
- **🧪 Well Tested**: Comprehensive test suite with 93 tests and 224+ assertions

## 📦 Installation

### For Production Use

```bash
composer require solution-forest/workflow-engine-core
```

### For Development

```bash
# Clone the repository
git clone https://github.com/solution-forest/workflow-engine-core.git
cd workflow-engine-core

# Install dependencies
composer install

# Run quality checks
composer ci
```

## 🚀 Quick Start

### Basic Workflow Definition

```php
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;
use SolutionForest\WorkflowEngine\Core\WorkflowEngine;
use SolutionForest\WorkflowEngine\Core\WorkflowContext;
use SolutionForest\WorkflowEngine\Core\ActionResult;
use SolutionForest\WorkflowEngine\Actions\BaseAction;

// Define custom actions
class ValidateOrderAction extends BaseAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        $orderId = $context->getData('order_id');

        // Your validation logic here
        if ($this->isValidOrder($orderId)) {
            return ActionResult::success(['validated' => true]);
        }

        return ActionResult::failure('Invalid order');
    }
}

// Build a workflow definition
$definition = WorkflowBuilder::create('order-processing')
    ->description('Process customer orders')
    ->addStep('validate', ValidateOrderAction::class)
    ->addStep('payment', ProcessPaymentAction::class, timeout: 300, retryAttempts: 3)
    ->addStep('fulfillment', FulfillOrderAction::class)
    ->build();

// Create engine with a storage adapter (InMemoryStorage ships with the package)
use SolutionForest\WorkflowEngine\Storage\InMemoryStorage;

$engine = new WorkflowEngine(new InMemoryStorage(), $eventDispatcher);

// Start and run the workflow
$instanceId = $engine->start(
    'order-processing',
    $definition->toArray(),
    ['order_id' => 123, 'customer_id' => 456]
);

// Check the result
$instance = $engine->getInstance($instanceId);
echo $instance->getState()->value; // "completed"
```

### Advanced Workflow Builder

```php
use SolutionForest\WorkflowEngine\Core\WorkflowBuilder;

$workflow = WorkflowBuilder::create('order-flow')
    ->description('Process customer orders')
    ->addStep('validate', ValidateOrderAction::class)
    ->when('order.total > 1000', function ($builder) {
        $builder->addStep('fraud_check', FraudCheckAction::class);
    })
    ->addStep('payment', ProcessPaymentAction::class, timeout: 300, retryAttempts: 3)
    // fakeEmail() records an email but does NOT send one - this package ships
    // no mail transport. Use your own action for real delivery.
    ->fakeEmail('order-confirmation', 'customer@example.com', 'Order Confirmed')
    ->build();

// Quick templates for common patterns
$workflow = WorkflowBuilder::quick()->userOnboarding();
$workflow = WorkflowBuilder::quick()->orderProcessing();
$workflow = WorkflowBuilder::quick()->documentApproval();
```

### PHP 8.3+ Attributes

> **Note:** Attributes are currently metadata annotations for documentation and tooling. They are not yet auto-parsed by the engine at runtime — use the builder API (`timeout:`, `retryAttempts:`, `when()`) or step config to apply these behaviors. Attribute-driven execution is planned for a future release.

Use native PHP attributes to annotate actions with retry, timeout, and conditions:

#### Retry Logic
```php
use SolutionForest\WorkflowEngine\Attributes\Retry;

#[Retry(attempts: 3, backoff: 'exponential', delay: 1000)]
class ReliableApiAction extends BaseAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        // Metadata only - the engine does not read this attribute yet.
        // For real retries use: ->addStep('id', Action::class, retryAttempts: 3)
        return ActionResult::success();
    }
}
```

#### Timeouts
```php
use SolutionForest\WorkflowEngine\Attributes\Timeout;

#[Timeout(seconds: 30)]
class TimedAction extends BaseAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        // Metadata only - the engine does not read this attribute yet.
        // For a real timeout use: ->addStep('id', Action::class, timeout: 30)
        return ActionResult::success();
    }
}
```

#### Conditional Execution
```php
use SolutionForest\WorkflowEngine\Attributes\Condition;

#[Condition('order.amount > 100')]
class PremiumProcessingAction extends BaseAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        // Metadata only - the engine does not read this attribute yet.
        // For real gating use: ->when('order.amount > 100', fn ($b) => ...)
        return ActionResult::success();
    }
}
```

#### Step Metadata
```php
use SolutionForest\WorkflowEngine\Attributes\WorkflowStep;

#[WorkflowStep(id: 'send_email', name: 'Send Welcome Email', description: 'Sends a welcome email to the new user')]
class SendWelcomeEmailAction extends BaseAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        return ActionResult::success();
    }
}
```

### Retry, Timeout & Conditions via Builder

These features can also be configured through the fluent builder API:

```php
// Steps with retry and timeout configured via builder
$workflow = WorkflowBuilder::create('reliable-flow')
    ->addStep('fetch_data', FetchDataAction::class, timeout: 30, retryAttempts: 3)
    ->addStep('process', ProcessAction::class, timeout: 60)
    ->build();

// Conditional steps evaluated at runtime
$workflow = WorkflowBuilder::create('conditional-flow')
    ->addStep('validate', ValidateAction::class)
    ->when('order.total > 1000', function ($builder) {
        $builder->addStep('fraud_check', FraudCheckAction::class);
    })
    ->addStep('complete', CompleteAction::class)
    ->build();
```

### Condition Syntax

Conditions are parsed, never `eval()`'d. The same grammar is used by step
conditions, transition conditions, `when()` and `ConditionAction`.

```php
// Comparisons: === !== == != > < >= <=
'order.total > 1000'
'user.plan === "premium"'
'status != cancelled'        // bare words are treated as strings

// Truthy checks and negation
'user.active'
'!user.suspended'

// Boolean operators, with && binding tighter than ||
'order.total > 1000 && user.vip === true'
'user.vip || order.total > 5000'

// Parentheses to override precedence
'(user.vip || order.total > 5000) && !order.refunded'
```

Dot notation reads nested data (`order.customer.email`). Values may be numbers,
quoted strings, bare words, `true`, `false` or `null`.

Two rules keep a mistyped condition from quietly routing a workflow the wrong way:

- **A malformed expression throws** `InvalidWorkflowDefinitionException` rather
  than collapsing to a boolean. Chained comparisons (`a > 1 > 2`), dangling
  operators and unbalanced parentheses are all rejected.
- **A relational comparison against a missing key is `false`.** Because `null`
  coerces to `0` in PHP, `missing.key < 1000` would otherwise be *true* and a
  step gated on data that was never set would run.

### Workflow Lifecycle Management

```php
// Start, resume, cancel
$instanceId = $engine->start('my-workflow', $definition->toArray(), ['key' => 'value']);
$instance = $engine->getInstance($instanceId);
$engine->resume($instanceId);
$engine->cancel($instanceId, 'No longer needed');

// Instance IDs are caller-supplied and must be unique: start() throws
// InvalidWorkflowStateException rather than overwriting an existing instance.

// Recovering a failed workflow: fix the cause, then resume to retry the
// step that failed. resume() is rejected for COMPLETED and CANCELLED.
try {
    $engine->resume($instanceId);
} catch (StepExecutionException $e) {
    // Still failing - the exception carries the real cause, not a
    // state-machine error from the failure handler.
}

// Track progress
$progress = $instance->getProgress(); // 0.0 to 100.0
$summary = $instance->getStatusSummary();

// Query instances with filters
$instances = $engine->getInstances([
    'state' => 'running',
    'definition_name' => 'order-processing',
    'created_after' => new \DateTime('-7 days'),
    'limit' => 50,
    'offset' => 0,
]);
```

### SimpleWorkflow Helper

For quick workflow execution without manual engine setup:

```php
use SolutionForest\WorkflowEngine\Support\SimpleWorkflow;

$simple = new SimpleWorkflow($storageAdapter);

// Run actions sequentially
$instanceId = $simple->sequential('user-onboarding', [
    SendWelcomeEmailAction::class,
    CreateUserProfileAction::class,
    AssignDefaultRoleAction::class,
], ['user_id' => 123]);

// Run a single action as a workflow
$instanceId = $simple->runAction(SendEmailAction::class, [
    'to' => 'user@example.com',
    'subject' => 'Welcome!',
]);

// Execute from a builder
$builder = WorkflowBuilder::create('custom-flow')
    ->addStep('validate', ValidateAction::class)
    ->addStep('process', ProcessAction::class);
$instanceId = $simple->executeBuilder($builder, $context);

// Check status
$status = $simple->getStatus($instanceId);
// Returns: id, state, current_step, progress, completed_steps, failed_steps, error_message, ...
```

## 🏗️ Architecture

The workflow engine follows a clean architecture with clear separation of concerns:

```
WorkflowBuilder → WorkflowDefinition → WorkflowEngine → Executor → Actions
                                              ↓
                                        StateManager → StorageAdapter
                                              ↓
                                        EventDispatcher
```

### Core Components

| Component | Purpose |
|-----------|---------|
| **WorkflowBuilder** | Fluent API for constructing workflow definitions with `addStep()`, `when()`, `email()`, `delay()`, `http()` |
| **WorkflowDefinition** | Immutable blueprint containing steps, transitions, conditions, and metadata |
| **WorkflowEngine** | Central orchestrator — `start()`, `resume()`, `cancel()`, `getInstance()`, `getInstances()`, `getStatus()` |
| **Executor** | Runs steps sequentially with retry logic, timeout enforcement, and condition evaluation |
| **StateManager** | Coordinates persistence through StorageAdapter |
| **EventDispatcher** | Broadcasts 7 event types during workflow lifecycle |

### State Machine

```
PENDING ──→ RUNNING ──→ COMPLETED   (terminal)
              ↓ ↑
          WAITING / PAUSED
              ↓ ↑
            FAILED ──→ RUNNING      (resume retries the failed step)
              ↓
          CANCELLED                 (terminal)
```

**Valid transitions:**
- `PENDING` → `RUNNING`, `FAILED`, `CANCELLED`
- `RUNNING` → `WAITING`, `PAUSED`, `COMPLETED`, `FAILED`, `CANCELLED`
- `WAITING` → `RUNNING`, `FAILED`, `CANCELLED`
- `PAUSED` → `RUNNING`, `FAILED`, `CANCELLED`
- `FAILED` → `RUNNING` (via `resume()`), `CANCELLED`
- Terminal states (`COMPLETED`, `CANCELLED`) → no further transitions

`FAILED` is **recoverable, not terminal**: calling `resume()` puts the instance
back into `RUNNING` and retries the step that failed, which is how you recover a
workflow once the underlying cause is fixed.

A workflow whose next steps are all blocked on unmet prerequisites parks in
`WAITING` rather than sitting in `RUNNING`, so a stuck instance is
distinguishable from one still executing.

State transitions are validated at runtime — invalid transitions throw `InvalidWorkflowStateException`.

### Namespace Map

| Namespace | Contents |
|-----------|----------|
| `Core\` | WorkflowEngine, WorkflowBuilder, Executor, StateManager, WorkflowInstance, WorkflowDefinition, WorkflowContext, ActionResult, Step, DefinitionParser, ActionResolver |
| `Actions\` | BaseAction, LogAction, FakeEmailAction, HttpAction, DelayAction, ConditionAction |
| `Contracts\` | WorkflowAction, StorageAdapter, EventDispatcher, Logger |
| `Attributes\` | WorkflowStep, Retry, Timeout, Condition |
| `Events\` | WorkflowStartedEvent, WorkflowCompletedEvent, WorkflowFailedEvent, WorkflowCancelledEvent, StepCompletedEvent, StepFailedEvent, StepRetriedEvent |
| `Exceptions\` | WorkflowException, InvalidWorkflowDefinitionException, InvalidWorkflowStateException, ActionNotFoundException, StepExecutionException, WorkflowInstanceNotFoundException |
| `Support\` | NullLogger, NullEventDispatcher, SimpleWorkflow, Uuid, Timeout, ConditionEvaluator, Arr |

### Built-in Actions

Six ready-to-use actions are included:

| Action | Purpose | Config Keys |
|--------|---------|-------------|
| **LogAction** | Log messages with placeholder replacement (`{user.name}`) | `message`, `level` (debug/info/warning/error) |
| **FakeEmailAction** | ⚠️ Records an email; **does not send one** (no mail transport ships with this package) | `to`, `subject`, `template`, `data` |
| **HttpAction** | HTTP requests with `{{ variable }}` template variables (requires `ext-curl`) | `url`, `method`, `data`, `headers`, `timeout`, `connect_timeout`, `verify_tls`, `max_redirects` |
| **DelayAction** | Pause execution for a specified duration (blocking) | `hours`, `minutes`, `seconds`, `microseconds` |
| **ConditionAction** | Evaluate a condition and record the result in workflow data | `condition` |
| **BaseAction** | Abstract base class for custom actions | — |

### WorkflowState Helpers

The `WorkflowState` enum provides utility methods for UI and logic:

```php
$state = $instance->getState();

$state->isActive();        // true for PENDING, RUNNING, WAITING, PAUSED
$state->isFinished();      // true for COMPLETED, FAILED, CANCELLED
$state->isSuccessful();    // true for COMPLETED
$state->isError();         // true for FAILED
$state->label();           // "Running"
$state->description();     // "The workflow is actively executing steps..."
$state->color();           // "blue" (gray, blue, yellow, orange, green, red, purple)
$state->icon();            // "▶️"
$state->canTransitionTo(WorkflowState::COMPLETED); // bool
$state->getValidTransitions(); // [WorkflowState::WAITING, ...]
```

## 🔧 Configuration

### Storage Adapters

Implement the `StorageAdapter` interface for custom persistence:

```php
use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;

class DatabaseStorageAdapter implements StorageAdapter
{
    public function save(WorkflowInstance $instance): void { /* ... */ }
    public function load(string $id): WorkflowInstance { /* ... */ }
    public function findInstances(array $criteria = []): array { /* ... */ }
    public function delete(string $id): void { /* ... */ }
    public function exists(string $id): bool { /* ... */ }
    public function updateState(string $id, array $updates): void { /* ... */ }
}
```

### Event Handling

Listen to workflow events — 7 event types are dispatched during execution:

```php
use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;
use SolutionForest\WorkflowEngine\Events\WorkflowStartedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowCompletedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowFailedEvent;
use SolutionForest\WorkflowEngine\Events\WorkflowCancelledEvent;
use SolutionForest\WorkflowEngine\Events\StepCompletedEvent;
use SolutionForest\WorkflowEngine\Events\StepFailedEvent;
use SolutionForest\WorkflowEngine\Events\StepRetriedEvent;

class CustomEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        match ($event::class) {
            WorkflowStartedEvent::class => $this->onWorkflowStarted($event),
            WorkflowCompletedEvent::class => $this->onWorkflowCompleted($event),
            WorkflowFailedEvent::class => $this->onWorkflowFailed($event),
            StepCompletedEvent::class => $this->onStepCompleted($event),
            StepFailedEvent::class => $this->onStepFailed($event),
            StepRetriedEvent::class => $this->onStepRetried($event),
            default => null,
        };
    }
}
```

### Logging

Provide a custom logging implementation (PSR-3 style):

```php
use SolutionForest\WorkflowEngine\Contracts\Logger;

class CustomLogger implements Logger
{
    public function info(string $message, array $context = []): void { /* ... */ }
    public function warning(string $message, array $context = []): void { /* ... */ }
    public function error(string $message, array $context = []): void { /* ... */ }
    public function debug(string $message, array $context = []): void { /* ... */ }
}
```

## 🧪 Development

### Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run specific test file
vendor/bin/pest tests/Unit/WorkflowEngineTest.php

# Run tests with detailed output
vendor/bin/pest --verbose
```

### Code Quality

We use several tools to maintain high code quality:

```bash
# Static analysis with PHPStan
composer analyze

# Code formatting with Laravel Pint
composer pint

# Check code formatting without making changes
composer pint --test

# Run all quality checks
composer pint && composer analyze && composer test
```

### Development Tools

- **[Pest](https://pestphp.com/)** - Testing framework with expressive syntax
- **[Pest Architecture Testing](https://pestphp.com/docs/arch-testing)** - Architectural constraints and code quality rules
- **[PHPStan](https://phpstan.org/)** - Static analysis tool for catching bugs
- **[Laravel Pint](https://laravel.com/docs/pint)** - Code style fixer built on PHP-CS-Fixer
- **Framework-agnostic** - No Laravel dependencies in the core library

### Configuration Files

- `phpstan.neon.dist` - PHPStan configuration for static analysis
- `pint.json` - Laravel Pint configuration for code formatting  
- `phpunit.xml.dist` - PHPUnit configuration for testing
- `.github/workflows/run-tests.yml` - CI/CD pipeline configuration

### Quality Standards

We maintain high code quality through:

- **100% PHPStan Level 6** - Static analysis with zero errors across 46 source files
- **Laravel Pint** - Consistent code formatting following Laravel standards
- **Comprehensive Testing** - 93 tests with 224+ assertions covering unit, integration, and real-world scenarios
- **Architecture Tests** - Automated checks preventing debug functions in source code
- **State Transition Validation** - Runtime enforcement of valid workflow state transitions
- **Type Safety** - Full PHP 8.3+ type declarations throughout
- **Continuous Integration** - Automated quality checks on every commit (PHP 8.3/8.4 matrix)

## 📚 Framework Integrations

This core library is framework-agnostic. For specific framework integrations:

- **Laravel**: Use [`solution-forest/workflow-engine-laravel`](https://packagist.org/packages/solution-forest/workflow-engine-laravel)
- **Symfony**: Coming soon
- **Other frameworks**: Easily integrate using the provided interfaces

## 🤝 Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Credits

- **Solution Forest Team** - Initial development and maintenance
- **Contributors** - Thank you to all contributors who have helped improve this project

## 🔗 Links

- [Issues](https://github.com/solutionforest/workflow-engine-core/issues)
- [Changelog](https://github.com/solutionforest/workflow-engine-core/blob/main/CHANGELOG.md)
- [Laravel Integration](https://github.com/solutionforest/workflow-engine-laravel)
