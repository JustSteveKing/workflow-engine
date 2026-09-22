# Console commands

The package ships a set of artisan commands for development and operations:

| Command | What it does |
|---|---|
| `workflow:list` | List the registered workflow definitions with their step counts and versions. |
| `workflow:show {id}` | Inspect one instance: status, cursor position in the step sequence, context, timestamps, and its signal log (buffered rows are flagged). This is the "where is it stuck and why" view. |
| `workflow:instances [--status= --workflow= --aggregate= --limit=]` | List instances with a filter, plus a per-status summary. |
| `workflow:start {workflow} {aggregateId} {aggregateType} [--context=JSON]` | Start an instance from the CLI. |
| `workflow:signal {id} {signal} [--data=JSON] [--by=]` | Deliver a signal by hand, to resume a flow locally or recover a missed webhook. |
| `workflow:advance {id}` | Advance one step synchronously, handy when you have no worker running locally. |
| `workflow:retry {id?} [--workflow= --failed-since= --force]` | Re-arm a failed instance, or a batch of them (`--workflow` / `--failed-since`). |
| `workflow:tick [--limit=]` | Re-dispatch advance jobs for sleeping instances past their wake time. |
| `workflow:recover [--minutes=15 --limit=100]` | Re-dispatch jobs for instances stranded mid-flight — pending, in progress or compensating — with nothing left to move them. |
| `workflow:cancel {id} [--reason= --by=]` | Stop an instance that will not finish on its own, rolling back any completed compensating steps. |
| `workflow:prune [--days=30 --status=completed,failed --force]` | Delete old terminal instances; their signals cascade. |

## The scheduler

**`workflow:tick` and `workflow:recover` are the two to wire into the scheduler.** Sleeping instances resume via a *delayed* queue job, and delayed jobs can be lost, whether from a queue restart or a flushed Redis. `tick` is the safety net: it finds sleepers whose `wake_at` has passed and re-queues their advance, so a lost timer self-heals. Run it every minute:

```php
// routes/console.php (Laravel 11+)
use Illuminate\Support\Facades\Schedule;

Schedule::command('workflow:tick')->everyMinute();
Schedule::command('workflow:recover')->everyFiveMinutes();
Schedule::command('workflow:prune')->daily();
```

`tick` covers instances whose delay was known in advance. `recover` covers the
other kind of stall: a job that was lost rather than delayed, leaving an
instance in pending, in progress or compensating with nothing scheduled to
move it. Both are safe to run repeatedly — the jobs take a row lock and return
immediately unless the instance is still in a state they can move.

A stranded instance is not only a stuck workflow. It is non-terminal, so it
still owns its aggregate, and whatever it is about is refused every further
change until someone notices.

`instances`, `tick`, `recover`, and `prune` query the bundled Eloquent tables directly; the rest go through the engine and registry and work with any repository.

## Related

- [Control flow](control-flow.md), the `sleep` outcome that `workflow:tick` backs up.
- [Custom persistence](custom-persistence.md), which commands work against a custom repository.
