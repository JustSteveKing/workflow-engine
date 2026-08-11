<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Events\StepCompensationFailed;
use JustSteveKing\WorkflowEngine\Events\WorkflowFailed;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Jobs\CompensateWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\BackoffWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CompensatingChargeStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\FailThenCompleteStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\FailThenCompleteWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\GotoSkippedStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\GotoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\LoopBackStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\LoopStartStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\LoopWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\MutableWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SagaWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SecondAutoStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SleepWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\ThrowingWorkflowDefinition;

function engine(): WorkflowEngine
{
    return app(WorkflowEngine::class);
}

function register(string $definition): void
{
    app(WorkflowRegistry::class)->register($definition);
}

it('buffers a signal delivered before its step is awaiting and consumes it on park', function (): void {
    Queue::fake();
    register(AwaitWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_100',
        aggregateType: 'invoice',
    );

    // Signal arrives before the step has parked.
    engine()->signal($instance->id, 'payment_succeeded', ['payment_id' => 'pay_early'], 'stripe_webhook');

    $buffered = WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->firstOrFail();
    $pending = WorkflowInstance::query()->findOrFail($instance->id);

    expect($buffered->consumed_at)->toBeNull()
        ->and($pending->status)->toBe('pending')
        ->and($pending->step_index)->toBe(0);

    // The step now runs, parks on the signal, and immediately consumes the buffered one.
    engine()->advance($instance->id);

    $resumed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($resumed->status)->toBe('in_progress')
        ->and($resumed->step_index)->toBe(1)
        ->and($resumed->context)->toMatchArray(['payment_id' => 'pay_early'])
        ->and($buffered->refresh()->consumed_at)->not->toBeNull();
});

it('runs an instance against the step sequence snapshotted at start, not the live definition', function (): void {
    Queue::fake();
    MutableWorkflowDefinition::reset();
    SecondAutoStep::reset();
    MutableWorkflowDefinition::$version = 2;

    register(MutableWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: MutableWorkflowDefinition::name(),
        aggregateId: 'agg_ver',
        aggregateType: 'thing',
    );

    // The definition gains a second step *after* the instance started.
    MutableWorkflowDefinition::$includeSecondStep = true;

    engine()->advance($instance->id); // run the one snapshotted step
    engine()->advance($instance->id); // cursor past end -> complete

    $persisted = WorkflowInstance::query()->findOrFail($instance->id);

    expect(SecondAutoStep::$executed)->toBe(0)
        ->and($persisted->status)->toBe('completed')
        ->and($persisted->definition_version)->toBe(2)
        ->and($persisted->step_sequence)->toBe([\JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoCompleteTestStep::class]);
});

it('delays a retry using the step backoff', function (): void {
    Queue::fake();
    register(BackoffWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: BackoffWorkflowDefinition::name(),
        aggregateId: 'invoice_backoff',
        aggregateType: 'invoice',
    );

    engine()->advance($instance->id);

    $persisted = WorkflowInstance::query()->findOrFail($instance->id);

    expect($persisted->attempts)->toBe(1)
        ->and($persisted->status)->toBe('in_progress');

    Queue::assertPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $job): bool => $job->workflowInstanceId === $instance->id
        && null !== $job->delay);
});

it('dispatches jobs onto the configured queue', function (): void {
    Queue::fake();
    config()->set('workflow-engine.queue.name', 'workflows');
    register(AutoWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: AutoWorkflowDefinition::name(),
        aggregateId: 'member_q',
        aggregateType: 'member',
    );

    engine()->advance($instance->id);

    Queue::assertPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $job): bool => 'workflows' === $job->queue);
});

it('resumes a failed workflow when retried', function (): void {
    Queue::fake();
    FailThenCompleteStep::reset();
    register(FailThenCompleteWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: FailThenCompleteWorkflowDefinition::name(),
        aggregateId: 'invoice_resume',
        aggregateType: 'invoice',
    );

    engine()->advance($instance->id);

    $failed = WorkflowInstance::query()->findOrFail($instance->id);
    expect($failed->status)->toBe('failed')
        ->and($failed->failed_reason)->toBe('first attempt fails')
        ->and($failed->failed_at)->not->toBeNull();

    engine()->retry($instance->id);

    $retried = WorkflowInstance::query()->findOrFail($instance->id);
    expect($retried->status)->toBe('in_progress')
        ->and($retried->failed_reason)->toBeNull()
        ->and($retried->failed_at)->toBeNull();

    engine()->advance($instance->id); // step succeeds this time
    engine()->advance($instance->id); // complete

    $completed = WorkflowInstance::query()->findOrFail($instance->id);

    expect(FailThenCompleteStep::$executions)->toBe(2)
        ->and($completed->status)->toBe('completed')
        ->and($completed->context)->toMatchArray(['recovered' => true]);
});

it('cannot retry a workflow that has not failed', function (): void {
    Queue::fake();
    register(AwaitWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_noretry',
        aggregateType: 'invoice',
    );

    engine()->advance($instance->id); // now awaiting

    expect(fn() => engine()->retry($instance->id))->toThrow(RuntimeException::class);
});

it('compensates completed steps in reverse when a later step fails', function (): void {
    Queue::fake();
    CompensatingChargeStep::reset();
    register(SagaWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: SagaWorkflowDefinition::name(),
        aggregateId: 'invoice_saga',
        aggregateType: 'invoice',
    );

    engine()->advance($instance->id); // charge step completes
    engine()->advance($instance->id); // failing step -> begin compensation

    $compensating = WorkflowInstance::query()->findOrFail($instance->id);
    expect($compensating->status)->toBe('compensating')
        ->and(CompensatingChargeStep::$compensated)->toBe(0);

    Queue::assertPushed(CompensateWorkflow::class, fn(CompensateWorkflow $job): bool => $job->workflowInstanceId === $instance->id);

    // Run the compensation job.
    (new CompensateWorkflow($instance->id))->handle(engine());

    $failed = WorkflowInstance::query()->findOrFail($instance->id);

    expect(CompensatingChargeStep::$compensated)->toBe(1)
        ->and($failed->status)->toBe('failed')
        ->and($failed->failed_reason)->toBe('downstream failure');
});

it('routes to another step via goto, skipping the steps in between', function (): void {
    Queue::fake();
    GotoSkippedStep::reset();
    register(GotoWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: GotoWorkflowDefinition::name(),
        aggregateId: 'agg_goto',
        aggregateType: 'thing',
    );

    engine()->advance($instance->id); // router -> goto target (index 2)

    $afterGoto = WorkflowInstance::query()->findOrFail($instance->id);
    expect($afterGoto->step_index)->toBe(2)
        ->and($afterGoto->status)->toBe('in_progress');

    engine()->advance($instance->id); // target completes
    engine()->advance($instance->id); // complete

    $completed = WorkflowInstance::query()->findOrFail($instance->id);

    expect(GotoSkippedStep::$executed)->toBe(0)
        ->and($completed->status)->toBe('completed')
        ->and($completed->context)->toMatchArray(['routed' => true, 'reached_target' => true])
        ->and($completed->context)->not->toHaveKey('skipped_ran');
});

it('sleeps a step and resumes it only after the wake time', function (): void {
    Queue::fake();
    register(SleepWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: SleepWorkflowDefinition::name(),
        aggregateId: 'invoice_sleep',
        aggregateType: 'invoice',
    );

    engine()->advance($instance->id); // sleep step

    $sleeping = WorkflowInstance::query()->findOrFail($instance->id);
    expect($sleeping->status)->toBe('sleeping')
        ->and($sleeping->step_index)->toBe(1)
        ->and($sleeping->wake_at)->not->toBeNull()
        ->and($sleeping->context)->toMatchArray(['slept' => true]);

    Queue::assertPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $job): bool => $job->workflowInstanceId === $instance->id
        && null !== $job->delay);

    // A stray advance before the wake time does nothing.
    engine()->advance($instance->id);
    $stillSleeping = WorkflowInstance::query()->findOrFail($instance->id);
    expect($stillSleeping->status)->toBe('sleeping')
        ->and($stillSleeping->context)->not->toHaveKey('ran_auto_step');

    // After the wake time, the next step runs.
    $this->travel(61)->seconds();
    engine()->advance($instance->id); // wake + run AutoCompleteTestStep
    engine()->advance($instance->id); // complete

    $completed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($completed->status)->toBe('completed')
        ->and($completed->context)->toMatchArray(['ran_auto_step' => true]);
});

it('records lifecycle timestamps', function (): void {
    Queue::fake();
    register(AutoWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: AutoWorkflowDefinition::name(),
        aggregateId: 'member_ts',
        aggregateType: 'member',
    );

    $started = WorkflowInstance::query()->findOrFail($instance->id);
    expect($started->started_at)->not->toBeNull()
        ->and($started->completed_at)->toBeNull()
        ->and($started->failed_at)->toBeNull();

    engine()->advance($instance->id);
    engine()->advance($instance->id);

    $completed = WorkflowInstance::query()->findOrFail($instance->id);
    expect($completed->completed_at)->not->toBeNull();
});

it('records each completed step once even when a workflow loops via goto', function (): void {
    Queue::fake();
    LoopBackStep::reset();
    register(LoopWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: LoopWorkflowDefinition::name(),
        aggregateId: 'agg_loop',
        aggregateType: 'thing',
    );

    // start -> back (goto start) -> start again -> back -> complete
    engine()->advance($instance->id);
    engine()->advance($instance->id);
    engine()->advance($instance->id);
    engine()->advance($instance->id);
    engine()->advance($instance->id);

    $completed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($completed->status)->toBe('completed')
        ->and($completed->completed_steps)->toBe([LoopStartStep::class, LoopBackStep::class]);
});

it('discards buffered signals when an instance terminates', function (): void {
    Queue::fake();
    register(ThrowingWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: ThrowingWorkflowDefinition::name(),
        aggregateId: 'invoice_reap',
        aggregateType: 'invoice',
    );

    // Buffer a signal the instance will never consume.
    engine()->signal($instance->id, 'never_awaited', ['x' => 1], 'some_source');

    $buffered = WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->firstOrFail();
    expect($buffered->consumed_at)->toBeNull();

    // The step throws, the workflow fails, and the buffered signal is reaped.
    engine()->advance($instance->id);

    $failed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($failed->status)->toBe('failed')
        ->and($buffered->refresh()->consumed_at)->not->toBeNull();
});

it('ignores a stale compensate on an instance that is not compensating', function (): void {
    Queue::fake();
    CompensatingChargeStep::reset();
    register(SagaWorkflowDefinition::class);

    $instance = engine()->start(
        workflowName: SagaWorkflowDefinition::name(),
        aggregateId: 'invoice_stale_compensate',
        aggregateType: 'invoice',
    );

    engine()->advance($instance->id); // charge step completes; it is a compensatable, completed step

    $running = WorkflowInstance::query()->findOrFail($instance->id);
    expect($running->status)->toBe('in_progress'); // not compensating

    Event::fake();

    // A stale CompensateWorkflow job for an instance that is not compensating.
    engine()->compensate($instance->id);

    // The guard holds: nothing was compensated, the status is untouched, and no
    // terminal failure was recorded.
    expect(CompensatingChargeStep::$compensated)->toBe(0)
        ->and(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('in_progress');

    Event::assertNotDispatched(WorkflowFailed::class);
    Event::assertNotDispatched(StepCompensationFailed::class);
});
