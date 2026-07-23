# Examples

Four workflows, each built to show off one part of the engine. They are
illustrative: the calls to payment gateways, mailers, and the like are left as
comments so you can see the shape without needing those services to exist. Copy
one, fill in the real work, register it, and start it.

| Example | Shows off |
|---|---|
| [`MemberRegistration.php`](MemberRegistration.php) | Awaiting signals, per-step timeouts, context threaded across steps, and per-step `maxAttempts` decisions (charge once, provision three times). |
| [`OrderFulfilment.php`](OrderFulfilment.php) | Saga compensation with `CompensatingStep` (refund + release stock in reverse), plus a step-defined retry backoff with `HasRetryBackoff`. |
| [`ExpenseApproval.php`](ExpenseApproval.php) | Branching with `goto` — small expenses auto-approve, large ones wait for a human — and the ordering wrinkle that comes with it. |
| [`AbandonedCartReminder.php`](AbandonedCartReminder.php) | A time-spaced drip campaign built on `sleep()`, where a multi-day sequence costs one database row while it waits. |

## Wiring one up

Register the workflow, usually in a service provider's `boot()`:

```php
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Examples\MemberRegistrationWorkflow;

$this->app->make(WorkflowRegistry::class)->register(MemberRegistrationWorkflow::class);
```

Start an instance when the process begins:

```php
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;

app(WorkflowEngine::class)->start(
    workflowName: 'member_registration',
    aggregateId: (string) $member->id,
    aggregateType: 'member',
    initialContext: ['member_id' => $member->id, 'plan' => 'annual'],
);
```

Then make sure a queue worker is running (`php artisan queue:work`) so the
engine's jobs advance the instance, and deliver signals when the outside world
calls back — see [Delivering signals from webhooks](../README.md#delivering-signals-from-webhooks)
in the main README.

## Poking at a running instance

```bash
php artisan workflow:list                 # what is registered
php artisan workflow:show 42              # where instance 42 is parked, and why
php artisan workflow:signal 42 payment_succeeded --data='{"payment_id":"pay_1"}'
```
