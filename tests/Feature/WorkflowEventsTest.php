<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Events\SignalReceived;
use JustSteveKing\WorkflowEngine\Events\StepCompensationFailed;
use JustSteveKing\WorkflowEngine\Events\StepCompleted;
use JustSteveKing\WorkflowEngine\Events\StepFailed;
use JustSteveKing\WorkflowEngine\Events\StepTimedOut;
use JustSteveKing\WorkflowEngine\Events\WorkflowAwaitingSignal;
use JustSteveKing\WorkflowEngine\Events\WorkflowCompensating;
use JustSteveKing\WorkflowEngine\Events\WorkflowCompleted;
use JustSteveKing\WorkflowEngine\Events\WorkflowFailed;
use JustSteveKing\WorkflowEngine\Events\WorkflowRetried;
use JustSteveKing\WorkflowEngine\Events\WorkflowStarted;
use JustSteveKing\WorkflowEngine\Jobs\CompensateWorkflow;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\CompensatingChargeStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\FailingCompensationWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\FailThenCompleteStep;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\FailThenCompleteWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\SagaWorkflowDefinition;

const WORKFLOW_EVENTS = [
    WorkflowStarted::class,
    StepCompleted::class,
    StepFailed::class,
    SignalReceived::class,
    WorkflowAwaitingSignal::class,
    StepTimedOut::class,
    WorkflowCompleted::class,
    WorkflowFailed::class,
    WorkflowCompensating::class,
    StepCompensationFailed::class,
    WorkflowRetried::class,
];

it('emits started, awaiting, signal, and step-completed events', function (): void {
    Queue::fake();
    Event::fake(WORKFLOW_EVENTS);

    app(WorkflowRegistry::class)->register(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_ev1',
        aggregateType: 'invoice',
    );

    Event::assertDispatched(WorkflowStarted::class, fn(WorkflowStarted $e): bool => $e->instanceId === $instance->id);

    $engine->advance($instance->id);
    Event::assertDispatched(WorkflowAwaitingSignal::class, fn(WorkflowAwaitingSignal $e): bool => 'payment_succeeded' === $e->signal);

    $engine->signal($instance->id, 'payment_succeeded', ['payment_id' => 'pay_ev']);
    Event::assertDispatched(SignalReceived::class, fn(SignalReceived $e): bool => 'payment_succeeded' === $e->signal && false === $e->buffered);
    Event::assertDispatched(StepCompleted::class);
});

it('emits a completed event when a workflow finishes', function (): void {
    Queue::fake();
    Event::fake(WORKFLOW_EVENTS);

    app(WorkflowRegistry::class)->register(AutoWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: AutoWorkflowDefinition::name(),
        aggregateId: 'member_ev',
        aggregateType: 'member',
    );

    $engine->advance($instance->id);
    $engine->advance($instance->id);

    Event::assertDispatched(WorkflowCompleted::class, fn(WorkflowCompleted $e): bool => $e->instanceId === $instance->id);
});

it('emits failed and retried events', function (): void {
    Queue::fake();
    Event::fake(WORKFLOW_EVENTS);
    FailThenCompleteStep::reset();

    app(WorkflowRegistry::class)->register(FailThenCompleteWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: FailThenCompleteWorkflowDefinition::name(),
        aggregateId: 'invoice_evfail',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    Event::assertDispatched(StepFailed::class, fn(StepFailed $e): bool => false === $e->willRetry);
    Event::assertDispatched(WorkflowFailed::class);

    $engine->retry($instance->id);
    Event::assertDispatched(WorkflowRetried::class, fn(WorkflowRetried $e): bool => $e->instanceId === $instance->id);
});

it('emits a compensating event when a saga rolls back', function (): void {
    Queue::fake();
    Event::fake(WORKFLOW_EVENTS);
    CompensatingChargeStep::reset();

    app(WorkflowRegistry::class)->register(SagaWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: SagaWorkflowDefinition::name(),
        aggregateId: 'invoice_evsaga',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);
    $engine->advance($instance->id);

    Event::assertDispatched(WorkflowCompensating::class, fn(WorkflowCompensating $e): bool => $e->instanceId === $instance->id);
});

it('emits a compensation-failed event and still fails when compensation throws', function (): void {
    Queue::fake();
    Event::fake(WORKFLOW_EVENTS);

    app(WorkflowRegistry::class)->register(FailingCompensationWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: FailingCompensationWorkflowDefinition::name(),
        aggregateId: 'invoice_compfail',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id); // step completes
    $engine->advance($instance->id); // later step fails -> compensating

    (new CompensateWorkflow($instance->id))->handle($engine);

    Event::assertDispatched(StepCompensationFailed::class, fn(StepCompensationFailed $e): bool => $e->instanceId === $instance->id
        && 'refund failed' === $e->reason);
    Event::assertDispatched(WorkflowFailed::class, fn(WorkflowFailed $e): bool => $e->instanceId === $instance->id);
});

it('threads the original deliverer through a buffered signal consume', function (): void {
    Queue::fake();
    Event::fake(WORKFLOW_EVENTS);

    app(WorkflowRegistry::class)->register(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: AwaitWorkflowDefinition::name(),
        aggregateId: 'invoice_evbuf',
        aggregateType: 'invoice',
    );

    // Buffered early, then consumed when the step parks.
    $engine->signal($instance->id, 'payment_succeeded', ['payment_id' => 'pay_buf'], 'stripe_webhook');
    $engine->advance($instance->id);

    Event::assertDispatched(SignalReceived::class, fn(SignalReceived $e): bool => false === $e->buffered
        && 'stripe_webhook' === $e->deliveredBy
        && 'pay_buf' === ($e->signalData['payment_id'] ?? null));
});
