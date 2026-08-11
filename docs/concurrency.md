# Concurrency guarantees

The engine assumes an at-least-once queue where duplicate and out-of-order jobs are normal rather than exceptional:

- **Row locking.** `advance`, `signal`, `timeout`, `retry`, and `compensate` each run inside a transaction and load the instance with `SELECT ... FOR UPDATE`. Two concurrent advance jobs cannot both run the same step.
- **Stale job guards.** Advance no-ops on terminal or awaiting instances, and on a sleeping instance before its wake time. Timeout no-ops unless the instance is still awaiting the exact step the job was scheduled for. Compensate no-ops unless the instance is compensating.
- **Deferred dispatch.** Follow-on jobs and events fire only after commit.

Read that as narrowing the window for double execution rather than closing it. Nothing here makes your steps idempotent for you. If a step has side effects, design it to tolerate a rare re-run.

## Related

- [The state machine](the-state-machine.md), the transitions these locks protect.
- [Custom persistence](custom-persistence.md), the transactional intent a repository must honour for any of this to hold.
- [Running on the queue](running-on-the-queue.md), the jobs this guards against.
