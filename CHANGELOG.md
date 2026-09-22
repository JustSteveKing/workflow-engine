# Changelog

All notable changes to `juststeveking/workflow-engine` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `WorkflowInstance::newFactory()` and `WorkflowSignal::newFactory()` declared `Factory<self>`, and `Factory`'s model type is invariant, so a subclass returning a factory for itself was not a compatible override. Now that the models are extendable, that sealed them again in practice: a host could extend the model but not give it a factory. Declared `Factory<covariant self>` at the use site.

### Added

- `workflow:recover`, a scheduled sweep for instances stranded mid-flight in pending, in-progress or compensating with no job left to move them — a dispatch lost rather than delayed. `workflow:tick` covers sleeping instances, whose delay is known in advance; nothing covered these, and because a non-terminal instance still owns its aggregate, one stranded instance refuses every further change to whatever it is about.
- `WorkflowEngine::routeOntoWorkflowQueue()` is now public, so a host dispatching the engine's jobs routes them onto the configured connection and queue rather than reimplementing it.
- `inProgress()`, `sleeping()` and `compensating()` states on `WorkflowInstanceFactory`.
- `Dispatchable` on `AdvanceWorkflow`, `CompensateWorkflow` and `TimeoutWorkflowStep`, so they can be dispatched without reaching for the bus.

### Changed

- `WorkflowInstance` and `WorkflowSignal` models are no longer `final`. An application needs somewhere to hang the relations connecting an instance to its own aggregate; without it, hosts declare a second model over the same table whose casts then drift.

### Fixed

- `docs/defining-steps.md` now lists `TimeoutRoutingStep` and `ContextualTimeout` alongside the other optional step interfaces. Both were documented in `timeouts-and-retries.md` but missing from the page describing what a step can do.

- `WorkflowEngine::cancel()` and `workflow:cancel`, for stopping an instance awaiting a signal that will never arrive without faking the signal and letting the workflow act on a decision nobody made. It terminates through the same path as any other failure, so completed compensating steps roll back rather than the instance simply stopping, and it wakes a sleeping instance first, which cannot transition to failed directly.
- `WorkflowCancelled`, dispatched before the instance terminates so a listener can tell a cancellation from the `WorkflowFailed` or `WorkflowCompensating` that follows.

- `signal()` takes an optional `$consumingStep`. Buffering means the method refuses nothing short of a terminal instance, so a caller naming the step expected to consume a signal gets that checked instead: the step must be in the instance's pinned sequence and still at or ahead of the cursor, and nothing of that name may already be buffered. Without it a signal aimed at the wrong workflow, or at a decision already taken, is held rather than rejected and the caller is told it landed. Untargeted delivery is unchanged.
- `WorkflowRepository::hasBufferedSignal()`, supporting the above. **Breaking for custom persistence implementations**, which must add the method.

- `ContextualTimeout`, an optional step interface whose `timeoutSecondsFor(WorkflowContext $context)` is asked for the instance in front of it. `timeoutSeconds()` takes no arguments, so it can express "an hour after this step parks" but not "three days after the date this particular order was promised" — the shape most real deadlines have. It is checked before `timeoutSeconds()`, which is unchanged for every step that does not implement it, and a deadline already past is clamped to zero rather than scheduled into the past.

### Added

- `TimeoutRoutingStep`, an optional step interface that turns a timeout into a deadline rather than a failure. A step implementing it names another step in the sequence via `timeoutTo()`, and on expiry the instance continues from there through the same jump `goto` uses instead of failing. Without it, a timeout fails the instance as before.
- `StepTimedOut` now carries `reroutedTo`, the step the instance continued from, or `null` when the timeout failed it. The parameter is optional, so existing listeners are unaffected.

### Fixed

- `timeoutTo()` is checked with `is_a()` before the step is resolved from the container, matching `hasCompensatableSteps()`. Resolving inside the timeout transaction meant a pinned step class removed by a later deploy threw out of the transaction and retried into the failed queue instead of failing the instance; it also kept a step's dependencies out of the row lock.
- `WorkflowInstance::jumpToStep()` now clears `awaitingSignal`. It was only ever reached from `goto`, where nothing was pending, so the stale value never surfaced; a routed timeout jumps from a step that is still parked and would otherwise have carried its signal forward.

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
