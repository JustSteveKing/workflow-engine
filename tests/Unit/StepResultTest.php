<?php

declare(strict_types=1);

use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\GotoTargetStep;

it('builds a complete result', function (): void {
    $result = StepResult::complete(['a' => 1]);

    expect($result->isComplete())->toBeTrue()
        ->and($result->isAwaiting())->toBeFalse()
        ->and($result->isGoto())->toBeFalse()
        ->and($result->isSleep())->toBeFalse()
        ->and($result->isFailed())->toBeFalse()
        ->and($result->contextUpdates)->toBe(['a' => 1]);
});

it('builds an await result', function (): void {
    $result = StepResult::await('payment_succeeded', ['b' => 2]);

    expect($result->isAwaiting())->toBeTrue()
        ->and($result->awaitingSignal)->toBe('payment_succeeded')
        ->and($result->contextUpdates)->toBe(['b' => 2]);
});

it('builds a fail result', function (): void {
    $result = StepResult::fail('nope');

    expect($result->isFailed())->toBeTrue()
        ->and($result->failureReason)->toBe('nope');
});

it('builds a goto result', function (): void {
    $result = StepResult::goto(GotoTargetStep::class, ['c' => 3]);

    expect($result->isGoto())->toBeTrue()
        ->and($result->gotoStep)->toBe(GotoTargetStep::class)
        ->and($result->contextUpdates)->toBe(['c' => 3]);
});

it('builds a sleep result', function (): void {
    $result = StepResult::sleep(60, ['d' => 4]);

    expect($result->isSleep())->toBeTrue()
        ->and($result->sleepSeconds)->toBe(60)
        ->and($result->contextUpdates)->toBe(['d' => 4]);
});

it('defaults context updates to an empty array', function (): void {
    expect(StepResult::complete()->contextUpdates)->toBe([])
        ->and(StepResult::await('x')->contextUpdates)->toBe([]);
});
