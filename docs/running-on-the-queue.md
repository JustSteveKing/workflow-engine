# Running on the queue

The engine moves instances forward with queued jobs. In production you run a worker; in tests you call the engine directly.

`advance`, `timeout`, and `compensate` are normally invoked by the bundled queued jobs, `AdvanceWorkflow`, `TimeoutWorkflowStep`, and `CompensateWorkflow`, which the engine dispatches as instances progress. So you need a worker:

```bash
php artisan queue:work
```

Queue dispatches are deferred until the surrounding database transaction commits, which means a follow-on job can never observe state the engine has not persisted yet. In a synchronous context you can still call the engine methods directly, which is exactly what the test suite does.

It is worth giving the engine its own queue connection in production. Workflow advance jobs are small and frequent, and you do not want them stuck behind a slow image resize. Set the connection and queue name in [configuration](configuration.md).

## Related

- [Concurrency guarantees](concurrency.md), what deferred dispatch protects you from.
- [Console commands](console-commands.md), the `workflow:tick` safety net for lost delayed jobs.
- [Configuration](configuration.md), the `queue` config keys.
