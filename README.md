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
- **🧪 Well Tested**: Comprehensive test suite with 160+ assertions

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

// Create a workflow
$workflow = WorkflowBuilder::create('order-processing')
    ->addStep('validate', ValidateOrderAction::class)
    ->addStep('payment', ProcessPaymentAction::class)
    ->addStep('fulfillment', FulfillOrderAction::class)
    ->addTransition('validate', 'payment')
    ->addTransition('payment', 'fulfillment')
    ->build();

// Execute the workflow
$engine = new WorkflowEngine();
$context = new WorkflowContext(
    workflowId: 'order-processing',
    stepId: 'validate',
    data: ['order_id' => 123, 'customer_id' => 456]
);

$instance = $engine->start($workflow, $context);
$result = $engine->executeStep($instance, $context);
```

### Advanced Features

#### Conditional Steps
```php
use SolutionForest\WorkflowEngine\Attributes\Condition;

class ConditionalAction extends BaseAction
{
    #[Condition("data.amount > 1000")]
    public function execute(WorkflowContext $context): ActionResult
    {
        // This action only executes if amount > 1000
        return ActionResult::success();
    }
}
```

#### Retry Logic
```php
use SolutionForest\WorkflowEngine\Attributes\Retry;

class ReliableAction extends BaseAction
{
    #[Retry(maxAttempts: 3, delay: 1000)]
    public function execute(WorkflowContext $context): ActionResult
    {
        // This action will retry up to 3 times with 1 second delay
        return ActionResult::success();
    }
}
```

#### Timeouts
```php
use SolutionForest\WorkflowEngine\Attributes\Timeout;

class TimedAction extends BaseAction
{
    #[Timeout(seconds: 30)]
    public function execute(WorkflowContext $context): ActionResult
    {
        // This action will timeout after 30 seconds
        return ActionResult::success();
    }
}
```

## 🏗️ Architecture

The workflow engine follows a clean architecture pattern with clear separation of concerns:

```
┌─────────────────┐
│   Workflow      │
│   Builder       │
└─────────────────┘
         │
         ▼
┌─────────────────┐    ┌─────────────────┐
│   Workflow      │◄───│   Workflow      │
│   Definition    │    │   Engine        │
└─────────────────┘    └─────────────────┘
         │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐
│     Steps       │    │    Executor     │
│   & Actions     │    │                 │
└─────────────────┘    └─────────────────┘
         │                       │
         ▼                       ▼
┌─────────────────┐    ┌─────────────────┐
│     State       │    │     Events      │
│   Manager       │    │   Dispatcher    │
└─────────────────┘    └─────────────────┘
```


#### 📝 **Workflow Builder**
- **Purpose**: Fluent interface for creating workflow definitions
- **Responsibilities**: 
  - Provides method chaining (`.addStep()`, `.when()`, `.email()`, etc.)
  - Validates workflow structure during construction
  - Creates immutable workflow definitions
  - Supports conditional steps and common patterns
- **Example**: `WorkflowBuilder::create('user-onboarding')->addStep(...)->build()`

#### 📋 **Workflow Definition**
- **Purpose**: Immutable data structure representing a complete workflow
- **Responsibilities**:
  - Contains workflow metadata (name, description, version)
  - Stores all steps and their relationships
  - Defines step execution order and conditions
  - Serves as a blueprint for workflow execution
- **Key data**: Steps, transitions, conditions, metadata

#### ⚡ **Workflow Engine**
- **Purpose**: Central orchestrator that manages workflow execution
- **Responsibilities**:
  - Starts new workflow instances from definitions
  - Manages workflow lifecycle (start, pause, resume, cancel)
  - Coordinates between different components
  - Provides API for workflow operations
- **Main methods**: `start()`, `pause()`, `resume()`, `cancel()`, `getInstance()`

#### 🎯 **Steps & Actions**
- **Purpose**: Individual workflow tasks and their implementations
- **Responsibilities**:
  - **Steps**: Define what should happen (metadata, config, conditions)
  - **Actions**: Implement the actual business logic (`execute()` method)
  - Handle step-specific configuration (timeout, retry, conditions)
  - Support compensation actions for rollback scenarios
- **Examples**: `SendEmailAction`, `CreateUserAction`, `ValidateOrderAction`

#### 🎬 **Executor**
- **Purpose**: Runtime engine that executes individual workflow steps
- **Responsibilities**:
  - Executes actions in the correct sequence
  - Handles conditional execution based on workflow context
  - Manages timeouts and retry logic
  - Processes step transitions and flow control
  - Handles errors and compensation

#### 🗄️ **State Manager**
- **Purpose**: Component responsible for workflow instance state persistence
- **Responsibilities**:
  - Saves/loads workflow instances to/from storage
  - Tracks workflow execution state (running, paused, completed, failed)
  - Manages workflow context data
  - Handles state transitions and validation
  - Supports different storage adapters (database, file, memory)

#### 📡 **Events Dispatcher**
- **Purpose**: Event system for monitoring and integration
- **Responsibilities**:
  - Fires events during workflow execution
  - Enables workflow monitoring and logging
  - Supports custom event listeners
  - Provides hooks for external system integration
  - Events: `WorkflowStarted`, `StepCompleted`, `WorkflowFailed`, etc.

### 🔄 **Data Flow**
1. **Builder** → creates → **Definition**
2. **Engine** → uses **Definition** to create instances
3. **Engine** → delegates to **Executor** for step execution
4. **Executor** → runs → **Steps & Actions**
5. **State Manager** → persists → workflow state
6. **Events Dispatcher** → broadcasts → execution events

### ✅ **Architecture Benefits**
- **Separation of concerns** - each component has a single responsibility
- **Extensibility** - you can swap out storage adapters, add custom actions
- **Testability** - each component can be tested independently
- **Framework agnostic** - no dependencies on specific frameworks
- **Type safety** - full PHP 8.3+ type hints throughout


## 🔧 Configuration

### Storage Adapters

Implement the `StorageAdapter` interface for custom storage:

```php
use SolutionForest\WorkflowEngine\Contracts\StorageAdapter;

class CustomStorageAdapter implements StorageAdapter
{
    public function save(WorkflowInstance $instance): void
    {
        // Save workflow instance to your storage
    }

    public function load(string $instanceId): ?WorkflowInstance
    {
        // Load workflow instance from your storage
    }

    public function delete(string $instanceId): void
    {
        // Delete workflow instance from your storage
    }
}
```

### Event Handling

Listen to workflow events:

```php
use SolutionForest\WorkflowEngine\Contracts\EventDispatcher;

class CustomEventDispatcher implements EventDispatcher
{
    public function dispatch(object $event): void
    {
        // Handle workflow events
        match (get_class($event)) {
            'SolutionForest\WorkflowEngine\Events\WorkflowStarted' => $this->onWorkflowStarted($event),
            'SolutionForest\WorkflowEngine\Events\StepCompletedEvent' => $this->onStepCompleted($event),
            'SolutionForest\WorkflowEngine\Events\WorkflowCompletedEvent' => $this->onWorkflowCompleted($event),
            default => null,
        };
    }
}
```

### Logging

Provide custom logging implementation:

```php
use SolutionForest\WorkflowEngine\Contracts\Logger;

class CustomLogger implements Logger
{
    public function info(string $message, array $context = []): void
    {
        // Log info messages
    }

    public function error(string $message, array $context = []): void
    {
        // Log error messages
    }

    public function warning(string $message, array $context = []): void
    {
        // Log warning messages
    }
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

- **100% PHPStan Level 6** - Static analysis with no errors
- **Laravel Pint** - Consistent code formatting following Laravel standards
- **Comprehensive Testing** - 40 tests with 160+ assertions covering all core functionality
- **Type Safety** - Full PHP 8.3+ type declarations and documentation
- **Continuous Integration** - Automated quality checks on every commit

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
