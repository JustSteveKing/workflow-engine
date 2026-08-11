# Concepts

The whole package is seven nouns and one piece of bookkeeping. The [mental model](../README.md#the-mental-model) in the README is the napkin version; this page names the types behind it.

## Core concepts

| Concept | Type | Responsibility |
|---|---|---|
| **Definition** | `WorkflowDefinition` | A `name()` and an ordered `steps()`. Optionally `VersionedWorkflowDefinition`. |
| **Step** | `WorkflowStep` | `handle()`, `timeoutSeconds()`, `maxAttempts()`. Optionally `CompensatingStep`, `CustomRetryBackoff`. |
| **StepResult** | `Domain\StepResult` | `complete`, `await`, `goto`, `sleep`, or `fail`. |
| **WorkflowContext** | `Domain\WorkflowContext` | Immutable key/value data carried between steps. |
| **WorkflowEngine** | `Domain\WorkflowEngine` | `start`, `advance`, `signal`, `timeout`, `retry`, `compensate`. |
| **WorkflowRegistry** | `Domain\WorkflowRegistry` | Maps a workflow name to its definition class. |
| **WorkflowInstance** | `Domain\WorkflowInstance` | The state of one run, hydrated from the database. Its properties are public to read, but they change only through the instance's own transition methods. |

## The aggregate

Every instance is tied to an aggregate, meaning the domain entity the process is actually *about*, through `aggregateId` and `aggregateType`. Something like `'42'` and `'member'`.

This looks like bookkeeping until the first webhook lands. A payment provider tells you a charge succeeded for customer 42. It has no idea your workflow instance is `01J...`. The aggregate is what lets you ask "which workflows for this member are waiting on `payment_succeeded`?" without having stashed an instance ID somewhere first. See [delivering signals from webhooks](signals.md#delivering-signals-from-webhooks) for where that pays off.

## Related

- [The state machine](the-state-machine.md), how an instance moves between statuses.
- [Defining steps](defining-steps.md), the step and definition contracts in full.
