# Custom persistence

The engine only ever talks to storage through `WorkflowRepository`, so swapping the default `EloquentWorkflowRepository` is a one-liner in a service provider:

```php
$this->app->bind(WorkflowRepository::class, MyRepository::class);
```

Your implementation has to honour the contract's transactional intent, and this is not optional. `transaction()` runs a callback atomically. `findById($id, lock: true)` takes a genuine row lock within it. `pullBufferedSignal()` atomically consumes a buffered signal, and `discardBufferedSignals()` clears the ones an instance can no longer consume. Every guarantee in [concurrency guarantees](concurrency.md) rests on those, so a repository that quietly no-ops the locking will look fine in development and lose you money in production.

Note that the `workflow:instances`, `workflow:tick`, and `workflow:prune` commands query the bundled Eloquent tables directly. The rest go through the engine and registry, so they work against any repository. See [console commands](console-commands.md).

## Related

- [Concurrency guarantees](concurrency.md), the guarantees that depend on the repository contract.
- [Configuration](configuration.md).
