# AGENTS.md

Instructions for AI agents working on this codebase. Read CLAUDE.md first for project context.

---

## General Rules

1. **Read before you write.** Never modify a file you haven't read. Understand the existing patterns.
2. **Zero production dependencies.** This is a framework-agnostic library. Never add runtime `require` entries to composer.json. Dev dependencies are fine.
3. **PHP 8.3+ only.** Use readonly properties, backed enums, match expressions, named arguments, constructor promotion. No legacy patterns.
4. **Run the full quality pipeline** after any change: `composer ci` (pint:test + analyze + test).
5. **PHPStan level 6.** All new code must pass. Don't suppress errors unless truly unavoidable—and document why.
6. **Pest PHP for tests.** Use `test()` syntax, `expect()` assertions, `describe()` blocks. No raw PHPUnit `$this->assert*()`.
7. **Laravel Pint formatting.** Run `composer pint` before committing. The CI will reject improperly formatted code.

---

## Agent: Code Review

When reviewing PRs or code changes for this project:

### What to Check

- **Contract compliance**: Does new code implement `WorkflowAction`, `StorageAdapter`, `EventDispatcher`, or `Logger` correctly?
- **Immutability**: `WorkflowContext`, `ActionResult`, `WorkflowDefinition` are value objects. Don't add setters or mutation methods.
- **State transitions**: Any code that calls `setState()` must respect the transition rules in `WorkflowState::canTransitionTo()`. Invalid transitions are bugs.
- **Action instantiation**: Actions are created with `new $actionClass($config, $logger)`. If you change the `WorkflowAction` interface or `BaseAction` constructor, you must update `Executor::executeAction()`.
- **No framework coupling**: This package must not `use` any Laravel, Symfony, or other framework classes in `src/`. The only exception is `data_get()`/`data_set()` helpers (which need to be replaced—see Known Issues).
- **Exception hierarchy**: All exceptions must extend `WorkflowException`. Use the static factory methods (e.g., `InvalidWorkflowDefinitionException::invalidName()`), not raw `new` constructors.
- **Event consistency**: Every state-changing operation should dispatch the appropriate event. Check that `WorkflowStarted`, `WorkflowCompleted`, `WorkflowFailed`, `WorkflowCancelled`, `StepCompleted`, `StepFailed` are dispatched at the right moments.

### What to Reject

- Adding `declare(strict_types=1)` piecemeal—if we add it, it goes in every file at once.
- Magic string config keys without constants or typed config objects.
- Suppressing PHPStan errors without a clear comment explaining why.
- Tests that use `assertTrue(true)` or other vacuous assertions.
- New public API methods without PHPDoc `@param`, `@return`, and `@throws` tags.

---

## Agent: Testing

### Test Structure

```
tests/
├── Unit/              # Isolated class tests (no I/O, no storage)
├── Integration/       # Multi-step workflow execution through the engine
├── RealWorld/         # Complex production-like scenarios
├── Actions/ECommerce/ # Custom action fixtures used by RealWorld tests
├── Support/           # Test helpers (InMemoryStorage)
├── TestCase.php       # Base class: provides $this->engine + $this->storage
├── Pest.php           # Pest config
├── ArchTest.php       # Architecture constraints
└── ExampleTest.php    # Sanity check
```

### Writing Tests

```php
// Good: descriptive, focused, uses Pest syntax
test('workflow transitions to failed state when action throws', function () {
    $definition = [
        'name' => 'failing-workflow',
        'steps' => [
            ['id' => 'bad_step', 'action' => NonExistentAction::class],
        ],
    ];

    expect(fn () => $this->engine->start('test-fail', $definition))
        ->toThrow(ActionNotFoundException::class);
});

// Good: grouped with describe
describe('WorkflowBuilder', function () {
    test('validates step IDs', function () { ... });
    test('rejects empty workflow names', function () { ... });
});
```

### Test Gaps to Fill

These areas currently lack test coverage. Prioritize them when writing new tests:

1. **Event dispatch verification** — No tests confirm events are actually dispatched. Create a `SpyEventDispatcher` that records dispatched events and assert against it.
2. **Retry logic** — `Step` supports `retryAttempts` but the `Executor` never actually retries. When retry is implemented, add tests for 0, 1, and max retries.
3. **HTTP/Email actions** — `HttpAction` and `EmailAction` have no unit tests. Mock the underlying operations and test config validation, error handling, and result mapping.
4. **Storage adapter edge cases** — Only `InMemoryStorage` is tested. Add tests for: loading a non-existent instance, concurrent saves, findInstances with various filter combinations.
5. **Condition evaluation** — The regex-based condition parser in `Step::evaluateCondition()` silently returns `true` for unparseable conditions. Test edge cases: nested properties, numeric comparisons, boolean values, empty strings.
6. **Compensation actions** — `Step` supports `compensationAction` but nothing executes it. When implemented, test rollback sequences.
7. **Pause/resume cycles** — Test multiple pause → resume → pause sequences and verify state consistency.

---

## Agent: Implementation

### Before Writing Code

1. Check if the feature touches any contract interface. Interface changes require updates to all implementations (including `InMemoryStorage`, `NullLogger`, `NullEventDispatcher`).
2. Check if the change affects the builder API. Builder changes should maintain backward compatibility—add new methods, don't change existing signatures.
3. Check if new exceptions are needed. Use the existing hierarchy and static factory pattern.

### Patterns to Follow

**Creating a new Action:**
```php
class MyAction extends BaseAction
{
    protected function doExecute(WorkflowContext $context): ActionResult
    {
        $value = $this->getConfig('key');
        // ... business logic ...
        return ActionResult::success(['output' => $result]);
    }

    public function getName(): string
    {
        return 'My Action';
    }

    public function getDescription(): string
    {
        return 'Does something specific';
    }
}
```

**Creating a new Exception:**
```php
class MyException extends WorkflowException
{
    public static function specificCase(string $id): self
    {
        return new self(
            message: "Technical: thing failed for {$id}",
            userMessage: "The operation could not be completed.",
            suggestions: ['Check the configuration', 'Verify the ID exists'],
            context: ['id' => $id]
        );
    }
}
```

**Creating a new Event:**
```php
class MyEvent
{
    public function __construct(
        public readonly string $workflowId,
        public readonly string $detail,
        public readonly \DateTimeInterface $occurredAt = new \DateTime(),
    ) {}
}
```

---

## Known Issues and Improvement Roadmap

These are real issues found through code review, ordered by impact. This is not a wishlist—these are bugs, missing implementations, and design problems that need fixing before v1.0.

### Critical — Blocks Production Use

**1. Timeout is configured but never enforced**
`Step` accepts a `timeout` parameter. `WorkflowBuilder` validates it. But `Executor::executeStep()` never checks it. A misbehaving action can hang the entire process indefinitely. The TODO comment at `Executor.php:230` confirms this is known.

*Fix:* Implement timeout enforcement in `Executor::executeStep()` using `pcntl_alarm()` or a wrapper that throws `StepExecutionException` on timeout. Add a `TimeoutException` to the exception hierarchy.

**2. Retry logic is declared but never executed**
`Step::getRetryAttempts()` returns a value, but `Executor` catches exceptions and immediately fails. No retry loop exists anywhere in the execution path.

*Fix:* Add a retry loop in `Executor::executeStep()` with exponential backoff. Dispatch `StepRetryEvent` on each retry attempt. Track attempt count on the `WorkflowInstance`.

**3. Compensation actions are defined but never called**
`Step` supports `compensationAction` and `hasCompensation()`. Nothing in the codebase ever calls a compensation action. The Saga pattern is advertised but not implemented.

*Fix:* When a step fails, walk backward through completed steps and execute their compensation actions. Add a `CompensationExecutedEvent`. This is a significant feature — design it before implementing.

**4. `data_get()` / `data_set()` Laravel helper dependency**
`Step::evaluateCondition()` and `WorkflowDefinition::evaluateCondition()` call `data_get()`, a Laravel helper. This function doesn't exist in non-Laravel environments. The package advertises itself as framework-agnostic but will throw a fatal error without Laravel's helpers.

*Fix:* Implement a simple `Support\Arr::get()` utility that handles dot-notation access, or inline the logic. Remove the PHPStan suppression for `data_get`/`data_set`.

### High — Correctness Issues

**5. Duplicate condition evaluation logic**
`Step::evaluateCondition()` (line 221) and `WorkflowDefinition::evaluateCondition()` contain identical regex-based condition parsing. This violates DRY and means bug fixes must be applied in two places.

*Fix:* Extract to a `Support\ConditionEvaluator` class. Both `Step` and `WorkflowDefinition` should delegate to it.

**6. Silent condition parsing failures**
`Step::evaluateCondition()` returns `true` when it can't parse a condition (line 244). This means a typo in a condition expression (`user.plan = "premium"` instead of `===`) will silently pass, executing steps that should be skipped.

*Fix:* Throw `InvalidWorkflowDefinitionException` for unparseable conditions, or at minimum log a warning. Never silently succeed.

**7. Inconsistent event class naming**
Some events end with `Event` (`StepCompletedEvent`, `WorkflowCompletedEvent`, `WorkflowFailedEvent`) and some don't (`WorkflowStarted`, `WorkflowCancelled`). Pick one convention and stick with it.

*Fix:* Rename all to `*Event` suffix for consistency: `WorkflowStartedEvent`, `WorkflowCancelledEvent`.

**8. Duplicate API methods on WorkflowEngine**
`getInstance()` and `getWorkflow()` do the same thing (lines 203 and 265). `getInstances()` and `listWorkflows()` do the same thing (lines 238 and 294). This confuses consumers and doubles the API surface.

*Fix:* Deprecate `getWorkflow()` and `listWorkflows()`. Keep `getInstance()` and `getInstances()` as the canonical API. Remove the deprecated methods before v1.0.

### Medium — Design Improvements

**9. Action constructor not enforced by contract**
`Executor::executeAction()` (line 300) does `new $actionClass($config, $logger)`. The `WorkflowAction` interface doesn't define a constructor, so custom actions with different constructors will fail at runtime with a cryptic error.

*Fix:* Either document the constructor contract clearly, add a static factory method to the interface (`WorkflowAction::make($config, $logger)`), or use an `ActionFactory` that can be overridden for DI containers.

**10. `WorkflowInstance` does too much**
`WorkflowInstance` handles state tracking, progress calculation, step management, data merging, serialization, and error recording. It's a god object.

*Fix:* Extract `WorkflowProgress` (progress calculation, step completion tracking) and `WorkflowSerializer` (toArray/fromArray) into separate concerns. Keep `WorkflowInstance` focused on identity and state.

**11. No middleware/pipeline for cross-cutting concerns**
Retry, timeout, logging, and metrics all need to wrap step execution. Currently there's no clean way to add these behaviors without modifying `Executor` directly.

*Fix:* Implement a `StepMiddleware` interface and a pipeline in `Executor` that chains middleware around action execution. Ship `RetryMiddleware`, `TimeoutMiddleware`, and `LoggingMiddleware` as built-in implementations.

**12. Condition evaluator is too limited**
The regex-based condition parser only supports simple binary comparisons. No AND/OR, no grouping, no function calls. This limits real-world usefulness significantly.

*Fix:* Consider a simple expression parser or adopt a lightweight expression language. At minimum, support AND (`&&`) and OR (`||`) operators.

### Low — Polish

**13. Missing `declare(strict_types=1)`**
No source file declares strict types. This allows implicit type coercion which can hide bugs.

**14. PHPStan could be stricter**
Level 6 is good but not maximum. Several errors are suppressed in `phpstan.neon.dist`. Work toward level 8 by fixing the underlying type issues rather than suppressing them.

**15. Excessive inline documentation**
Some classes (e.g., `WorkflowContext`) have 600+ lines with extensive code examples in PHPDoc. This makes files hard to navigate. Move examples to a `docs/` directory or the README.

**16. No integration test for event dispatching**
The event system is a core feature but no test verifies that events are dispatched. Add a `SpyEventDispatcher` to the test support classes and use it in integration tests.

---

## PR Checklist

Before approving any PR, verify:

- [ ] `composer ci` passes (pint:test + phpstan + pest)
- [ ] New public methods have `@param`, `@return`, and `@throws` PHPDoc
- [ ] No new PHPStan suppressions without justification
- [ ] No framework-specific imports in `src/`
- [ ] State transitions respect `WorkflowState::canTransitionTo()`
- [ ] New features have corresponding tests in the appropriate directory
- [ ] Exception messages are helpful (use static factory methods with context)
- [ ] Events are dispatched for state-changing operations
- [ ] No `dd()`, `dump()`, `var_dump()`, or `ray()` calls (ArchTest enforces this)
- [ ] Builder API changes are backward-compatible
