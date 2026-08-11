# Documentation

Reference documentation for [`juststeveking/workflow-engine`](../README.md), a signal-driven, step-based workflow engine for Laravel. The [README](../README.md) covers what the package is, when to reach for it, installation, and a quickstart. These pages go deeper, one topic at a time.

## Start here

- [The state machine](the-state-machine.md). The enforced lifecycle, and why an instance cannot reach an impossible state. This is the strongest guarantee the package makes, so read it first.
- [Concurrency guarantees](concurrency.md). What row locking, stale-job guards, and deferred dispatch do, and what they do not do.

## Concepts

- [Concepts](concepts.md). The seven nouns, and the aggregate that ties an instance to a domain entity.

## Authoring workflows

- [Defining steps](defining-steps.md). The `WorkflowStep` contract, `StepResult` outcomes, and the workflow context.
- [Control flow](control-flow.md). Branching with `goto`, delaying with `sleep`.
- [Signals](signals.md). Awaiting a signal, early buffering, and delivering signals from webhooks.
- [Timeouts and retries](timeouts-and-retries.md). Bounding a wait, and bounded retries with backoff.
- [Failure and compensation](failure-and-compensation.md). Saga rollback, and resuming a failed instance.
- [Versioning](versioning.md). Deploying safely while instances are in flight.

## Operating

- [Events](events.md). The domain events every transition dispatches.
- [Running on the queue](running-on-the-queue.md). The jobs that drive instances forward.
- [Console commands](console-commands.md). The artisan commands for development and operations.
- [Custom persistence](custom-persistence.md). Swapping the default Eloquent repository.
- [Configuration](configuration.md). Every config key.
- [Exceptions](exceptions.md). What each exception means.
- [Testing](testing.md). How the package tests itself, and how to test code that drives it.

## Decisions

Architecture decisions are recorded in [adr/](adr).
