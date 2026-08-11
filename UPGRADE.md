# Upgrade guide

## Upgrading to the next release (from 0.2.0)

This release renames several public contracts and methods, and drops one dependency by
inlining it. The changes are mechanical, and most of them are a find and replace. Work
through the sections below in order.

### Requirements

- PHP `^8.3` (was `^8.5`).
- Laravel 12 or 13 (was 13 only).

Both are widenings, so an application already on PHP 8.5 and Laravel 13 keeps working. No
action is needed unless you want to move to a lower supported version.

### The `juststeveking/state-machine` dependency is gone

The state machine now ships inside this package under
`JustSteveKing\WorkflowEngine\StateMachine`. You do not need to require
`juststeveking/state-machine` for the workflow engine any more.

If your code referenced its classes directly, repoint the namespace:

| Before | After |
|---|---|
| `JustSteveKing\StateMachine\Exceptions\InvalidTransitionException` | `JustSteveKing\WorkflowEngine\StateMachine\Exceptions\InvalidTransitionException` |
| `JustSteveKing\StateMachine\Contracts\StateContract` | `JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract` |
| `JustSteveKing\StateMachine\Contracts\TransitionContract` | `JustSteveKing\WorkflowEngine\StateMachine\Contracts\TransitionContract` |

Most applications never touched these directly and have nothing to change here.

### Contract renames

The `Contract` suffix has been dropped from the core contracts, and one capability
interface has been renamed. Update your `implements` clauses and imports:

| Before | After |
|---|---|
| `Contracts\WorkflowStepContract` | `Contracts\WorkflowStep` |
| `Contracts\WorkflowDefinitionContract` | `Contracts\WorkflowDefinition` |
| `Contracts\WorkflowRepositoryContract` | `Contracts\WorkflowRepository` |
| `Contracts\HasRetryBackoff` | `Contracts\CustomRetryBackoff` |

`CompensatingStep` and `VersionedWorkflowDefinition` are unchanged.

A find and replace across your app handles this:

```bash
grep -rl WorkflowStepContract app/ | xargs sed -i '' 's/WorkflowStepContract/WorkflowStep/g'
grep -rl WorkflowDefinitionContract app/ | xargs sed -i '' 's/WorkflowDefinitionContract/WorkflowDefinition/g'
grep -rl WorkflowRepositoryContract app/ | xargs sed -i '' 's/WorkflowRepositoryContract/WorkflowRepository/g'
grep -rl HasRetryBackoff app/ | xargs sed -i '' 's/HasRetryBackoff/CustomRetryBackoff/g'
```

(Drop the `''` after `-i` on GNU `sed`.)

### The step method is now `handle()`

Every step's `execute()` method is now `handle()`:

```php
// Before
final class ChargeCard implements WorkflowStep
{
    public function execute(WorkflowContext $context): StepResult { /* ... */ }
}

// After
final class ChargeCard implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult { /* ... */ }
}
```

Rename the method on every class that implements `WorkflowStep`. The signature is
unchanged, so only the method name moves.

### `StepResult` outcome is now an enum

`StepResult::$outcome` is now a `JustSteveKing\WorkflowEngine\Domain\StepOutcome` enum
rather than a string. If you compared it against the literal strings `'complete'`,
`'await'`, `'fail'`, `'goto'`, or `'sleep'`, compare against the enum instead, or use the
predicates. The factory methods (`complete()`, `await()`, `fail()`, `goto()`, `sleep()`)
are unchanged.

Two predicates were renamed to match `WorkflowInstance`:

| Before | After |
|---|---|
| `$result->isComplete()` | `$result->isCompleted()` |
| `$result->isSleep()` | `$result->isSleeping()` |

`isAwaiting()`, `isFailed()`, and `isGoto()` are unchanged.

### `WorkflowInstance::reopen()` is now `retry()`

If you drove a domain `WorkflowInstance` directly (most code goes through
`WorkflowEngine::retry()` and does not touch this), rename the call:

```php
// Before
$instance->reopen();

// After
$instance->retry();
```
