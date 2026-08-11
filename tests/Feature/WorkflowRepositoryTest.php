<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowRepository;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;

function repository(): WorkflowRepository
{
    return app(WorkflowRepository::class);
}

it('finds instances awaiting a signal, filtered by aggregate and type', function (): void {
    Queue::fake();
    app(WorkflowRegistry::class)->register(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $one = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_A', 'invoice');
    $two = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_B', 'invoice');
    $other = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_A', 'order');

    $engine->advance($one->id);   // awaiting payment_succeeded
    $engine->advance($two->id);   // awaiting payment_succeeded
    // $other is left pending (not advanced), so it should not match.

    $matches = repository()->findAwaitingSignal('payment_succeeded', 'invoice_A');
    expect($matches)->toHaveCount(1)
        ->and($matches[0]->id)->toBe($one->id);

    $byType = repository()->findAwaitingSignal('payment_succeeded', 'invoice_A', 'order');
    expect($byType)->toHaveCount(0);

    $allInvoiceB = repository()->findAwaitingSignal('payment_succeeded', 'invoice_B', 'invoice');
    expect($allInvoiceB)->toHaveCount(1)
        ->and($allInvoiceB[0]->id)->toBe($two->id);
});

it('returns null when pulling a buffered signal that does not exist', function (): void {
    Queue::fake();
    app(WorkflowRegistry::class)->register(AwaitWorkflowDefinition::class);
    $instance = app(WorkflowEngine::class)->start(AwaitWorkflowDefinition::name(), 'invoice_none', 'invoice');

    $pulled = repository()->transaction(fn() => repository()->pullBufferedSignal($instance->id, 'payment_succeeded'));

    expect($pulled)->toBeNull();
});

it('discards only unconsumed buffered signals', function (): void {
    Queue::fake();
    app(WorkflowRegistry::class)->register(AwaitWorkflowDefinition::class);
    $instance = app(WorkflowEngine::class)->start(AwaitWorkflowDefinition::name(), 'invoice_discard', 'invoice');

    $consumed = WorkflowSignal::query()->create([
        'workflow_instance_id' => $instance->id,
        'signal' => 'already',
        'signal_data' => [],
        'consumed_at' => now()->subDay(),
    ]);
    $buffered = WorkflowSignal::query()->create([
        'workflow_instance_id' => $instance->id,
        'signal' => 'pending',
        'signal_data' => [],
        'consumed_at' => null,
    ]);

    repository()->discardBufferedSignals($instance->id);

    expect($buffered->refresh()->consumed_at)->not->toBeNull()
        ->and($consumed->refresh()->consumed_at->toDateString())->toBe(now()->subDay()->toDateString());
});
