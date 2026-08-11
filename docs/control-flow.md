# Control flow

Steps do not have to run in a straight line. Two `StepResult` outcomes bend the sequence: `goto` jumps, and `sleep` delays.

## Branching with goto

Return `StepResult::goto(SomeStep::class)` to jump to another step in the workflow's sequence instead of advancing linearly. The target has to be one of the definition's steps, which keeps the graph closed and inspectable:

```php
public function handle(WorkflowContext $context): StepResult
{
    return $context->get('amount') > 10_000
        ? StepResult::goto(ManualReviewStep::class)
        : StepResult::complete();
}
```

## Delaying with sleep

Return `StepResult::sleep($seconds)` when you want to advance to the next step, but not yet. This is your "wait three days, then send a reminder":

```php
return StepResult::sleep(now()->diffInSeconds(now()->addDays(3)), ['reminded_at' => now()]);
```

The instance moves to **sleeping** and a delayed advance is queued. A stray advance that arrives before the wake time is ignored rather than skipping the delay.

Delayed jobs can be lost, so a sleeping instance is also recovered by the `workflow:tick` command. Wire it into the scheduler, as described in [console commands](console-commands.md#the-scheduler).

## Related

- [Defining steps](defining-steps.md), the full set of `StepResult` outcomes.
- [The state machine](the-state-machine.md), where `sleeping` sits in the lifecycle.
