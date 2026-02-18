# CLAUDE.md

Project context for Claude Code and AI-assisted development.

## Project Overview

**workflow-engine-core** is a framework-agnostic PHP workflow engine. Zero production dependencies. PHP 8.3+. MIT licensed.

Status: **v0.0.2-alpha** — active development, not production-ready.

Related package: `solution-forest/workflow-engine-laravel` (Laravel integration layer).

## Quick Reference

```bash
# Run tests
composer test

# Run tests with coverage
composer test:coverage

# Static analysis (PHPStan level 6)
composer analyze

# Code formatting (Laravel Pint)
composer pint

# Check formatting without changes (CI mode)
composer pint:test

# Full quality check: format + analyze + test
composer quality

# CI pipeline: check format + analyze + test
composer ci
```

## Architecture

```
WorkflowBuilder → WorkflowDefinition → WorkflowEngine → Executor → Actions
                                              ↓
                                        StateManager → StorageAdapter
                                              ↓
                                        EventDispatcher
```

### Namespace Map

| Namespace | Purpose |
|-----------|---------|
| `Core\` | Engine, Builder, Executor, StateManager, Step, WorkflowInstance, WorkflowDefinition, WorkflowContext, ActionResult |
| `Actions\` | Built-in actions: Log, Email, Http, Delay, Condition (all extend BaseAction) |
| `Contracts\` | Interfaces: WorkflowAction, StorageAdapter, EventDispatcher, Logger |
| `Attributes\` | PHP 8 attributes: WorkflowStep, Retry, Timeout, Condition |
| `Events\` | WorkflowStarted, WorkflowCompleted, WorkflowFailed, WorkflowCancelled, StepCompleted, StepFailed |
| `Exceptions\` | WorkflowException (base), InvalidWorkflowDefinition, InvalidWorkflowState, ActionNotFound, StepExecution, WorkflowInstanceNotFound |
| `Support\` | NullLogger, NullEventDispatcher, SimpleWorkflow, Uuid |

### State Machine

```
PENDING → RUNNING → COMPLETED
              ↓ ↑
           WAITING
              ↓ ↑
            PAUSED
              ↓
            FAILED

CANCELLED ← (any non-terminal state)
```

Terminal states: COMPLETED, FAILED, CANCELLED.

### Key Contracts

Every custom integration implements one of these:

- **`WorkflowAction`** — `execute(WorkflowContext): ActionResult` + `canExecute(WorkflowContext): bool`
- **`StorageAdapter`** — `save()`, `load()`, `findInstances()`, `delete()`, `exists()`, `updateState()`
- **`EventDispatcher`** — `dispatch(object $event): void`
- **`Logger`** — PSR-3 style: `info()`, `error()`, `warning()`, `debug()`

### How Actions Work

Actions are instantiated by `Executor` with `new $actionClass($config, $logger)`. They receive a `WorkflowContext` containing workflowId, stepId, data, config, and the instance reference. They return `ActionResult::success($data)` or `ActionResult::failure($message)`.

## Conventions

### Code Style
- **Laravel Pint** with Laravel preset
- Alphabetically ordered imports
- Short array syntax `[]`
- No trailing commas in single-line arrays
- PHPDoc left-aligned

### Testing
- **Pest PHP 2.0** with PHPUnit 10 base
- Test structure: `tests/Unit/`, `tests/Integration/`, `tests/RealWorld/`
- Base test class: `TestCase` (provides `$this->engine` and `$this->storage` via `InMemoryStorage`)
- Use `test('description', function () { ... })` syntax
- Use `describe()` blocks for grouping related tests
- Use `expect()` fluent assertions, not `$this->assert*()`
- Architecture tests in `tests/ArchTest.php` — no `dd`, `dump`, `ray` calls

### Naming
- Step IDs: `snake_case`, must match `/^[a-zA-Z][a-zA-Z0-9_-]*$/`
- Workflow names: `kebab-case` (e.g., `user-onboarding`, `order-processing`)
- Action classes: PascalCase ending in `Action` (e.g., `ProcessPaymentAction`)
- Event classes: PascalCase ending in `Event` (e.g., `StepCompletedEvent`)
- Exception classes: PascalCase ending in `Exception`

### PHP Features Used
- PHP 8.3 readonly properties and classes
- Backed enums with methods (`WorkflowState`)
- Named arguments throughout builder API
- Match expressions instead of switch
- Union types (`string|WorkflowAction`)
- Constructor promotion

### Builder API Pattern

```php
// Fluent builder (primary API)
$workflow = WorkflowBuilder::create('order-flow')
    ->description('Process customer orders')
    ->addStep('validate', ValidateOrderAction::class)
    ->when('order.total > 1000', function ($builder) {
        $builder->addStep('fraud_check', FraudCheckAction::class);
    })
    ->addStep('payment', ProcessPaymentAction::class, timeout: 300, retryAttempts: 3)
    ->build();

// Quick templates
$workflow = WorkflowBuilder::quick()->userOnboarding();
$workflow = WorkflowBuilder::quick()->orderProcessing();
```

### Running a Workflow

```php
$engine = new WorkflowEngine($storageAdapter, $eventDispatcher);
$instanceId = $engine->start('my-workflow', $definition->toArray(), ['key' => 'value']);
$instance = $engine->getInstance($instanceId);
$engine->cancel($instanceId, 'reason');
```

## CI/CD

GitHub Actions workflows:
- `run-tests.yml` — Matrix: PHP 8.3/8.4 × prefer-lowest/prefer-stable
- `phpstan.yml` — Static analysis on .php changes
- `fix-php-code-style-issues.yml` — Auto-format with Pint on push
- `update-changelog.yml` — Auto-update CHANGELOG on release
- `dependabot-auto-merge.yml` — Auto-merge minor/patch dependency updates

## File Counts

- 42 source files in `src/`
- 18 test files in `tests/`
- 40+ test cases, 160+ assertions
- PHPStan level 6 compliance
