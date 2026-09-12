# Changelog

All notable changes to `workflow-engine-core` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v2.0.0 - 2026-09-12

Fixes several cases where the engine silently did the wrong thing and still
reported success — the failure mode a workflow engine can least afford.

**Why 2.0.0 and not 1.1.0:** `v1.0.0` was tagged as a stable release, so the
corrections below — which change public class names and runtime behaviour — are
breaking changes under semver and require a major bump. Every one is listed with
its migration. If you are on `v0.0.4-alpha` or `v1.0.0`, read the *Changed*
section before upgrading.

### Fixed

- **Compound conditions no longer evaluate to the wrong branch.** `ConditionEvaluator`
  had no `&&`/`||` support, but instead of rejecting them it swallowed the operator
  into the right-hand side and fell back to a string comparison — so
  `order.total > 1000 && order.vip === true` returned **true** for a 500 dollar
  order, with no exception and nothing in the logs. Boolean operators, parentheses
  and negated groups are now supported, and a malformed expression throws
  `InvalidWorkflowDefinitionException` instead of collapsing to a boolean.
- **`resume()` works on a failed workflow.** Recovering a failed instance — the
  documented recovery path — threw `Cannot transition workflow from 'failed' to
  'failed'`. Three defects chained: the executor never lifted `FAILED` back to
  `RUNNING`; the final `FAILED -> COMPLETED` hop was rejected; and the resulting
  exception was raised *inside* the failure handler, destroying the original cause.
  `FAILED -> RUNNING` is now a legal transition, resume retries the step that
  failed, and the failure handler can no longer mask the real error.
- **Multi-root workflows no longer drop a branch.** A definition with two
  independent entry points executed only one of them and still reported
  `COMPLETED` (with progress stuck below 100%). Every root now runs.
- **Unreachable steps are rejected at parse time.** A group of steps reachable
  only from each other could never execute, yet the workflow reported success.
- **Relational comparisons against a missing key are `false`.** `null` coerces to
  `0` in PHP, so `missing.key < 1000` was *true* and steps gated on data that was
  never set would run.
- **`start()` no longer overwrites an existing instance.** Reusing a workflow ID
  silently discarded the earlier run's state and history; it now throws
  `InvalidWorkflowStateException`.
- **Blocked workflows park in `WAITING`.** A workflow whose steps were all blocked
  on unmet prerequisites stayed in `RUNNING` forever, indistinguishable from one
  still executing.
- **`DelayAction` honours `minutes` and `hours`.** Both were documented but never
  read, so `delay(hours: 2)` silently paused for the one second default.
- **`HttpAction` reports a missing cURL extension** as a step failure instead of a
  fatal "undefined function" error.

### Changed

- **BREAKING: `EmailAction` is now `FakeEmailAction`.** It never sent email, but
  returned `'status' => 'sent'` — a workflow could show a delivered confirmation
  that did not exist. The payload is now explicitly `'sent' => false, 'mock' => true`,
  and it logs a warning. *Migration: implement `WorkflowAction` with your own mail
  transport for real delivery.*
- **BREAKING: `WorkflowBuilder::email()` is now `fakeEmail()`**, for the same
  reason. *Migration: rename the call, or switch to your own action.*
- **BREAKING: `ConditionAction` no longer accepts `on_true` / `on_false`.** They
  were read but never consumed by the engine, so the documented branching did not
  exist. *Migration: branch with a `condition` on the transition instead.*
- **BREAKING: `ConditionAction` uses the shared condition grammar.** It previously
  carried its own parser accepting `=`, `is` and `is not`, which no other part of
  the engine understood. *Migration: use `===`, `==`, `!=` etc.*
- **BREAKING: `FAILED` is no longer a terminal state** — it can transition to
  `RUNNING` (resume) or `CANCELLED`. Code asserting that failed workflows are
  immutable needs updating.
- `WorkflowState::isFinished()` still reports `true` for `FAILED`; use
  `canTransitionTo()` to test whether an instance can still move.

### Added

- `Storage\InMemoryStorage` now **ships with the package**. Previously the only
  implementation lived in `tests/` under `autoload-dev`, so the library could not
  run a workflow out of the box without first writing an adapter.
- `WorkflowDefinition::getFirstSteps()` returns every entry point.
- `Support\Arr::has()` distinguishes an absent key from one holding `null`.
- Boolean operators (`&&`, `||`), parentheses and negated groups in conditions.
- `SECURITY.md` with a disclosure process and scope notes.
- Regression tests for every issue above (116 -> 161 tests).

### CI

- `run-tests.yml` and `phpstan.yml` now run on **pull requests**, not just pushes.
  Combined with `dependabot-auto-merge.yml` auto-merging minor and patch bumps,
  dependency updates could previously reach `main` without the suite ever running
  against the merge result.
- Removed a stale PHPStan `ignoreErrors` pattern that no longer matched anything.

### Docs

- Documented the condition grammar, including the two rules that stop a mistyped
  condition from silently routing a workflow the wrong way.
- Corrected the built-in actions table: it documented a `body` key for
  `EmailAction` and `HttpAction` that neither read, `minutes`/`hours` for
  `DelayAction` that were ignored, and branching for `ConditionAction` that did
  not exist.
- Fixed the PHP attribute examples, whose inline comments claimed retries and
  timeouts that the attributes do not actually perform — the engine still does not
  read them (this is noted in the README).
- Archived the completed `PLAN.md` to `docs/PLAN-2025-refactor.md`.

## v1.0.0 - 2026-09-12

First stable release.

Promotes the package out of alpha so dependents can require a stable constraint instead of `dev-main`. No behavioural change from `v0.0.4-alpha`.

The only work since that tag was allowing current test tooling (#57): the dev requirements pinned Pest 2 / PHPUnit 10, which cannot install on PHP 8.4+, so the suite could not be run at all on a current PHP. 116 tests, PHPStan and Pint green.

## v0.0.4-alpha - 2026-05-04

### What's Changed

* Bump phpstan/phpstan from 2.1.39 to 2.1.40 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/29
* Bump laravel/pint from 1.27.1 to 1.29.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/31
* Bump phpstan/phpstan from 2.1.40 to 2.1.42 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/32
* Bump phpstan/phpstan from 2.1.42 to 2.1.44 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/34
* Bump dependabot/fetch-metadata from 2.5.0 to 3.0.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/33
* Bump ramsey/composer-install from 3 to 4 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/30
* Bump phpstan/phpstan from 2.1.44 to 2.1.46 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/35
* Refactor executor to iterative loop, add condition evaluation, improve validation by @lam0819 in https://github.com/solutionforest/workflow-engine-core/pull/36
* Bump phpstan/phpstan from 2.1.46 to 2.1.50 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/37
* fix: UTC DateTime + remove dead compensation code by @lam0819 in https://github.com/solutionforest/workflow-engine-core/pull/38
* Bump dependabot/fetch-metadata from 3.0.0 to 3.1.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/39
* Bump phpstan/phpstan from 2.1.50 to 2.1.54 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/42

**Full Changelog**: https://github.com/solutionforest/workflow-engine-core/compare/v0.0.3-alpha...v0.0.4-alpha

## v0.0.3-alpha - 2026-02-19

### What's Changed

* Bump stefanzweifel/git-auto-commit-action from 5 to 6 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/2
* Bump laravel/pint from 1.22.1 to 1.23.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/3
* Bump laravel/pint from 1.23.0 to 1.24.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/4
* Bump phpstan/phpstan from 2.1.17 to 2.1.18 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/5
* Bump phpstan/phpstan from 2.1.18 to 2.1.20 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/6
* Bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/7
* Bump phpstan/phpstan from 2.1.20 to 2.1.21 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/8
* Bump phpstan/phpstan from 2.1.21 to 2.1.22 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/9
* Bump actions/checkout from 4 to 5 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/10
* Bump phpstan/phpstan from 2.1.22 to 2.1.25 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/11
* Bump laravel/pint from 1.24.0 to 1.25.1 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/12
* Bump phpstan/phpstan from 2.1.25 to 2.1.28 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/13
* Bump phpstan/phpstan from 2.1.28 to 2.1.29 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/14
* Bump phpstan/phpstan from 2.1.29 to 2.1.30 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/15
* Bump phpstan/phpstan from 2.1.30 to 2.1.31 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/16
* Bump phpstan/phpstan from 2.1.31 to 2.1.32 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/19
* Bump laravel/pint from 1.25.1 to 1.26.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/20
* Bump stefanzweifel/git-auto-commit-action from 6 to 7 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/17
* Bump actions/checkout from 5 to 6 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/18
* Bump phpstan/phpstan from 2.1.32 to 2.1.33 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/21
* Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/23
* Bump phpstan/phpstan from 2.1.33 to 2.1.37 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/24
* Bump laravel/pint from 1.26.0 to 1.27.0 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/22
* Bump phpstan/phpstan from 2.1.37 to 2.1.38 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/25
* Bump phpstan/phpstan from 2.1.38 to 2.1.39 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/26
* Bump laravel/pint from 1.27.0 to 1.27.1 by @dependabot[bot] in https://github.com/solutionforest/workflow-engine-core/pull/27
* Add CLAUDE.md and AGENTS.md with codebase review and improvement roadmap by @lam0819 in https://github.com/solutionforest/workflow-engine-core/pull/28

### New Contributors

* @lam0819 made their first contribution in https://github.com/solutionforest/workflow-engine-core/pull/28

**Full Changelog**: https://github.com/solutionforest/workflow-engine-core/compare/v0.0.2-alpha...v0.0.3-alpha

## v0.0.2-alpha - 2025-05-29

**Full Changelog**: https://github.com/solutionforest/workflow-engine-core/compare/v0.0.1-alpha...v0.0.2-alpha

## v0.0.1-alpha - 2025-05-29

### What's Changed

* Bump phpstan/phpstan from 1.12.27 to 2.1.17 by @dependabot in https://github.com/solutionforest/workflow-engine-core/pull/1

### New Contributors

* @dependabot made their first contribution in https://github.com/solutionforest/workflow-engine-core/pull/1

**Full Changelog**: https://github.com/solutionforest/workflow-engine-core/commits/v0.0.1-alpha

## [Unreleased]

### Added

- Initial release of framework-agnostic workflow engine core
- Workflow definition and execution engine
- Support for custom actions with attributes (Retry, Timeout, Condition)
- Event system for workflow monitoring
- Storage adapter interface for persistence
- Logger interface for custom logging
- State management and workflow instance tracking
- Comprehensive test suite with Pest PHP
- PHPStan static analysis integration
- GitHub Actions CI/CD pipeline

### Features

- Framework-agnostic architecture
- PHP 8.3+ type safety and modern features
- Extensible plugin system
- Built-in retry mechanisms
- Timeout controls
- Conditional execution
- Rich event system
- Memory-efficient execution
- High-throughput optimization

## [1.0.0] - TBD

### Added

- Initial stable release
