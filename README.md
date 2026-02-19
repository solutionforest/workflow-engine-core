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

// Create engine with storage adapter and event dispatcher
$engine = new WorkflowEngine($storageAdapter, $eventDispatcher);

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
    ->email('order-confirmation', 'customer@example.com', 'Order Confirmed')
    ->build();

// Quick templates for common patterns
$workflow = WorkflowBuilder::quick()->userOnboarding();
$workflow = WorkflowBuilder::quick()->orderProcessing();
$workflow = WorkflowBuilder::quick()->documentApproval();
```

### PHP 8.3+ Attributes

Use native PHP attributes to configure actions with retry, timeout, and conditions:

#### Retry Logic
```php
use SolutionForest\WorkflowEngine\Attributes\Retry;

#[Retry(attempts: 3, backoff: 'exponential', delay: 1000)]
class ReliableApiAction extends BaseAction
{
    public function execute(WorkflowContext $context): ActionResult
    {
        // Retries up to 3 times with exponential backoff starting at 1s
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
        // Will timeout after 30 seconds
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
        // Only executes when order.amount > 100
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

### Workflow Lifecycle Management

```php
// Start, pause, resume, cancel
$instanceId = $engine->start('my-workflow', $definition->toArray(), ['key' => 'value']);
$instance = $engine->getInstance($instanceId);
$engine->cancel($instanceId, 'No longer needed');

// Query instances
$instances = $engine->getInstances(['state' => 'running']);
$status = $engine->getStatus('my-workflow');
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
PENDING → RUNNING → COMPLETED
    ↓         ↓ ↑
  FAILED   WAITING
    ↑         ↓ ↑
  FAILED ← PAUSED
    ↑
CANCELLED ← (any non-terminal state)
```

**Valid transitions:**
- `PENDING` → `RUNNING`, `FAILED`, `CANCELLED`
- `RUNNING` → `WAITING`, `PAUSED`, `COMPLETED`, `FAILED`, `CANCELLED`
- `WAITING` → `RUNNING`, `FAILED`, `CANCELLED`
- `PAUSED` → `RUNNING`, `FAILED`, `CANCELLED`
- Terminal states (`COMPLETED`, `FAILED`, `CANCELLED`) → no further transitions

State transitions are validated at runtime — invalid transitions throw `InvalidWorkflowStateException`.

### Namespace Map

| Namespace | Contents |
|-----------|----------|
| `Core\` | WorkflowEngine, WorkflowBuilder, Executor, StateManager, WorkflowInstance, WorkflowDefinition, WorkflowContext, ActionResult, Step, DefinitionParser, ActionResolver |
| `Actions\` | BaseAction, LogAction, EmailAction, HttpAction, DelayAction, ConditionAction |
| `Contracts\` | WorkflowAction, StorageAdapter, EventDispatcher, Logger |
| `Attributes\` | WorkflowStep, Retry, Timeout, Condition |
| `Events\` | WorkflowStartedEvent, WorkflowCompletedEvent, WorkflowFailedEvent, WorkflowCancelledEvent, StepCompletedEvent, StepFailedEvent, StepRetriedEvent |
| `Exceptions\` | WorkflowException, InvalidWorkflowDefinitionException, InvalidWorkflowStateException, ActionNotFoundException, StepExecutionException, WorkflowInstanceNotFoundException |
| `Support\` | NullLogger, NullEventDispatcher, SimpleWorkflow, Uuid, Timeout, ConditionEvaluator, Arr |


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

- [Documentation](https://github.com/solution-forest/workflow-engine-core/docs)
- [Issues](https://github.com/solution-forest/workflow-engine-core/issues)
- [Changelog](https://github.com/solution-forest/workflow-engine-core/blob/main/CHANGELOG.md)
- [Laravel Integration](https://github.com/solution-forest/workflow-engine-laravel)
