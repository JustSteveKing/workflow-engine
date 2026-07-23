<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Exceptions\InvalidSignalException;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\BadGotoWorkflowDefinition;

function errorEngine(string ...$definitions): WorkflowEngine
{
    $registry = app(WorkflowRegistry::class);
    foreach ($definitions as $definition) {
        $registry->register($definition);
    }

    return app(WorkflowEngine::class);
}

it('throws when the target instance is unknown', function (string $method): void {
    Queue::fake();
    $engine = errorEngine();

    $call = match ($method) {
        'advance' => fn() => $engine->advance('missing'),
        'signal' => fn() => $engine->signal('missing', 'x'),
        'timeout' => fn() => $engine->timeout('missing', 0),
        'retry' => fn() => $engine->retry('missing'),
        'compensate' => fn() => $engine->compensate('missing'),
    };

    expect($call)->toThrow(WorkflowNotFoundException::class);
})->with(['advance', 'signal', 'timeout', 'retry', 'compensate']);

it('throws when starting an unregistered workflow', function (): void {
    $engine = errorEngine();

    expect(fn() => $engine->start('nope', 'agg', 'thing'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a signal for a non-awaiting instance when buffering is off', function (): void {
    Queue::fake();
    config()->set('workflow-engine.signals.buffer_early', false);
    $engine = errorEngine(AwaitWorkflowDefinition::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_off', 'invoice');

    // Still pending, not awaiting anything yet.
    expect(fn() => $engine->signal($instance->id, 'payment_succeeded'))
        ->toThrow(InvalidSignalException::class);
});

it('rejects a signal that does not match the awaited one when buffering is off', function (): void {
    Queue::fake();
    config()->set('workflow-engine.signals.buffer_early', false);
    $engine = errorEngine(AwaitWorkflowDefinition::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_mismatch', 'invoice');
    $engine->advance($instance->id); // awaiting payment_succeeded

    try {
        $engine->signal($instance->id, 'wrong_signal');
        $this->fail('Expected InvalidSignalException.');
    } catch (InvalidSignalException $exception) {
        expect($exception->getMessage())->toContain('wrong_signal')
            ->and($exception->getMessage())->toContain('payment_succeeded');
    }
});

it('rejects a signal delivered to a terminal instance even with buffering on', function (): void {
    Queue::fake();
    $engine = errorEngine(AutoWorkflowDefinition::class);

    $instance = $engine->start(AutoWorkflowDefinition::name(), 'member_done', 'member');
    $engine->advance($instance->id);
    $engine->advance($instance->id); // completed

    expect(fn() => $engine->signal($instance->id, 'anything'))
        ->toThrow(InvalidSignalException::class);
});

it('throws when a step routes to a goto target outside the sequence', function (): void {
    Queue::fake();
    $engine = errorEngine(BadGotoWorkflowDefinition::class);

    $instance = $engine->start(BadGotoWorkflowDefinition::name(), 'agg_badgoto', 'thing');

    expect(fn() => $engine->advance($instance->id))
        ->toThrow(RuntimeException::class);
});
