<?php

declare(strict_types=1);

use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

function makeContext(array $data = []): WorkflowContext
{
    return WorkflowContext::make('inst_1', 'agg_1', 'member', $data);
}

it('reads values with an optional default', function (): void {
    $context = makeContext(['plan' => 'annual']);

    expect($context->get('plan'))->toBe('annual')
        ->and($context->get('missing'))->toBeNull()
        ->and($context->get('missing', 'fallback'))->toBe('fallback');
});

it('reports key presence', function (): void {
    $context = makeContext(['plan' => 'annual', 'nullable' => null]);

    expect($context->has('plan'))->toBeTrue()
        ->and($context->has('missing'))->toBeFalse()
        ->and($context->has('nullable'))->toBeFalse();
});

it('exposes all data and identity', function (): void {
    $context = makeContext(['a' => 1]);

    expect($context->all())->toBe(['a' => 1])
        ->and($context->workflowInstanceId)->toBe('inst_1')
        ->and($context->aggregateId)->toBe('agg_1')
        ->and($context->aggregateType)->toBe('member');
});

it('is immutable — with() returns a new merged instance', function (): void {
    $original = makeContext(['a' => 1, 'b' => 2]);
    $next = $original->with(['b' => 20, 'c' => 3]);

    expect($original->all())->toBe(['a' => 1, 'b' => 2])
        ->and($next->all())->toBe(['a' => 1, 'b' => 20, 'c' => 3])
        ->and($next)->not->toBe($original);
});
