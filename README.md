# Workflow Engine Core

[![Tests](https://github.com/solution-forest/workflow-engine-core/workflows/run-tests/badge.svg)](https://github.com/solution-forest/workflow-engine-core/actions)
[![PHPStan](https://github.com/solution-forest/workflow-engine-core/workflows/phpstan/badge.svg)](https://github.com/solution-forest/workflow-engine-core/actions)
[![Latest Stable Version](https://poser.pugx.org/solution-forest/workflow-engine-core/v/stable)](https://packagist.org/packages/solution-forest/workflow-engine-core)
[![Total Downloads](https://poser.pugx.org/solution-forest/workflow-engine-core/downloads)](https://packagist.org/packages/solution-forest/workflow-engine-core)
[![License](https://poser.pugx.org/solution-forest/workflow-engine-core/license)](https://packagist.org/packages/solution-forest/workflow-engine-core)

A powerful, framework-agnostic workflow engine for PHP applications. This core library provides comprehensive workflow definition, execution, and state management capabilities without any framework dependencies.

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

## 📦 Installation

```bash
composer require solution-forest/workflow-engine-core
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

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run static analysis
composer analyze
```

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
