# Changelog

All notable changes to `juststeveking/workflow-engine` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-08-11

This release contains breaking public API changes. See [UPGRADE.md](UPGRADE.md) for the
step-by-step migration.

### Added

- A `docs/` documentation set, one topic per page, split out of the README.
- Architecture decision records under `docs/adr/`.
- A `StepOutcome` enum backing `StepResult`, replacing the string outcome.
- A row-lock contention test and a PostgreSQL CI job that verify the concurrency guarantees against a driver with real row locking, rather than only SQLite.

### Changed

- **Breaking.** Dropped the `Contract` suffix from the core contracts: `WorkflowStepContract` is now `WorkflowStep`, `WorkflowDefinitionContract` is now `WorkflowDefinition`, `WorkflowRepositoryContract` is now `WorkflowRepository`.
- **Breaking.** Renamed the capability interface `HasRetryBackoff` to `CustomRetryBackoff`.
- **Breaking.** Renamed the step method `execute()` to `handle()` on `WorkflowStep`.
- **Breaking.** `StepResult::$outcome` is now a `StepOutcome` enum rather than a string, and the predicates `isComplete()` and `isSleep()` are now `isCompleted()` and `isSleeping()`.
- **Breaking.** Renamed `WorkflowInstance::reopen()` to `retry()`.
- Widened the requirements to PHP `^8.3` and Laravel 12 or 13 (previously PHP `^8.5` and Laravel 13 only).
- Inlined the state machine into `JustSteveKing\WorkflowEngine\StateMachine`, so illegal transitions now throw `JustSteveKing\WorkflowEngine\StateMachine\Exceptions\InvalidTransitionException`.
- Marked the inlined state machine under `src/StateMachine` as `@internal`. It is package implementation, not a public extension point.

### Removed

- The `juststeveking/state-machine` dependency, now inlined. Anyone referencing its classes directly must move to the `JustSteveKing\WorkflowEngine\StateMachine` namespace.

## [0.2.0] - 2026-07-24

### Changed

- Depend on the individual `illuminate/*` components (`bus`, `console`, `contracts`, `database`, `queue`, `support`) instead of the whole `laravel/framework`. The engine now dispatches jobs through `Illuminate\Contracts\Bus\Dispatcher` and reads configuration through `Illuminate\Contracts\Config\Repository`, and the package no longer uses any `Illuminate\Foundation\*` class or the `config()` / `config_path()` / `database_path()` global helpers. No behavioural change.

## [0.1.0] - 2026-07-24

### Added

- Signal-driven, step-based workflow engine: `start`, `advance`, `signal`, `timeout`, `retry`, `compensate`.
- Step outcomes via `StepResult`: `complete`, `await`, `goto` (branching), `sleep` (delays), `fail`.
- Per-step timeouts (tagged with the guarded step) and bounded retries with configurable exponential backoff (`HasRetryBackoff`).
- Saga compensation via `CompensatingStep`, run in reverse outside the database transaction, with a `StepCompensationFailed` event on failure.
- Early-signal buffering so a webhook that beats its step is consumed on park; buffered signals are discarded on terminal states.
- Definition versioning: the step sequence and version are snapshotted onto each instance at start.
- Status lifecycle enforced through [`juststeveking/state-machine`](https://github.com/juststeveking/state-machine); illegal transitions throw.
- Domain events for every transition (started, step completed/failed, awaiting, signal, timed out, slept, completed, compensating, compensation-failed, failed, retried).
- Lifecycle timestamps (`started_at`, `completed_at`, `failed_at`) and a `wake_at` for sleeping instances.
- Row-level locking and after-commit dispatch for at-least-once queue safety.
- Swappable persistence behind `WorkflowRepositoryContract`; bundled `EloquentWorkflowRepository`.
- Console commands: `workflow:list`, `workflow:show`, `workflow:instances`, `workflow:start`, `workflow:signal`, `workflow:advance`, `workflow:retry`, `workflow:tick`, `workflow:prune`.
- Publishable config (`workflow-engine`) for queue connection/name, retry backoff, and early-signal buffering.

[Unreleased]: https://github.com/juststeveking/workflow-engine/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/juststeveking/workflow-engine/compare/v0.2.0...v1.0.0
[0.2.0]: https://github.com/juststeveking/workflow-engine/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/juststeveking/workflow-engine/releases/tag/v0.1.0
