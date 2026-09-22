<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Events\WorkflowCancelled;
use JustSteveKing\WorkflowEngine\Events\WorkflowCompensating;
use JustSteveKing\WorkflowEngine\Events\WorkflowFailed;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CancellableSagaWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SleepWorkflowDefinition;

function startFor(string $definition, string $aggregateId): array
{
    app(WorkflowRegistry::class)->register($definition);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: $definition::name(),
        aggregateId: $aggregateId,
        aggregateType: 'invoice',
    );

    return [$engine, $instance];
}

it('stops an instance awaiting a signal that will never arrive', function (): void {
    Queue::fake();

    [$engine, $instance] = startFor(AwaitWorkflowDefinition::class, 'invoice_cancel');
    $engine->advance($instance->id);

    $engine->cancel($instance->id, 'Supplier went under', 'ops');

    $cancelled = WorkflowInstance::query()->findOrFail($instance->id);

    expect($cancelled->status)->toBe('failed')
        ->and($cancelled->failed_reason)->toContain('Supplier went under')
        ->and($cancelled->awaiting_signal)->toBeNull();
});

it('records who cancelled it and why', function (): void {
    Queue::fake();

    [$engine, $instance] = startFor(AwaitWorkflowDefinition::class, 'invoice_cancel_log');
    $engine->advance($instance->id);

    $engine->cancel($instance->id, 'Duplicate', 'ops');

    $signal = WorkflowSignal::query()->where('signal', 'cancelled')->sole();

    expect($signal->delivered_by)->toBe('ops')
        ->and($signal->signal_data)->toMatchArray(['reason' => 'Duplicate'])
        ->and($signal->consumed_at)->not->toBeNull();
});

it('cancels a sleeping instance, which cannot fail directly', function (): void {
    Queue::fake();

    // Fail::from() does not include Sleeping; advance() is what normally wakes.
    [$engine, $instance] = startFor(SleepWorkflowDefinition::class, 'invoice_sleeping');
    $engine->advance($instance->id);

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('sleeping');

    $engine->cancel($instance->id, 'No longer needed');

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('failed');
});

it('announces the cancellation before the instance terminates', function (): void {
    Queue::fake();
    Event::fake([WorkflowCancelled::class, WorkflowFailed::class]);

    [$engine, $instance] = startFor(AwaitWorkflowDefinition::class, 'invoice_cancel_event');
    $engine->advance($instance->id);

    $engine->cancel($instance->id, 'Abandoned', 'ops');

    Event::assertDispatched(
        WorkflowCancelled::class,
        fn(WorkflowCancelled $event): bool => $event->instanceId === $instance->id
            && 'Abandoned' === $event->reason
            && 'ops' === $event->cancelledBy,
    );
    Event::assertDispatched(WorkflowFailed::class);
});

it('rolls back completed compensating steps rather than just stopping', function (): void {
    Queue::fake();
    // Faked before the engine is resolved: it takes the dispatcher by
    // constructor injection, so a later fake never reaches it.
    Event::fake([WorkflowCompensating::class]);

    [$engine, $instance] = startFor(CancellableSagaWorkflowDefinition::class, 'invoice_saga_cancel');
    $engine->advance($instance->id); // charge taken
    $engine->advance($instance->id); // parked on the payment signal

    $engine->cancel($instance->id, 'Order pulled');

    // Cancelling a half-finished process means undoing what it already did.
    Event::assertDispatched(WorkflowCompensating::class);
});

it('does nothing to an instance that already finished', function (): void {
    Queue::fake();

    [$engine, $instance] = startFor(AutoWorkflowDefinition::class, 'invoice_done');
    $engine->advance($instance->id);
    $engine->advance($instance->id);

    $before = WorkflowInstance::query()->findOrFail($instance->id)->status;

    $engine->cancel($instance->id, 'Too late');

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe($before)
        ->and(WorkflowSignal::query()->where('signal', 'cancelled')->count())->toBe(0);
});

it('throws for an instance that does not exist', function (): void {
    expect(fn() => app(WorkflowEngine::class)->cancel('nope', 'irrelevant'))
        ->toThrow(WorkflowNotFoundException::class);
});
