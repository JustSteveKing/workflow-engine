# AGENTS.md

Operating guide for AI agents working in **`juststeveking/workflow-engine`** — a
signal-driven, step-based workflow engine packaged for Laravel. Read this before
editing. Human-facing usage docs live in [README.md](README.md); this file is the
build/verify contract and the invariants you must not break.

## What this package is

An ordered list of **steps** (a *definition*) driven over time as a persisted
**instance**. A step completes (advance), awaits a named **signal** (pause),
sleeps for a delay, jumps to another step (goto), or fails (with bounded retries,
then compensation). Queued jobs move instances forward; signals resume them;
domain events report every transition. Two tables: `workflow_instances`,
`workflow_signals`.

## Setup

```bash
composer install
```

Requires PHP `^8.5`, the Laravel 13 `illuminate/*` components (bus, console,
contracts, database, queue, support — not the full `laravel/framework`), and
`juststeveking/state-machine`, plus a
database with transactions + row locking (tests use Testbench with in-memory
SQLite; no external services). It's a package, exercised through
`orchestra/testbench`.

## Verify — run all three before declaring done

```bash
composer test    # Pest suite: Unit (pure value objects) + Feature (engine/repo/commands + fixtures)
composer stan    # PHPStan/Larastan (max level per phpstan.neon; use --memory-limit=1G if run directly)
composer lint    # Pint (code style) — run last; it rewrites files
```

Two testsuites in `phpunit.xml`: `Unit` (tests/Unit, no DB, value objects) and `Feature`
(tests/Feature, RefreshDatabase). `composer test:coverage` needs pcov/xdebug.

Changing public method signatures, DB columns, state transitions, config keys, or
events **requires** running `composer test` — the feature tests encode intended
behaviour.

## Project layout

```
config/
  workflow-engine.php   queue connection/name, retry backoff, early-signal buffering
src/
  Contracts/            WorkflowDefinitionContract, WorkflowStepContract, WorkflowRepositoryContract,
                        CompensatingStep, HasRetryBackoff, VersionedWorkflowDefinition (last three optional)
  Domain/               WorkflowEngine (orchestrator), WorkflowInstance (in-memory state, encapsulated),
                        WorkflowContext (immutable data), StepResult, WorkflowRegistry, WorkflowStatus (enum + StateContract)
  StateMachine/         WorkflowStateMachine (StateMachineContract adapter), WorkflowStatusChanged (DomainEvent),
                        Transitions/ (Proceed, Await, Sleep, Complete, Compensate, Fail — the legal status table)
  Events/               WorkflowStarted, StepCompleted, StepFailed, SignalReceived, WorkflowAwaitingSignal,
                        StepTimedOut, WorkflowSlept, WorkflowCompleted, WorkflowCompensating, StepCompensationFailed,
                        WorkflowFailed, WorkflowRetried
  Models/               Eloquent WorkflowInstance, WorkflowSignal
  Repositories/         EloquentWorkflowRepository (default WorkflowRepositoryContract binding)
  Jobs/                 AdvanceWorkflow, TimeoutWorkflowStep, CompensateWorkflow
  Console/Commands/     WorkflowCommand (base, typed input helpers) + list/show/instances/start/
                        signal/advance/retry/tick/prune commands
  Exceptions/           WorkflowNotFoundException, InvalidSignalException
  WorkflowEngineServiceProvider.php
database/migrations/    workflow_instances, workflow_signals
examples/               illustrative copy-pasteable workflows (classmap-autoloaded, export-ignored)
tests/Feature/          WorkflowEngineTest, WorkflowFeaturesTest, WorkflowEventsTest, WorkflowSchemaTest, ...
tests/Fixtures/         step + definition test doubles
```

`src/Domain/WorkflowEngine.php` is the heart. Start there for any behaviour change.

## Code conventions

- `declare(strict_types=1);` in every PHP file.
- Classes `final`; value objects and the engine `readonly`.
- Constructor property promotion; named arguments at call sites; explicit return
  types including `: void`.
- Existing code uses Yoda comparisons (`null === $x`, `'complete' === $x`) — match it.
- `Domain\WorkflowInstance` uses PHP 8.4 **asymmetric visibility**
  (`public private(set)`): state is readable outside, mutable only via the
  instance's own transition methods. Do not add public setters or make mutation
  props writable.
- Production deps stay limited to `illuminate/*`. Let Pint format — run `composer lint`.

## Core invariants — do not regress these

1. **Statuses are a state machine.** `pending → in_progress → {awaiting, sleeping}
   → …`, terminal `completed`/`failed`; `compensating` precedes `failed` for sagas;
   `retry()` moves `failed → in_progress`. Every status change in
   `WorkflowInstance` goes through `transitionTo()`, which validates against
   `juststeveking/state-machine` and throws `InvalidTransitionException` on an
   illegal move. When you add a status or a new transition, update the matching
   `TransitionContract` in `src/StateMachine/Transitions` (and `transitionFor()`)
   or legal flows will throw. Enum `Domain\WorkflowStatus` is the `StateContract`;
   `isTerminal()` is authoritative.
2. **One step per `advance()`.** Run a single step, persist, queue the next. Never
   loop the whole workflow in one call.
3. **Everything mutating runs in `repository->transaction()` + `findById(lock: true)`.**
   `advance`, `signal`, `timeout`, `retry`, `compensate`. Any new mutating path must too.
4. **Deferred side effects.** Job dispatches AND event dispatches are collected as
   closures and run *after* the transaction commits (`runDeferred`). Never
   dispatch a job or fire an event inside the transaction body.
5. **Timeout step identity.** `TimeoutWorkflowStep` carries the `stepIndex` it
   guards; `timeout()` no-ops unless still `awaiting` that exact index.
6. **`maxAttempts()` = total attempts** (1 = no retry). Retries are delayed by
   `HasRetryBackoff` or the config exponential backoff. `attempts` is persisted,
   reset on advance/goto/await.
7. **Exceptions from `execute()` are failures** — caught and routed through the
   retry/compensation path. Keep the `try/catch`.
8. **Definition snapshot.** `start()` stores the ordered step list
   (`step_sequence`) and `definition_version` on the instance; the engine drives
   from the snapshot via `currentStepClass()`/`indexOfStep()`, never the live
   `definition->steps()`. Preserve this — it's how versioning works.
9. **Early-signal buffering.** A `signal()` to a non-awaiting, non-terminal
   instance is recorded with `consumed_at = null` (buffered); an `await` consumes
   the earliest matching buffered signal via `pullBufferedSignal()` (which returns
   a `BufferedSignal` carrying data + deliveredBy) before parking. Gated by
   `signals.buffer_early`. On reaching a terminal state the engine calls
   `discardBufferedSignals()` so leftovers don't accumulate.
10. **Compensation runs OUTSIDE the transaction.** `compensate()` captures the
    plan under a short lock, runs `CompensatingStep::compensate()` callbacks
    unlocked (never do external I/O inside `repository->transaction()`), then
    records the terminal failure under a second lock. A throwing `compensate()` is
    caught and reported via `StepCompensationFailed`, not swallowed; the run still
    ends `failed`. Callbacks must be idempotent (at-least-once redelivery).
11. **Validate before mutate.** `WorkflowInstance` transition methods call
    `transitionTo()` FIRST, then mutate other fields, so a rejected transition
    leaves the object unchanged. `markStepCompleted()` dedupes so goto loops don't
    grow `completed_steps`.
12. **Context is immutable.** Mutate only via `WorkflowInstance::mergeContext()` /
    `WorkflowContext::with()`.

## Common tasks

**Add a step (in a consuming app):** implement `WorkflowStepContract`. Return
`complete`/`await`/`goto`/`sleep`/`fail`. Optionally add `CompensatingStep` and/or
`HasRetryBackoff`. Steps resolve from the container (constructor DI works).

**Add a definition:** implement `WorkflowDefinitionContract` (`name()`, `steps()`);
optionally `VersionedWorkflowDefinition`. Register via `WorkflowRegistry::register()`.

**Add a `StepResult` outcome:** add the factory + `isX()` on `StepResult`, then a
branch in `WorkflowEngine::advance()`, then a fixture + feature test.

**Add an event:** add the class in `src/Events`, dispatch it via a deferred
closure at the transition, and cover it in `WorkflowEventsTest`.

**Touch the examples:** files in `examples/` are illustrative (side effects are
comments) and classmap-autoloaded via `autoload-dev`, so one file holds a
definition plus its steps. `ExamplesTest` instantiates each and asserts the
contracts, so keep step constructors parameterless. They are outside the PHPStan
paths on purpose (illustrative facade calls), but Pint does format them.

**Add a console command:** extend `Console\Commands\WorkflowCommand` (not
`Illuminate\Console\Command`) so you get the typed input helpers — `argument()`/
`option()` are `mixed` to PHPStan, so read them via `stringArgument()`,
`stringOption()`, `intOption()`, `jsonObjectOption()`, never raw with a cast.
Register it in the provider's `$this->commands([...])` block. Cover it with
`$this->artisan(...)` in `WorkflowCommandsTest`.

**Change the schema:** edit the migration, then thread the field through in order:
migration → `Models\WorkflowInstance` (`$fillable`/PHPDoc/`casts()`) →
`Domain\WorkflowInstance` (constructor, `create()`, `fromEloquent()`, transition
methods) → `EloquentWorkflowRepository` (`create()`/`save()`) →
`WorkflowSchemaTest`. Add a feature test.

## Gotchas

- Jobs are dispatched through the injected `Illuminate\Contracts\Bus\Dispatcher`
  (`$this->bus->dispatch(new Job(...))`), configured with the bus `Queueable`
  trait's `onConnection`/`onQueue`/`delay`. The package requires component-level
  `illuminate/*` packages, not `laravel/framework`, and uses no
  `Illuminate\Foundation\*` classes or the `config()`/`config_path()`/
  `database_path()` global helpers (all Foundation-only) — inject
  `Config\Repository` and use `$app->configPath()`/`databasePath()` instead.
- Arrow fns returning a `void` call trip PHPStan (`return.void`). The queue
  dispatch helpers return the bus dispatch result (`mixed`), so
  `fn () => $this->dispatchX()` stays valid; keep them non-`void`.
- Tests use `RefreshDatabase` (each test in a transaction). The engine's own
  `DB::transaction` nests as a savepoint — that's why side effects are deferred to
  after the closure returns (via `runDeferred`), not via `->afterCommit()`.
- `WorkflowInstance::fromEloquent()` uses the Eloquent `array`/`datetime` casts
  (`$model->context`, `$model->wake_at`, …) — do not reintroduce manual decoding.
- Config reads go through the injected `Config\Repository` behind typed helpers
  (`intConfig`, `is_string` guards) because `Repository::get()` returns `mixed`
  and PHPStan rejects casting `mixed` to `int`/`string`.
- Terminal-status and stale-job no-ops are intentional; a returning `advance` that
  "does nothing" is often correct. Check the "does nothing when advance is called
  on a completed/failed workflow" and stale-timeout tests before changing.
- `Event::fake(WORKFLOW_EVENTS)` in tests fakes only the package events so
  Eloquent model events still fire.
- `WorkflowCommand`'s input helpers coerce scalars with `is_scalar` + `(string)`,
  because `$this->artisan('cmd', ['id' => $model->id])` passes an **int** in tests
  (a plain `is_string` check would reject it and the command would see a blank
  argument). The `instances`/`tick`/`prune` commands query the Eloquent models
  directly and assume the bundled tables.

## Boundaries

- Don't commit, push, or open PRs unless explicitly asked.
- Don't add CI, tooling, or dependencies beyond what the task needs.
- Persistence stays behind `WorkflowRepositoryContract`; the engine never touches
  Eloquent or the DB directly.
