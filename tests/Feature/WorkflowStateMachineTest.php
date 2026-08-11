<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\StateMachine\Exceptions\InvalidTransitionException;
use JustSteveKing\WorkflowEngine\StateMachine\StateMachine;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowRepository;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStateMachine;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStatusChanged;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;

it('produces a domain event for a legal status transition', function (): void {
    $machine = new StateMachine(
        machine: new WorkflowStateMachine(WorkflowStatus::InProgress),
    );

    $event = $machine->transition(WorkflowStateMachine::transitionFor(WorkflowStatus::Completed));

    expect($event)->toBeInstanceOf(WorkflowStatusChanged::class)
        ->and($event->from->value())->toBe('in_progress')
        ->and($event->to->value())->toBe('completed')
        ->and($event->name())->toBe('workflow.status.changed');
});

it('rejects an illegal status transition', function (): void {
    $machine = new StateMachine(
        machine: new WorkflowStateMachine(WorkflowStatus::Completed),
    );

    expect(fn() => $machine->transition(WorkflowStateMachine::transitionFor(WorkflowStatus::Awaiting)))
        ->toThrow(InvalidTransitionException::class);
});

it('exposes the full transition table for a state', function (): void {
    $machine = new WorkflowStateMachine(WorkflowStatus::Pending);

    expect($machine->currentState())->toBe(WorkflowStatus::Pending)
        ->and($machine->transitions())->toHaveCount(6);
});

it('accepts exactly the declared edges and rejects the rest for every state', function (): void {
    $targets = [
        WorkflowStatus::InProgress,
        WorkflowStatus::Awaiting,
        WorkflowStatus::Sleeping,
        WorkflowStatus::Completed,
        WorkflowStatus::Compensating,
        WorkflowStatus::Failed,
    ];

    foreach (WorkflowStatus::cases() as $current) {
        foreach ($targets as $target) {
            $transition = WorkflowStateMachine::transitionFor($target);
            $legal = in_array($current, $transition->from(), true);
            $machine = new StateMachine(machine: new WorkflowStateMachine($current));

            if ($legal) {
                $event = $machine->transition($transition);
                expect($event->to->value())->toBe($target->value());
            } else {
                expect(fn() => $machine->transition($transition))
                    ->toThrow(InvalidTransitionException::class);
            }
        }
    }
});

it('prevents a completed instance from being driven into an illegal state', function (): void {
    Queue::fake();
    app(WorkflowRegistry::class)->register(AutoWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(
        workflowName: AutoWorkflowDefinition::name(),
        aggregateId: 'member_sm',
        aggregateType: 'member',
    );

    $engine->advance($instance->id);
    $engine->advance($instance->id); // completed

    $completed = app(WorkflowRepository::class)->findById($instance->id);

    expect($completed?->isCompleted())->toBeTrue()
        ->and(fn() => $completed?->awaitSignal('anything'))->toThrow(InvalidTransitionException::class);
});
