# Implementation Plan: workflow-engine-core Improvements

## Philosophy

Fix the foundation before adding features. Every change below makes the engine
more honest — the public API already promises these behaviors, we just need the
internals to deliver. Ordered by dependency graph: earlier phases unblock later ones.

---

## Phase 1: Remove Laravel Coupling (Critical)

The package claims "zero production dependencies" but calls `data_get()` which
only exists in Laravel. This is a fatal error in any non-Laravel environment.

### 1a. Create `Support\Arr` helper

**New file:** `src/Support/Arr.php`

```php
final class Arr
{
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        // Handle direct key match first
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // Dot-notation traversal
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    public static function set(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
```

### 1b. Replace all `data_get()` calls

- `src/Core/Step.php:229` — replace `data_get($data, $key)` with `Arr::get($data, $key)`
- Remove `WorkflowDefinition::getNestedValue()` private method, replace its calls with `Arr::get()`

### 1c. Update PHPStan config

- Remove the suppression: `'#Function (data_get|data_set|class_basename) not found#'`
- Remove the suppression: `'#Call to static method timeout\(\) on an unknown class Illuminate\\Support\\Facades\\Http#'`
  (This is in `HttpAction` — fix the underlying code to not reference `Http` facade)

### 1d. Fix `HttpAction` Laravel facade usage

- `src/Actions/HttpAction.php` references `Illuminate\Support\Facades\Http`
- Replace with a simple `curl` wrapper or make HTTP client injectable
- This action should work without Laravel installed

**Files changed:** `src/Support/Arr.php` (new), `src/Core/Step.php`, `src/Core/WorkflowDefinition.php`, `src/Actions/HttpAction.php`, `phpstan.neon.dist`

**Tests:** `tests/Unit/Support/ArrTest.php` (new) — dot notation, nested arrays, missing keys, default values

---

## Phase 2: Deduplicate Condition Evaluation (High)

Two identical regex-based condition parsers exist. Fix DRY violation and the
silent-success bug at the same time.

### 2a. Create `Support\ConditionEvaluator`

**New file:** `src/Support/ConditionEvaluator.php`

```php
final class ConditionEvaluator
{
    public static function evaluate(string $condition, array $data): bool
    {
        if (!preg_match('/(\w+(?:\.\w+)*)\s*(===|!==|>=|<=|==|!=|>|<)\s*(.+)/', $condition, $matches)) {
            throw InvalidWorkflowDefinitionException::invalidCondition(
                $condition,
                'Condition must be in format: "key operator value" (e.g., "user.plan === premium")'
            );
        }

        $key = $matches[1];
        $operator = $matches[2];
        $value = trim($matches[3], '"\'');

        $dataValue = Arr::get($data, $key);

        return match ($operator) {
            '===' => $dataValue === $value,
            '!==' => $dataValue !== $value,
            '>='  => $dataValue >= $value,
            '<='  => $dataValue <= $value,
            '=='  => $dataValue == $value,
            '!='  => $dataValue != $value,
            '>'   => $dataValue > $value,
            '<'   => $dataValue < $value,
            default => false,
        };
    }
}
```

### 2b. Refactor callers

- `Step::evaluateCondition()` → delegate to `ConditionEvaluator::evaluate()`
- `WorkflowDefinition::evaluateCondition()` → delegate to `ConditionEvaluator::evaluate()`
- Remove `WorkflowDefinition::getNestedValue()` (already replaced by `Arr::get` in Phase 1)

### 2c. Decide on silent failure behavior

- `Step::evaluateCondition()` currently returns `true` on parse failure (line 244) — dangerous
- `WorkflowDefinition::evaluateCondition()` returns `false` on parse failure (line 346) — inconsistent
- **Decision:** Throw `InvalidWorkflowDefinitionException` on unparseable conditions. Fail loud.
- This is a breaking change for anyone relying on malformed conditions silently passing. Acceptable at v0.0.2-alpha.

**Files changed:** `src/Support/ConditionEvaluator.php` (new), `src/Core/Step.php`, `src/Core/WorkflowDefinition.php`

**Tests:** `tests/Unit/Support/ConditionEvaluatorTest.php` (new) — valid conditions, invalid format, dot notation, type coercion edge cases, all 8 operators

---

## Phase 3: Normalize Event Naming (High)

Inconsistent: `WorkflowStarted`, `WorkflowCancelled` vs `WorkflowCompletedEvent`, `StepCompletedEvent`.

### 3a. Rename events to consistent `*Event` suffix

| Current | New |
|---------|-----|
| `WorkflowStarted` | `WorkflowStartedEvent` |
| `WorkflowCancelled` | `WorkflowCancelledEvent` |
| `WorkflowCompletedEvent` | _(no change)_ |
| `WorkflowFailedEvent` | _(no change)_ |
| `StepCompletedEvent` | _(no change)_ |
| `StepFailedEvent` | _(no change)_ |

### 3b. Update all dispatchers

- `src/Core/WorkflowEngine.php:135` — `new WorkflowStarted(...)` → `new WorkflowStartedEvent(...)`
- `src/Core/WorkflowEngine.php:253` — `new WorkflowCancelled(...)` → `new WorkflowCancelledEvent(...)`

### 3c. Normalize constructor signatures

Currently `WorkflowStartedEvent` and `WorkflowCancelledEvent` take primitive strings,
while `WorkflowCompletedEvent` takes a `WorkflowInstance`. Standardize:

- All workflow-level events should accept `WorkflowInstance` + optional extra context
- This gives event listeners access to the full instance, not just the ID

```php
// Standardized pattern for all workflow events:
final readonly class WorkflowStartedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public array $initialContext = [],
    ) {}
}
```

**Files changed:** `src/Events/WorkflowStarted.php` (rename + refactor), `src/Events/WorkflowCancelled.php` (rename + refactor), `src/Core/WorkflowEngine.php`

**Tests:** Update any test referencing old class names

---

## Phase 4: Clean Up Duplicate API Methods (High)

`WorkflowEngine` has redundant method pairs that confuse consumers.

### 4a. Remove duplicates

- Remove `getWorkflow()` (line 265) — duplicate of `getInstance()`
- Remove `listWorkflows()` (line 294) — duplicate of `getInstances()`
- Move the `WorkflowState` enum-to-string conversion from `listWorkflows()` into `getInstances()`

### 4b. Keep `getStatus()` but simplify

`getStatus()` returns a formatted array — this is useful and distinct from `getInstance()`.
Keep it, but make it delegate to `getInstance()` internally (it already does via `getWorkflow()`).

**Files changed:** `src/Core/WorkflowEngine.php`

**Tests:** Update any test calling `getWorkflow()` or `listWorkflows()` to use `getInstance()` / `getInstances()`

---

## Phase 5: Implement Retry Logic (Critical)

`Step::getRetryAttempts()` returns a value but `Executor` never retries.

### 5a. Add retry loop in `Executor::executeStep()`

```php
private function executeStep(WorkflowInstance $instance, Step $step): void
{
    $maxAttempts = $step->getRetryAttempts() + 1; // +1 for initial attempt

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $this->doExecuteStep($instance, $step, $attempt);
            return; // Success — exit retry loop
        } catch (\Exception $e) {
            if ($attempt === $maxAttempts) {
                throw $e; // Final attempt failed — propagate
            }

            $this->logger->warning('Step failed, retrying', [
                'step_id' => $step->getId(),
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'error' => $e->getMessage(),
            ]);

            // Exponential backoff: 1s, 2s, 4s...
            $backoffMs = (int) (1000 * pow(2, $attempt - 1));
            usleep($backoffMs * 1000);
        }
    }
}
```

### 5b. Add `StepRetriedEvent`

**New file:** `src/Events/StepRetriedEvent.php`

```php
final readonly class StepRetriedEvent
{
    public function __construct(
        public WorkflowInstance $instance,
        public Step $step,
        public int $attempt,
        public int $maxAttempts,
        public \Throwable $lastError,
    ) {}
}
```

Dispatch from the retry loop's catch block.

**Files changed:** `src/Core/Executor.php`, `src/Events/StepRetriedEvent.php` (new)

**Tests:** `tests/Unit/ExecutorRetryTest.php` (new) — 0 retries (fail immediately), 1 retry (fail then succeed), max retries exhausted, backoff timing, event dispatch verification

---

## Phase 6: Implement Timeout Enforcement (Critical)

`Step` accepts timeout but `Executor` never enforces it.

### 6a. Add timeout wrapper in `Executor`

Use `pcntl_alarm` on Linux or a simpler approach with `set_time_limit` per step.
Since this is a library (not a web app), use a process-level approach:

```php
private function executeWithTimeout(callable $callback, int $timeoutSeconds): mixed
{
    if (!function_exists('pcntl_alarm')) {
        // Fallback: just execute without timeout (log warning)
        $this->logger->warning('pcntl extension not available, timeout not enforced');
        return $callback();
    }

    $previousHandler = pcntl_signal(SIGALRM, function () use ($timeoutSeconds) {
        throw StepExecutionException::timeout($timeoutSeconds);
    });

    pcntl_alarm($timeoutSeconds);

    try {
        $result = $callback();
        pcntl_alarm(0); // Cancel alarm
        return $result;
    } catch (\Exception $e) {
        pcntl_alarm(0); // Cancel alarm on error too
        throw $e;
    } finally {
        // Restore previous handler
        if ($previousHandler !== null) {
            pcntl_signal(SIGALRM, $previousHandler);
        }
    }
}
```

### 6b. Parse timeout string in Executor

The `Step::getTimeout()` returns a string like `"300"` or `"5m"`. Reuse the
`WorkflowBuilder::parseTimeoutString()` logic — extract it to a `Support\Timeout` helper.

### 6c. Wire into step execution

In `executeStep()`, wrap the action execution call:

```php
if ($step->getTimeout() !== null) {
    $seconds = Timeout::toSeconds($step->getTimeout());
    $this->executeWithTimeout(fn () => $this->executeAction($instance, $step), $seconds);
} else {
    $this->executeAction($instance, $step);
}
```

**Files changed:** `src/Core/Executor.php`, `src/Support/Timeout.php` (new)

**Tests:** `tests/Unit/Support/TimeoutTest.php` (parsing), `tests/Integration/TimeoutTest.php` (actual enforcement — skip if pcntl unavailable)

---

## Phase 7: Add SpyEventDispatcher for Tests (Medium)

No test currently verifies events are dispatched. This blocks testing of Phases 3, 5, 6.

### 7a. Create test spy

**New file:** `tests/Support/SpyEventDispatcher.php`

```php
class SpyEventDispatcher implements EventDispatcher
{
    public array $dispatched = [];

    public function dispatch(object $event): void
    {
        $this->dispatched[] = $event;
    }

    public function assertDispatched(string $eventClass, ?callable $callback = null): void
    {
        // Find matching events, optionally filtered by callback
    }

    public function assertNotDispatched(string $eventClass): void { ... }
    public function assertDispatchedCount(string $eventClass, int $count): void { ... }
}
```

### 7b. Add event dispatch tests

- Test `WorkflowStartedEvent` dispatched on `$engine->start()`
- Test `WorkflowCompletedEvent` dispatched when all steps finish
- Test `WorkflowCancelledEvent` dispatched on `$engine->cancel()`
- Test `StepCompletedEvent` dispatched after each step
- Test `StepRetriedEvent` dispatched on retries (Phase 5)

**Files changed:** `tests/Support/SpyEventDispatcher.php` (new), `tests/Integration/EventDispatchTest.php` (new)

---

## Phase 8: State Transition Enforcement (Medium)

`WorkflowState::canTransitionTo()` exists but `WorkflowInstance::setState()` doesn't call it.
Invalid transitions are silently accepted.

### 8a. Enforce transitions in `WorkflowInstance::setState()`

```php
public function setState(WorkflowState $state): void
{
    if (!$this->state->canTransitionTo($state)) {
        throw InvalidWorkflowStateException::fromInstanceTransition(
            $this->id, $this->state, $state
        );
    }

    $this->state = $state;
    $this->updatedAt = new \DateTime();
}
```

### 8b. Audit all `setState()` callers

Ensure every call site transitions legally:
- `Executor::processWorkflow()` — PENDING → RUNNING (valid)
- `Executor::processWorkflow()` — RUNNING → COMPLETED (valid)
- `StateManager::setError()` — any → FAILED (need to verify PENDING → FAILED is allowed, currently it's not — add it to `canTransitionTo()`)
- `WorkflowEngine::cancel()` — any → CANCELLED (verify all non-terminal → CANCELLED is allowed)

### 8c. Update state machine

Add missing transitions that the engine actually needs:
- `PENDING → FAILED` (engine can fail before running if definition is bad post-start)

**Files changed:** `src/Core/WorkflowInstance.php`, `src/Core/WorkflowState.php`

**Tests:** `tests/Unit/WorkflowStateTransitionTest.php` (new) — test every valid and invalid transition pair

---

## Execution Order

```
Phase 1 (Laravel coupling)     — no dependencies, do first
Phase 2 (Condition evaluator)  — depends on Phase 1 (uses Arr::get)
Phase 3 (Event naming)         — independent
Phase 4 (API cleanup)          — independent
Phase 7 (SpyEventDispatcher)   — independent, but needed by Phase 5 tests
Phase 5 (Retry logic)          — depends on Phase 7 for testing
Phase 6 (Timeout enforcement)  — depends on Phase 7 for testing
Phase 8 (State enforcement)    — independent, but test after Phases 5-6
```

Phases 1, 3, 4, 7 can be done in parallel.
Phases 2, 5, 6, 8 are sequential after their dependencies.

## New Files Summary

| File | Type |
|------|------|
| `src/Support/Arr.php` | Source |
| `src/Support/ConditionEvaluator.php` | Source |
| `src/Support/Timeout.php` | Source |
| `src/Events/WorkflowStartedEvent.php` | Source (rename) |
| `src/Events/WorkflowCancelledEvent.php` | Source (rename) |
| `src/Events/StepRetriedEvent.php` | Source |
| `tests/Support/SpyEventDispatcher.php` | Test support |
| `tests/Unit/Support/ArrTest.php` | Test |
| `tests/Unit/Support/ConditionEvaluatorTest.php` | Test |
| `tests/Unit/Support/TimeoutTest.php` | Test |
| `tests/Unit/ExecutorRetryTest.php` | Test |
| `tests/Unit/WorkflowStateTransitionTest.php` | Test |
| `tests/Integration/EventDispatchTest.php` | Test |
| `tests/Integration/TimeoutTest.php` | Test |

## Quality Gate

After all phases, the full CI pipeline must pass:
- `composer pint:test` — formatting
- `composer analyze` — PHPStan level 6 with fewer suppressions
- `composer test` — all existing + new tests green
