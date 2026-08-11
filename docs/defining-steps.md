# Defining steps

A step is one unit of work. It reads from the context, does its thing, and returns a `StepResult` telling the engine what happens next.

## The step contract

```php
interface WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult;
    public function timeoutSeconds(): ?int; // seconds to await a signal, or null
    public function maxAttempts(): int;     // total attempts before failing, 1 = no retry
}
```

Steps are resolved from the container, so constructor dependencies are injected as you would expect. Two optional interfaces add behaviour:

- `CustomRetryBackoff` gives you `retryBackoff(int $attempt): int` to override the configured backoff. See [timeouts and retries](timeouts-and-retries.md).
- `CompensatingStep` gives you `compensate(WorkflowContext $context): void` to undo the step during saga rollback. See [failure and compensation](failure-and-compensation.md).

Keep steps small, and keep them idempotent where you can. Under an at-least-once queue, a step can run more than once in rare failure windows. That is a property of the queue, not a bug in the engine, and no amount of locking removes it entirely.

## Step outcomes with StepResult

```php
StepResult::complete(['payment_id' => $id]);   // advance to the next step
StepResult::await('payment_succeeded', [...]); // pause for a named signal
StepResult::goto(RefundStep::class, [...]);    // jump to another step in the sequence
StepResult::sleep(3600, [...]);                // advance, but only after a delay
StepResult::fail('Card declined');             // fail the step, subject to maxAttempts
```

Each of them takes optional context updates to merge in.

If `handle()` throws instead of returning, the engine treats it as a failure using the exception message and applies exactly the same retry and compensation logic. An instance never gets stranded halfway through a step because someone forgot a try/catch.

## The workflow context

`WorkflowContext` is an immutable bag threaded through the workflow. You read from it inside a step and return updates through the `StepResult`. There is no setter, which is deliberate: if a step could mutate context in place, the persisted row and the in-memory object would drift apart the moment something threw.

```php
$context->get('plan');            // value or null
$context->get('plan', 'monthly'); // value or default
$context->has('payment_id');      // bool
$context->all();                  // array<string, mixed>
```

It accumulates the `initialContext`, every set of `contextUpdates`, and the data from every signal, and it is persisted as JSON. Keep it to serialisable values such as IDs, scalars, and small arrays rather than whole models. A model you serialised on Tuesday is a lie by Thursday.

## Related

- [Control flow](control-flow.md), the `goto` and `sleep` outcomes in detail.
- [Signals](signals.md), the `await` outcome and how signals resume a step.
