# Failure, compensation, and resume

When a step fails past its retries, throws, or times out, one of two things happens. Either the workflow rolls back the work it has already done, or it fails outright. Both end terminal, and both are recoverable.

## What happens on failure

When a step fails past its retries, throws, or times out, one of two things happens.

If any already-completed step implements `CompensatingStep`, the instance moves to **compensating** and a `CompensateWorkflow` job runs each of those steps' `compensate()` methods in reverse order before marking the instance **failed**. That is saga-style rollback: you cannot roll back a distributed transaction, so you run the inverse operations instead. A `compensate()` that throws does not abort the rollback. The engine dispatches a `StepCompensationFailed` event for that step and carries on with the rest, so a refund that bounces is visible rather than silent.

Otherwise the instance goes straight to **failed**.

```php
final class ChargeStep implements WorkflowStep, CompensatingStep
{
    public function handle(WorkflowContext $context): StepResult { /* charge */ }
    public function compensate(WorkflowContext $context): void { /* refund */ }
    // ...
}
```

## Resuming a failed instance

A failed instance is terminal but recoverable. `retry()` re-arms it at the step that failed and queues an advance:

```php
$engine->retry($instance->id); // back to in_progress, failure cleared
```

## Compensation runs outside the lock

> Compensation callbacks run **outside** the database transaction and row lock. The engine captures the compensation plan under a short lock, runs your `compensate()` methods unlocked (so a slow external refund does not hold a DB connection or block other jobs on the instance), then records the terminal failure under a second short lock. `compensate()` should still be idempotent: an at-least-once queue can redeliver the compensate job, so guard external calls with an idempotency key.

## Related

- [The state machine](the-state-machine.md), the `compensating` and `failed` transitions.
- [Concurrency guarantees](concurrency.md), why compensation deliberately runs unlocked.
- [Timeouts and retries](timeouts-and-retries.md), the retries a step exhausts before it fails.
