<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Jobs\CompensateWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CompensatingChargeStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CompensatingTimeoutWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CompensationRecorder;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\OrderedSagaWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\ThrowThenCompleteStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\ThrowThenCompleteWorkflowDefinition;

function extraEngine(string ...$definitions): WorkflowEngine
{
    $registry = app(WorkflowRegistry::class);
    foreach ($definitions as $definition) {
        $registry->register($definition);
    }

    return app(WorkflowEngine::class);
}

it('retries a step that throws, then completes on the retry', function (): void {
    Queue::fake();
    ThrowThenCompleteStep::reset();
    $engine = extraEngine(ThrowThenCompleteWorkflowDefinition::class);

    $instance = $engine->start(ThrowThenCompleteWorkflowDefinition::name(), 'invoice_throw2', 'invoice');

    $engine->advance($instance->id); // throws -> retry queued
    $afterFirst = WorkflowInstance::query()->findOrFail($instance->id);
    expect($afterFirst->status)->toBe('in_progress')
        ->and($afterFirst->attempts)->toBe(1);

    $engine->advance($instance->id); // succeeds
    $engine->advance($instance->id); // complete

    $completed = WorkflowInstance::query()->findOrFail($instance->id);
    expect(ThrowThenCompleteStep::$executions)->toBe(2)
        ->and($completed->status)->toBe('completed')
        ->and($completed->context)->toMatchArray(['recovered' => true]);
});

it('compensates when an awaiting step times out with completed compensatable steps', function (): void {
    Queue::fake();
    CompensatingChargeStep::reset();
    $engine = extraEngine(CompensatingTimeoutWorkflowDefinition::class);

    $instance = $engine->start(CompensatingTimeoutWorkflowDefinition::name(), 'invoice_ctimeout', 'invoice');

    $engine->advance($instance->id); // charge completes
    $engine->advance($instance->id); // awaiting with timeout at step 1

    $engine->timeout($instance->id, 1);

    $compensating = WorkflowInstance::query()->findOrFail($instance->id);
    expect($compensating->status)->toBe('compensating');

    (new CompensateWorkflow($instance->id))->handle($engine);

    $failed = WorkflowInstance::query()->findOrFail($instance->id);
    expect(CompensatingChargeStep::$compensated)->toBe(1)
        ->and($failed->status)->toBe('failed')
        ->and($failed->failed_reason)->toContain("waiting for signal 'payment_confirmed'");
});

it('runs compensations in reverse order of completion', function (): void {
    Queue::fake();
    CompensationRecorder::reset();
    $engine = extraEngine(OrderedSagaWorkflowDefinition::class);

    $instance = $engine->start(OrderedSagaWorkflowDefinition::name(), 'invoice_order', 'invoice');

    $engine->advance($instance->id); // step one completes
    $engine->advance($instance->id); // step two completes
    $engine->advance($instance->id); // failing step -> compensating

    (new CompensateWorkflow($instance->id))->handle($engine);

    expect(CompensationRecorder::$order)->toBe(['two', 'one']);
});

it('consumes buffered signals in delivery order (FIFO)', function (): void {
    Queue::fake();
    $engine = extraEngine(AwaitWorkflowDefinition::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_fifo', 'invoice');

    // Two early deliveries of the same signal, buffered before the step parks.
    $engine->signal($instance->id, 'payment_succeeded', ['payment_id' => 'first']);
    $engine->signal($instance->id, 'payment_succeeded', ['payment_id' => 'second']);

    $engine->advance($instance->id); // consumes the earliest buffered signal

    $resumed = WorkflowInstance::query()->findOrFail($instance->id);
    expect($resumed->context)->toMatchArray(['payment_id' => 'first'])
        ->and(WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->whereNull('consumed_at')->count())->toBe(1);
});

it('discards buffered signals when an instance completes', function (): void {
    Queue::fake();
    $engine = extraEngine(AutoWorkflowDefinition::class);

    $instance = $engine->start(AutoWorkflowDefinition::name(), 'member_reapdone', 'member');
    $engine->signal($instance->id, 'never_awaited', ['x' => 1]);

    $buffered = WorkflowSignal::query()->where('workflow_instance_id', $instance->id)->firstOrFail();
    expect($buffered->consumed_at)->toBeNull();

    $engine->advance($instance->id); // step completes
    $engine->advance($instance->id); // workflow completes -> reap

    $done = WorkflowInstance::query()->findOrFail($instance->id);
    expect($done->status)->toBe('completed')
        ->and($buffered->refresh()->consumed_at)->not->toBeNull();
});
