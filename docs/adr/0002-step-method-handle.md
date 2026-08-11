# 0002. Rename the step method `execute()` to `handle()`

- Status: accepted
- Date: 2026-08-10

## Context

`WorkflowStep` (formerly `WorkflowStepContract`) declared its single unit of work as `execute(WorkflowContext $context): StepResult`. Every other action-shaped class in my work uses `handle()`, and a Laravel developer's muscle memory reaches for `handle()` on jobs, actions, and anything the container invokes. A step declaring `execute()` is a visible seam against that.

There is a genuine argument the other way, which I weighed. The three queued jobs in this package (`AdvanceWorkflow`, `CompensateWorkflow`, `TimeoutWorkflowStep`) already define `handle()` as their `ShouldQueue` entry point. Steps are not jobs: a step runs synchronously inside the engine's `advance()` loop, under a row lock, and returns a `StepResult` the engine interprets rather than being dispatched to a queue. So `execute()` did carry a useful signal that a step is not a job.

## Decision

Rename the method to `handle()`.

```php
interface WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult;
    // ...
}
```

Consistency with the rest of the ecosystem wins here. A developer writing a step should not have to remember that this one action class is spelled differently from every other. The distinction between a step and a job is carried by the type it implements and the value it returns, not by the method name.

## Consequences

- `handle()` now names two things in this package: the queue entry point on the three jobs, and the unit of work on a step. They live on different classes and never collide technically, but a reader should know both meanings exist. This is the accepted cost of the rename.
- Every step implementation changes its method name: 27 step classes plus the four documented examples, and the single call site in `WorkflowEngine`. Breaking for anyone with their own steps.
- Taken now while the package is 0.x, recorded in `UPGRADE.md`, released under a version bump. A soft path was rejected (see below).

## Alternatives considered

- **Keep `execute()`.** My own first instinct, and defensible: steps are not jobs, and `handle()` carries a queue connotation a step does not have. Rejected because cross-package consistency matters more for a method every user implements, and the non-job nature of a step is already clear from its interface and return type.
- **Support both via a shim.** An interface cannot carry an optional method, so this would need a second interface plus an adapter that tries `handle()` and falls back to `execute()`. Too much machinery for a pre-1.0 package. Break cleanly instead.
