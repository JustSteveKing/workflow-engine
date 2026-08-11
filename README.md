# Workflow Engine

[![CI](https://github.com/juststeveking/workflow-engine/actions/workflows/ci.yml/badge.svg)](https://github.com/juststeveking/workflow-engine/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/juststeveking/workflow-engine.svg)](https://packagist.org/packages/juststeveking/workflow-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/juststeveking/workflow-engine.svg)](https://packagist.org/packages/juststeveking/workflow-engine)
[![License](https://img.shields.io/packagist/l/juststeveking/workflow-engine.svg)](LICENSE.md)

A signal-driven, step-based workflow engine for Laravel.

Most of the interesting logic in a Laravel application is not a single request. It is a process that unfolds over hours or days, and spends most of that time waiting on something it does not control. A payment provider. A human clicking a link in an email. A cron tick three days from now.

The usual approach is to bolt a few booleans onto a model and let the flow emerge from whatever code happens to fire next. It works for a week. Then the logic ends up smeared across a controller, two listeners, a webhook handler, and a scheduled command, and nobody can answer the only question support ever asks: where is this stuck, and why?

This package lets you model those processes as an ordered sequence of steps. Each step does one thing and then tells the engine what happens next: complete, pause and wait for a named signal, sleep for a while, jump somewhere else in the sequence, or fail. Instances are persisted as database rows, driven forward by queued jobs, and resumed by signals arriving from outside. There is one place that describes the process, and one durable row per run that can answer where it is.

The part I care most about is that the lifecycle is a declarative, enforced state machine rather than a diagram in a doc, so an instance cannot end up in an impossible state. That is the strongest guarantee here, and it has its own page: [the state machine](docs/the-state-machine.md).

## What it looks like

A workflow is an ordered list of steps. A step does one thing, then tells the engine what happens next:

```php
final class MemberRegistration implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'member_registration';
    }

    public function steps(): array
    {
        return [ChargeCard::class, ProvisionAccount::class];
    }
}

final class ChargeCard implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        // ...charge the card through your gateway...

        return StepResult::await('payment_succeeded'); // pause until the webhook lands
    }

    public function timeoutSeconds(): ?int { return 3600; } // ...or fail if it never does
    public function maxAttempts(): int { return 1; }
}
```

Start it, and let it wait. No worker is held open, no request is blocked. The instance is a database row parked on a signal:

```php
$instance = app(WorkflowEngine::class)->start('member_registration', (string) $member->id, 'member');

// ...hours later, the payment provider calls your webhook...
$engine->signal($instance->id, 'payment_succeeded', ['payment_id' => 'pay_123']);
```

There are four complete, copy-pasteable workflows in [examples/](examples) if you would rather read code than prose.

## The mental model

Seven nouns, and they fit on a napkin:

```
Definition  = a named, ordered list of Step classes
Step        = one unit of work that returns a StepResult
StepResult  = complete() | await('signal') | goto(Step) | sleep(seconds) | fail('reason')
Instance    = one live run of a Definition, persisted as a row
Context     = an immutable key/value bag passed step to step
Signal      = a named external event that resumes an awaiting instance
Engine      = advances instances, applies signals, times out, retries, compensates
```

The important detail is what the engine does **not** do. It never runs your whole workflow in one call. It runs exactly one step, persists the result, and queues a job for the next one if there is more to do. Awaiting and sleeping steps stop entirely until a signal arrives or the wake time passes. Nothing is held open, so a workflow that waits three days costs you a database row and nothing else.

## When to reach for it

Reach for it when there is waiting in the process, when you need to know exactly which step an instance is parked on and why it failed, or when you are about to stitch something together from listeners, scheduled jobs, and status columns.

Do not reach for it for a synchronous sequence with no waiting and no failure branching, for pure fan-out event handling (Laravel events are already good at that), or for a single background job that happens to have a few lines in it. A workflow engine earns its keep when the process is long-lived. Otherwise it is ceremony.

## Installation

```bash
composer require juststeveking/workflow-engine
```

The service provider is auto-discovered and the package loads its own migrations:

```bash
php artisan migrate
```

If you want to customise the schema or the config, publish them first:

```bash
php artisan vendor:publish --tag=workflow-engine-migrations
php artisan vendor:publish --tag=workflow-engine-config
php artisan migrate
```

Two tables get created. `workflow_instances` holds one row per run. `workflow_signals` is an append-only log of every signal delivered, including buffered ones and timeouts. Configuration lives in `config/workflow-engine.php`; the [configuration reference](docs/configuration.md) walks through every key.

## Quickstart

### 1. Define your steps and a workflow

```php
use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class ChargeMemberStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        $payment = Payments::charge($context->get('member_id'), $context->get('plan'));

        // Pause here until the provider confirms the charge via webhook.
        return StepResult::await('payment_succeeded', ['payment_ref' => $payment->ref]);
    }

    public function timeoutSeconds(): ?int { return 3600; } // fail if no signal within an hour
    public function maxAttempts(): int { return 1; }        // charging twice is worse than failing
}

final class ProvisionAccountStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        Accounts::provision($context->get('member_id'), $context->get('payment_id'));

        return StepResult::complete();
    }

    public function timeoutSeconds(): ?int { return null; }
    public function maxAttempts(): int { return 3; }
}

final class MemberRegistrationWorkflow implements WorkflowDefinition
{
    public static function name(): string { return 'member_registration'; }

    public function steps(): array
    {
        return [ChargeMemberStep::class, ProvisionAccountStep::class];
    }
}
```

Read the two `maxAttempts()` values back to back and you can see the design decision in each step. Charging a card is not safe to repeat, so it gets one attempt. Provisioning an account is idempotent on our side, so it gets three. That choice belongs next to the code that makes it, which is why it lives on the step rather than in a config file.

### 2. Register the workflow

Typically in a service provider's `boot()`:

```php
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;

$this->app->make(WorkflowRegistry::class)->register(MemberRegistrationWorkflow::class);
```

### 3. Start an instance and drive it

```php
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;

$engine = app(WorkflowEngine::class);

$instance = $engine->start(
    workflowName: 'member_registration',
    aggregateId: (string) $member->id,
    aggregateType: 'member',
    initialContext: ['member_id' => $member->id, 'plan' => 'annual'],
);

$engine->advance($instance->id); // in production the queued job does this for you
```

### 4. Resume it when the signal arrives

```php
$engine->signal(
    instanceId: $instance->id,
    signal: 'payment_succeeded',
    signalData: ['payment_id' => 'pay_123'],
    deliveredBy: 'stripe_webhook',
);
```

That is the entire loop. Everything else is detail, and it lives in the docs.

## Documentation

Full documentation is in [docs/](docs). Start here:

- [The state machine](docs/the-state-machine.md), the enforced lifecycle and why an instance cannot reach an impossible state
- [Concepts](docs/concepts.md), the seven nouns and the aggregate
- [Defining steps](docs/defining-steps.md), the step contract, `StepResult`, and the context
- [Control flow](docs/control-flow.md), branching with `goto` and delaying with `sleep`
- [Signals](docs/signals.md), awaiting, early buffering, and webhook delivery
- [Timeouts and retries](docs/timeouts-and-retries.md)
- [Failure and compensation](docs/failure-and-compensation.md), saga rollback and resume
- [Versioning](docs/versioning.md), deploying safely with in-flight instances
- [Events](docs/events.md)
- [Running on the queue](docs/running-on-the-queue.md)
- [Console commands](docs/console-commands.md)
- [Concurrency guarantees](docs/concurrency.md)
- [Custom persistence](docs/custom-persistence.md)
- [Configuration](docs/configuration.md)
- [Exceptions](docs/exceptions.md)
- [Testing](docs/testing.md)

Architecture decisions are recorded in [docs/adr/](docs/adr).

## Requirements

- PHP `^8.3`
- Laravel 12 or 13. The package depends on the individual `illuminate/*` components (bus, console, contracts, database, queue, support) rather than the whole `laravel/framework`
- A configured queue and a running worker for production use
- A database supporting transactions and row locking, which is any standard Laravel SQL driver

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
