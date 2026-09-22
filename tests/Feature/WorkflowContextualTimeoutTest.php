<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Jobs\TimeoutWorkflowStep;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWithTimeoutWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\ContextualTimeoutWorkflowDefinition;

function parkOn(array $context): void
{
    app(WorkflowRegistry::class)->register(ContextualTimeoutWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: ContextualTimeoutWorkflowDefinition::name(),
        aggregateId: 'invoice_ctx',
        aggregateType: 'invoice',
        initialContext: $context,
    );

    $engine->advance($instance->id);
}

it('schedules the timeout from a deadline held in the context', function (): void {
    Queue::fake();
    Carbon::setTestNow('2026-01-01 00:00:00');

    parkOn(['due_at' => '2026-01-04 00:00:00']);

    Queue::assertPushed(
        TimeoutWorkflowStep::class,
        // delay() is given an absolute moment, so a deadline three days out
        // lands exactly on the due date rather than on a rounded interval.
        fn(TimeoutWorkflowStep $job): bool => $job->delay->equalTo(Carbon::parse('2026-01-04 00:00:00')),
    );
});

it('gives each instance its own deadline', function (): void {
    Queue::fake();
    Carbon::setTestNow('2026-01-01 00:00:00');

    parkOn(['due_at' => '2026-01-02 00:00:00']);

    Queue::assertPushed(
        TimeoutWorkflowStep::class,
        fn(TimeoutWorkflowStep $job): bool => $job->delay->equalTo(Carbon::parse('2026-01-02 00:00:00')),
    );
});

it('clamps a deadline that has already passed to fire immediately', function (): void {
    Queue::fake();
    Carbon::setTestNow('2026-01-10 00:00:00');

    // An instance restored from backup, or a deadline shortened after parking.
    parkOn(['due_at' => '2026-01-01 00:00:00']);

    Queue::assertPushed(
        TimeoutWorkflowStep::class,
        // Clamped to now, not scheduled into the past.
        fn(TimeoutWorkflowStep $job): bool => $job->delay->equalTo(Carbon::parse('2026-01-10 00:00:00')),
    );
});

it('waits indefinitely when the context carries no deadline', function (): void {
    Queue::fake();

    parkOn([]);

    Queue::assertNotPushed(TimeoutWorkflowStep::class);

    expect(WorkflowInstance::query()->sole()->status)->toBe('awaiting');
});

it('still uses timeoutSeconds() for a step that does not implement the interface', function (): void {
    Queue::fake();
    Carbon::setTestNow('2026-01-01 00:00:00');

    app(WorkflowRegistry::class)->register(AwaitWithTimeoutWorkflowDefinition::class);

    $engine = app(WorkflowEngine::class);
    $instance = $engine->start(
        workflowName: AwaitWithTimeoutWorkflowDefinition::name(),
        aggregateId: 'invoice_plain',
        aggregateType: 'invoice',
    );

    $engine->advance($instance->id);

    Queue::assertPushed(
        TimeoutWorkflowStep::class,
        fn(TimeoutWorkflowStep $job): bool => $job->delay->equalTo(Carbon::parse('2026-01-01 00:01:00')),
    );
});
