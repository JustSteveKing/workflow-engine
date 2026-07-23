# Changelog

All notable changes to `juststeveking/workflow-engine` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/juststeveking/workflow-engine/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/juststeveking/workflow-engine/releases/tag/v0.1.0
