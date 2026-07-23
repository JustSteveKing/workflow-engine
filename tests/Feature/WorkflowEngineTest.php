<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Jobs\TimeoutWorkflowStep;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWithTimeoutWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CountingAwaitTestStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CountingAwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\RetryingTestStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\RetryingWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\ThrowingWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\TwoStepAwaitTimeoutWorkflowDefinition;

it('starts and advances a simple workflow', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AutoWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AutoWorkflowDefinition::name(),
        aggregateId: 'member_001',
        aggregateType: 'member',
    );

    $engine->advance($instance->id);

    $persisted = WorkflowInstance::query()->findOrFail($instance->id);

    expect($persisted->step_index)->toBe(1)
        ->and($persisted->status)->toBe('in_progress')
        ->and($persisted->context)->toMatchArray(['ran_auto_step' => true]);

    Queue::assertPushed(AdvanceWorkflow::class);
});

it('awaits and resumes a workflow via signal', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AwaitWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_001',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);

    $awaiting = WorkflowInstance::query()->findOrFail($instance->id);

    expect($awaiting->status)->toBe('awaiting')
        ->and($awaiting->awaiting_signal)->toBe('payment_succeeded')
        ->and($awaiting->step_index)->toBe(0);

    $engine->signal(
        instanceId: $instance->id,
        signal: 'payment_succeeded',
        signalData: ['payment_id' => 'pay_001', 'paid_amount' => 2499],
        deliveredBy: 'mollie_webhook',
    );

    $signalled = WorkflowInstance::query()->findOrFail($instance->id);

    expect($signalled->status)->toBe('in_progress')
        ->and($signalled->step_index)->toBe(1)
        ->and($signalled->context)->toMatchArray(['payment_id' => 'pay_001', 'paid_amount' => 2499]);

    expect(WorkflowSignal::query()->count())->toBe(1);
});

it('fails an awaiting workflow when a timeout is delivered', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AwaitWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_002',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->timeout($instance->id, 0);

    $timedOut = WorkflowInstance::query()->findOrFail($instance->id);
    $timeoutSignal = WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->first();

    expect($timedOut->status)->toBe('failed')
        ->and($timedOut->awaiting_signal)->toBeNull()
        ->and($timedOut->failed_reason)->toContain("waiting for signal 'payment_succeeded'")
        ->and($timeoutSignal)->not->toBeNull()
        ->and($timeoutSignal?->signal)->toBe('timeout')
        ->and($timeoutSignal?->delivered_by)->toBe('system')
        ->and($timeoutSignal?->signal_data)->toMatchArray(['awaiting_signal' => 'payment_succeeded']);
});

it('ignores stale timeout delivery after the expected signal is already handled', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AwaitWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_003',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->signal($instance->id, 'payment_succeeded', ['payment_id' => 'pay_003']);
    $engine->timeout($instance->id, 0);

    expect(WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->count())->toBe(1)
        ->and(WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->firstOrFail()->signal)->toBe('payment_succeeded');
});

it('does not re-execute a step when advance is called while awaiting', function (): void {
    Queue::fake();

    CountingAwaitTestStep::resetExecutionCount();

    $registry = app(WorkflowRegistry::class);
    $registry->register(CountingAwaitWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: CountingAwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_004',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->advance($instance->id);

    $persisted = WorkflowInstance::query()->findOrFail($instance->id);

    expect(CountingAwaitTestStep::executionCount())->toBe(1)
        ->and($persisted->status)->toBe('awaiting')
        ->and($persisted->step_index)->toBe(0)
        ->and($persisted->awaiting_signal)->toBe('counting_signal');
});

it('schedules and executes timeout jobs for awaiting steps with configured timeouts', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AwaitWithTimeoutWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWithTimeoutWorkflowDefinition::name(),
        aggregateId: 'invoice_005',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);

    Queue::assertPushed(TimeoutWorkflowStep::class, fn(TimeoutWorkflowStep $job) => $job->workflowInstanceId === $instance->id
            && null !== $job->delay);

    $awaiting = WorkflowInstance::query()->findOrFail($instance->id);

    expect($awaiting->status)->toBe('awaiting')
        ->and($awaiting->awaiting_signal)->toBe('payment_confirmed');

    $timeoutJob = new TimeoutWorkflowStep($instance->id, 0);
    $timeoutJob->handle($engine);

    $timedOut = WorkflowInstance::query()->findOrFail($instance->id);
    $timeoutSignalCount = WorkflowSignal::query()
        ->where('workflow_instance_id', $instance->id)
        ->count();
    $timeoutSignal = WorkflowSignal::query()
        ->where('workflow_instance_id', $instance->id)
        ->where('signal', 'timeout')
        ->first();

    expect($timedOut->status)->toBe('failed')
        ->and($timedOut->awaiting_signal)->toBeNull()
        ->and($timedOut->failed_reason)->toContain("waiting for signal 'payment_confirmed'")
        ->and($timeoutSignalCount)->toBe(1)
        ->and($timeoutSignal)->not->toBeNull()
        ->and($timeoutSignal?->signal)->toBe('timeout')
        ->and($timeoutSignal?->signal_data)->toMatchArray(['awaiting_signal' => 'payment_confirmed'])
        ->and($timeoutSignal?->delivered_by)->toBe('system');
});

it('does nothing when advance is called on a completed workflow', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AutoWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AutoWorkflowDefinition::name(),
        aggregateId: 'member_002',
        aggregateType: 'member',
    );

    $engine->advance($instance->id); // execute step 0
    $engine->advance($instance->id); // transition to completed

    $completedBefore = WorkflowInstance::query()->findOrFail($instance->id);

    $engine->advance($instance->id); // stale duplicate

    $completedAfter = WorkflowInstance::query()->findOrFail($instance->id);

    expect($completedBefore->status)->toBe('completed')
        ->and($completedBefore->step_index)->toBe(1)
        ->and($completedAfter->status)->toBe('completed')
        ->and($completedAfter->step_index)->toBe(1)
        ->and($completedAfter->context)->toMatchArray(['ran_auto_step' => true]);

    Queue::assertPushed(AdvanceWorkflow::class, 1);
});

it('ignores a stale timeout scheduled for an earlier step after the workflow has advanced', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(TwoStepAwaitTimeoutWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: TwoStepAwaitTimeoutWorkflowDefinition::name(),
        aggregateId: 'invoice_007',
        aggregateType: 'invoice',
    );

    // Step 0 awaits 'payment_confirmed' with a timeout scheduled for step index 0.
    $engine->advance($instance->id);

    // The awaited signal arrives, moving the workflow to step 1 which awaits a different signal.
    $engine->signal($instance->id, 'payment_confirmed');
    $engine->advance($instance->id);

    $awaitingStepTwo = WorkflowInstance::query()->findOrFail($instance->id);
    expect($awaitingStepTwo->status)->toBe('awaiting')
        ->and($awaitingStepTwo->step_index)->toBe(1)
        ->and($awaitingStepTwo->awaiting_signal)->toBe('payment_succeeded');

    // The timeout job for step 0 finally fires; it must not fail the step-1 await.
    $engine->timeout($instance->id, 0);

    $afterStaleTimeout = WorkflowInstance::query()->findOrFail($instance->id);

    expect($afterStaleTimeout->status)->toBe('awaiting')
        ->and($afterStaleTimeout->step_index)->toBe(1)
        ->and($afterStaleTimeout->awaiting_signal)->toBe('payment_succeeded')
        ->and(WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->where('signal', 'timeout')->count())->toBe(0);
});

it('retries a failing step up to its max attempts before completing', function (): void {
    Queue::fake();

    RetryingTestStep::reset();

    $registry = app(WorkflowRegistry::class);
    $registry->register(RetryingWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: RetryingWorkflowDefinition::name(),
        aggregateId: 'invoice_008',
        aggregateType: 'invoice',
    );

    // Attempt 1 fails and is retried.
    $engine->advance($instance->id);

    $afterFirst = WorkflowInstance::query()->findOrFail($instance->id);
    expect($afterFirst->status)->toBe('in_progress')
        ->and($afterFirst->step_index)->toBe(0)
        ->and($afterFirst->attempts)->toBe(1);

    // Attempt 2 fails and is retried.
    $engine->advance($instance->id);

    $afterSecond = WorkflowInstance::query()->findOrFail($instance->id);
    expect($afterSecond->attempts)->toBe(2);

    // Attempt 3 succeeds and advances the workflow, resetting the attempt counter.
    $engine->advance($instance->id);
    $engine->advance($instance->id); // transition to completed

    $completed = WorkflowInstance::query()->findOrFail($instance->id);

    expect(RetryingTestStep::executionCount())->toBe(3)
        ->and($completed->status)->toBe('completed')
        ->and($completed->step_index)->toBe(1)
        ->and($completed->attempts)->toBe(0)
        ->and($completed->context)->toMatchArray(['retried_ok' => true]);
});

it('fails the workflow when a step throws instead of leaving it stuck in progress', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(ThrowingWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: ThrowingWorkflowDefinition::name(),
        aggregateId: 'invoice_009',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);

    $failed = WorkflowInstance::query()->findOrFail($instance->id);

    expect($failed->status)->toBe('failed')
        ->and($failed->step_index)->toBe(0)
        ->and($failed->failed_reason)->toBe('step blew up');
});

it('does nothing when advance is called on a failed workflow', function (): void {
    Queue::fake();

    $registry = app(WorkflowRegistry::class);
    $registry->register(AwaitWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_006',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->timeout($instance->id, 0);

    $failedBefore = WorkflowInstance::query()->findOrFail($instance->id);

    $engine->advance($instance->id); // stale duplicate

    $failedAfter = WorkflowInstance::query()->findOrFail($instance->id);

    expect($failedBefore->status)->toBe('failed')
        ->and($failedBefore->step_index)->toBe(0)
        ->and($failedAfter->status)->toBe('failed')
        ->and($failedAfter->step_index)->toBe(0)
        ->and($failedAfter->failed_reason)->toBe($failedBefore->failed_reason)
        ->and(WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->count())->toBe(1);

    Queue::assertPushed(AdvanceWorkflow::class, 0);
});
