# Signals

A signal is a named external event that resumes an awaiting instance. A step parks on one with `await()`, and something outside, usually a webhook, delivers it with `signal()`.

## Awaiting signals

When a step returns `await('some_signal')`, the instance parks in **awaiting** until you call `signal()` with a matching name:

```php
$engine->signal(
    instanceId: $instance->id,
    signal: 'payment_succeeded',             // must match what the step is awaiting
    signalData: ['payment_id' => 'pay_123'], // merged into the context
    deliveredBy: 'stripe_webhook',           // free-form provenance label, logged
);
```

Every delivery is written to `workflow_signals`, matched or not. On a match, the data is merged into the context and the workflow advances.

## Early signal buffering

Here is a race you will hit eventually. Your step calls the payment provider and returns `await('payment_succeeded')`. The provider is fast. The webhook lands before the engine has finished persisting the parked state, the signal finds an instance that is not awaiting anything yet, and it is gone.

With `signals.buffer_early` on, which is the default, a signal delivered to an instance that is not yet awaiting it gets buffered instead of throwing. When a step later awaits that signal, the buffered one is consumed immediately and the workflow advances without ever parking. When an instance reaches a terminal state, any still-unconsumed buffered signals are discarded, so a mistyped or never-awaited signal name does not accumulate forever. It will not raise either, so watch the `SignalReceived` `buffered` flag if you want to catch delivery typos.

Turn it off and a mismatched `signal()` throws `InvalidSignalException` instead. That is the right choice if a stray signal genuinely means something has gone wrong upstream and you would rather find out loudly.

## Delivering signals from webhooks

This is where the aggregate pays off:

```php
public function handleStripeWebhook(
    Request $request,
    WorkflowEngine $engine,
    WorkflowRepository $repo,
): Response {
    $event = /* verify and parse */;

    $awaiting = $repo->findAwaitingSignal(
        signal: 'payment_succeeded',
        aggregateId: $event->metadata['member_id'],
        aggregateType: 'member',
    );

    foreach ($awaiting as $instance) {
        $engine->signal($instance->id, 'payment_succeeded', ['payment_id' => $event->id], 'stripe_webhook');
    }

    return response()->noContent();
}
```

No instance IDs stored on the payment provider's side, no correlation table. You ask which instances for this member are waiting on this signal, and you tell them.

With early signal buffering on, you can also deliver a `signal()` to an instance that has not parked yet and trust it to be consumed when the step gets there.

## Related

- [Concepts](concepts.md#the-aggregate), how the aggregate makes webhook delivery work.
- [Timeouts and retries](timeouts-and-retries.md), bounding how long a step waits for its signal.
