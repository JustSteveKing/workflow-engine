# 0003. Introduce a `StepOutcome` enum and align `StepResult` predicates

- Status: accepted
- Date: 2026-08-10

## Context

`WorkflowStatus` is a backed enum. `StepResult`, the sibling value object a step returns, carried its outcome as a public `readonly string` with the allowed values (`'complete'`, `'await'`, `'fail'`, `'goto'`, `'sleep'`) documented only in a line comment, and its predicates hard-coded those strings. One half of the domain was typed, the other was stringly typed, for no reason other than history.

The predicates had also drifted from `WorkflowInstance`. `StepResult` exposed `isComplete()` and `isSleep()`, while `WorkflowInstance` used `isCompleted()` and `isSleeping()` for the same concepts. `isAwaiting()` and `isFailed()` already matched across both.

## Decision

Add a `StepOutcome` backed enum (`Completed`, `Awaiting`, `Failed`, `Goto`, `Sleeping`) and type `StepResult::$outcome` as it. The factory methods keep their imperative names (`complete()`, `await()`, `fail()`, `goto()`, `sleep()`), which read well as intent. Align the predicates to the `WorkflowInstance` vocabulary:

- `isComplete()` becomes `isCompleted()`
- `isSleep()` becomes `isSleeping()`
- `isAwaiting()`, `isFailed()`, `isGoto()` are unchanged

`isGoto()` keeps its name because "goto" has no natural past or continuous form, and inventing one would read worse than the small inconsistency.

## Consequences

- `StepResult::$outcome` changes type from `string` to `StepOutcome`. Anyone reading `$result->outcome` as a string breaks. Internally only the predicates read it, so the blast radius inside the package is contained.
- Callers of `isComplete()` or `isSleep()` break and move to the new names.
- The domain now has a single, typed vocabulary for both instance status and step outcome.

## Alternatives considered

- **Leave `$outcome` as a string.** Less churn, but it keeps an untyped hole next to an enum that does the same job, and the comment listing valid values is exactly the sort of thing an enum exists to replace.
- **Align `WorkflowInstance` down to `isComplete`/`isSleep` instead.** Rejected: the instance predicates are more numerous and read as the canonical state words, so `StepResult` is the one that should move.
- **Rename the factory methods too** (`completed()`, `awaiting()`). Rejected: the factories read as commands the step issues, and the imperative form is correct for that.
