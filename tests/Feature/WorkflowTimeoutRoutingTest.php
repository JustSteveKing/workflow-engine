<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Events\StepTimedOut;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitPaymentWithReminderStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\BadTimeoutRouteWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SendReminderStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\TimeoutRoutingWorkflowDefinition;

function startTimeoutRoutingInstance(): array
{
    app(WorkflowRegistry::class)->register(TimeoutRoutingWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: TimeoutRoutingWorkflowDefinition::name(),
        aggregateId: 'invoice_timeout_routing',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);

    return [$engine, $instance];
}

it('continues from the routed step instead of failing when the wait expires', function (): void {
    Queue::fake();

    [$engine, $instance] = startTimeoutRoutingInstance();

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('awaiting');

    $engine->timeout($instance->id, 0);

    $routed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($routed->status)->toBe('in_progress')
        ->and($routed->step_index)->toBe(1)
        ->and($routed->awaiting_signal)->toBeNull()
        ->and($routed->failed_reason)->toBeNull();

    Queue::assertPushed(
        AdvanceWorkflow::class,
        fn(AdvanceWorkflow $job): bool => $job->workflowInstanceId === $instance->id,
    );
});

it('records the expiry in the signal log even though nothing failed', function (): void {
    Queue::fake();

    [$engine, $instance] = startTimeoutRoutingInstance();

    $engine->timeout($instance->id, 0);

    $signal = WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->sole();

    expect($signal->signal)->toBe('timeout')
        ->and($signal->delivered_by)->toBe('system')
        ->and($signal->signal_data)->toMatchArray(['awaiting_signal' => 'payment_received']);
});

it('reports the step it routed to on the timeout event', function (): void {
    Queue::fake();
    Event::fake([StepTimedOut::class]);

    [$engine, $instance] = startTimeoutRoutingInstance();

    $engine->timeout($instance->id, 0);

    Event::assertDispatched(
        StepTimedOut::class,
        fn(StepTimedOut $event): bool => $event->instanceId === $instance->id
            && 'payment_received' === $event->signal
            && 0 === $event->stepIndex
            && SendReminderStep::class === $event->reroutedTo,
    );
});

it('runs the routed step on the next advance', function (): void {
    [$engine, $instance] = startTimeoutRoutingInstance();

    $engine->timeout($instance->id, 0);
    $engine->advance($instance->id);

    $finished = WorkflowInstance::query()->findOrFail($instance->id);

    expect($finished->context)->toMatchArray(['reminder_sent' => true])
        ->and($finished->step_index)->toBe(2);
});

it('marks the timed-out step completed so compensation still covers it', function (): void {
    Queue::fake();

    [$engine, $instance] = startTimeoutRoutingInstance();

    $engine->timeout($instance->id, 0);

    expect(WorkflowInstance::query()->findOrFail($instance->id)->completed_steps)
        ->toBe([AwaitPaymentWithReminderStep::class]);
});

it('still fails a step whose timeout routes nowhere', function (): void {
    Queue::fake();

    app(WorkflowRegistry::class)->register(AwaitWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_plain_timeout',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->timeout($instance->id, 0);

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('failed');
});

it('fails rather than stalling when the routed step is not in the sequence', function (): void {
    Queue::fake();

    app(WorkflowRegistry::class)->register(BadTimeoutRouteWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: BadTimeoutRouteWorkflowDefinition::name(),
        aggregateId: 'invoice_bad_route',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->timeout($instance->id, 0);

    $failed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($failed->status)->toBe('failed')
        ->and($failed->failed_reason)->toContain('not part of the workflow');
});

it('ignores a stale timeout aimed at a step the instance has left', function (): void {
    Queue::fake();

    [$engine, $instance] = startTimeoutRoutingInstance();

    // The payment arrived; the timeout job for step 0 is still on the queue.
    $engine->signal($instance->id, 'payment_received', ['payment_id' => 'pay_1']);
    $engine->timeout($instance->id, 0);

    $unaffected = WorkflowInstance::query()->findOrFail($instance->id);

    expect($unaffected->status)->not->toBe('failed')
        ->and(WorkflowSignal::query()->where('signal', 'timeout')->count())->toBe(0);
});
