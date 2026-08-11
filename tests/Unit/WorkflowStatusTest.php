<?php

declare(strict_types=1);

use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;

it('exposes its backing value through the StateContract', function (): void {
    expect(WorkflowStatus::InProgress)->toBeInstanceOf(StateContract::class)
        ->and(WorkflowStatus::InProgress->value())->toBe('in_progress')
        ->and(WorkflowStatus::InProgress->value)->toBe('in_progress');
});

it('provides a human label for every case', function (): void {
    foreach (WorkflowStatus::cases() as $case) {
        expect($case->label())->toBeString()->not->toBe('');
    }

    expect(WorkflowStatus::Awaiting->label())->toBe('Awaiting signal');
});

it('marks only completed and failed as terminal', function (): void {
    expect(WorkflowStatus::Completed->isTerminal())->toBeTrue()
        ->and(WorkflowStatus::Failed->isTerminal())->toBeTrue()
        ->and(WorkflowStatus::Pending->isTerminal())->toBeFalse()
        ->and(WorkflowStatus::InProgress->isTerminal())->toBeFalse()
        ->and(WorkflowStatus::Awaiting->isTerminal())->toBeFalse()
        ->and(WorkflowStatus::Sleeping->isTerminal())->toBeFalse()
        ->and(WorkflowStatus::Compensating->isTerminal())->toBeFalse();
});
