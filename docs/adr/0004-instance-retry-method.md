# 0004. Rename `WorkflowInstance::reopen()` to `retry()`

- Status: accepted
- Date: 2026-08-10

## Context

Retrying a failed instance was described by three different words depending on where you looked. The engine's public method is `retry()`, the instance method it called was `reopen()`, and that method's own docblock said it "re-arms" the instance. Same user intent, three vocabularies.

`reopen()` is internal (its only caller is `WorkflowEngine::retry()`), so this is a low-stakes change, but consistent language across a small domain is worth having.

## Decision

Rename `WorkflowInstance::reopen()` to `retry()`, so the engine's `retry()` delegates to the instance's `retry()` and the whole path uses one word.

## Consequences

- `WorkflowInstance` is a domain object most users interact with through the engine rather than directly, so the practical break is small. It is still a public method, so it is recorded in `UPGRADE.md`.
- The engine method and the instance method now share the name `retry()`. They sit on different classes with a clear delegation relationship (the engine orchestrates the transaction and job dispatch, the instance mutates its own state), so the shared name reads as delegation rather than duplication.

## Alternatives considered

- **Rename the engine method to match `reopen()` instead.** Rejected: `retry()` is the word users already reach for, and it is the public verb on the engine, the CLI, and the docs.
- **Leave it alone.** Rejected: it is a small, self-contained fix, and doing it alongside the other naming work keeps the vocabulary uniform before 1.0.
