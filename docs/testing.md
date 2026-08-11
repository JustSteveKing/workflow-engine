# Testing

The package's own suite runs through Pest:

```bash
composer test           # Pest test suite (Unit + Feature)
composer test:coverage  # with a coverage report (needs pcov or xdebug)
composer stan           # PHPStan / Larastan static analysis
composer lint           # Pint code style
```

The suite is split into a `Unit` testsuite for the pure value objects (`StepResult`, `WorkflowContext`, `WorkflowRegistry`, `WorkflowStatus`) and a `Feature` testsuite for everything that touches the engine, repository, state machine, events, and console commands. It covers both the happy paths and the failure paths: unknown instances, unregistered workflows, invalid gotos, rejected signals, and compensation failures.

## Testing code that drives the engine

When you are testing application code that drives the engine, `Queue::fake()` lets you assert the jobs were dispatched and then drive the steps manually, and `Event::fake()` lets you assert on the domain events. The package's own feature tests are the reference for both patterns, and I would start there rather than from scratch.

## Related

- [Events](events.md), the events you assert on with `Event::fake()`.
- [Running on the queue](running-on-the-queue.md), the jobs you assert on with `Queue::fake()`.
