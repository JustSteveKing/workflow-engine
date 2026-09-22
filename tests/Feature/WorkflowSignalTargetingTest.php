<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Exceptions\InvalidSignalException;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitPaymentWithReminderStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SendReminderStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\TimeoutRoutingWorkflowDefinition;

/**
 * Naming the consuming step is how a caller keeps the refusal that buffering
 * removed: without it signal() accepts anything short of a terminal instance.
 */
function startTargetingInstance(): array
{
    app(WorkflowRegistry::class)->register(TimeoutRoutingWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: TimeoutRoutingWorkflowDefinition::name(),
        aggregateId: 'invoice_targeting',
        aggregateType: 'invoice',
    );

    return [$engine, $instance];
}

it('buffers a signal for a step the cursor has not reached', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();

    $engine->signal($instance->id, 'payment_received', [], null, AwaitPaymentWithReminderStep::class);

    expect(WorkflowSignal::query()->sole()->consumed_at)->toBeNull();
});

it('applies a signal to the step it is parked on', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();
    $engine->advance($instance->id);

    $engine->signal($instance->id, 'payment_received', ['paid' => true], null, AwaitPaymentWithReminderStep::class);

    expect(WorkflowSignal::query()->sole()->consumed_at)->not->toBeNull()
        ->and(WorkflowInstance::query()->findOrFail($instance->id)->context)->toMatchArray(['paid' => true]);
});

it('refuses a signal whose consuming step the cursor has passed', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();
    $engine->advance($instance->id);
    $engine->signal($instance->id, 'payment_received', [], null, AwaitPaymentWithReminderStep::class);

    // The decision has been taken; a second one cannot reach that step.
    expect(fn() => $engine->signal($instance->id, 'payment_received', [], null, AwaitPaymentWithReminderStep::class))
        ->toThrow(InvalidSignalException::class);
});

it('refuses a signal naming a step this workflow does not contain', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();

    expect(fn() => $engine->signal($instance->id, 'payment_received', [], null, 'App\\Some\\OtherWorkflowStep'))
        ->toThrow(InvalidSignalException::class);

    expect(WorkflowSignal::query()->count())->toBe(0);
});

it('refuses a second verdict while one is already buffered', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();

    $engine->signal($instance->id, 'payment_received', ['paid' => true], null, AwaitPaymentWithReminderStep::class);

    // Only the oldest would ever be consumed, so silently keeping the first
    // would apply the verdict this one supersedes.
    expect(fn() => $engine->signal($instance->id, 'payment_received', ['paid' => false], null, AwaitPaymentWithReminderStep::class))
        ->toThrow(InvalidSignalException::class);

    expect(WorkflowSignal::query()->count())->toBe(1);
});

it('leaves untargeted delivery exactly as it was', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();

    // No consuming step named: buffered, as before.
    $engine->signal($instance->id, 'anything_at_all');

    expect(WorkflowSignal::query()->sole()->consumed_at)->toBeNull();
});

it('accepts a later step as the consuming step', function (): void {
    Queue::fake();

    [$engine, $instance] = startTargetingInstance();

    $engine->signal($instance->id, 'reminder_ack', [], null, SendReminderStep::class);

    expect(WorkflowSignal::query()->sole()->signal)->toBe('reminder_ack');
});
