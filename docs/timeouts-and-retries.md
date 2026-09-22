# Timeouts and retries

Two bounds keep a step from running forever: a timeout on how long it waits for a signal, and a maximum number of attempts before it fails.

## Timeouts

An awaiting step can bound its wait by returning a non-null `timeoutSeconds()`. The engine schedules a delayed `TimeoutWorkflowStep` job tagged with the step it guards. If the signal has not arrived by then, the instance fails, or compensates if there is anything to compensate.

The tagging matters more than it sounds. Without it, a timeout scheduled an hour ago for a step that has long since completed would fire and kill whatever step the workflow is on now. Because the job knows which step it was guarding, a stale timeout is simply ignored.

Return `null` to wait indefinitely.

## Deadlines that are not failures

Failing is the right answer when the signal was supposed to arrive. It is the
wrong answer when the silence is the point — an invoice waiting on payment,
where fifteen days of nothing should send a reminder and keep waiting, not kill
the renewal.

A step implementing `TimeoutRoutingStep` names where to continue instead:

```php
final class AwaitPayment implements WorkflowStep, TimeoutRoutingStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::await('payment_received');
    }

    public function timeoutTo(): string
    {
        return SendFirstReminder::class;
    }

    public function timeoutSeconds(): ?int
    {
        return 15 * 24 * 60 * 60;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
```

On expiry the instance jumps to that step through the same mechanism `goto`
uses, so the step it lands on runs exactly as it would on any other entry. The
timeout is still written to the signal log, and `StepTimedOut` still fires —
with `reroutedTo` set to the step continued from, so a listener can tell a
deadline from a death.

The timed-out step is marked completed before the jump, so compensation still
covers it if the workflow later rolls back. The routed step must appear in the
instance's pinned sequence; if it does not, the instance fails with a reason
saying so rather than parking on a signal that is never coming.

Without this interface a timeout fails the instance, which remains the default.

## Retries and backoff

`maxAttempts()` is the total number of attempts for a step before it fails, not the number of retries on top of the first run:

- `1` means no retry. The first failure fails the step.
- `3` means up to three runs. Each failure short of the limit re-queues the step.

Retries are delayed by an exponential backoff derived from config, growing from `retry.backoff` and capped at `retry.max_backoff`, unless the step implements `CustomRetryBackoff::retryBackoff($attempt)` and decides for itself.

The attempt counter is persisted per instance and reset when the step advances, so every step gets a fresh budget rather than inheriting the scars of the one before it.

## Related

- [Configuration](configuration.md), the `retry` config keys.
- [Failure and compensation](failure-and-compensation.md), what happens once a step is out of attempts.
