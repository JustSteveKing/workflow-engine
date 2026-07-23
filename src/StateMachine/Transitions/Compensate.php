<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Transitions;

use JustSteveKing\StateMachine\Contracts\StateContract;
use JustSteveKing\StateMachine\Contracts\TransitionContract;
use JustSteveKing\StateMachine\Events\DomainEvent;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStatusChanged;

/**
 * Begin saga compensation after a failure that has completed steps to undo.
 */
final class Compensate implements TransitionContract
{
    /** @return array<int, StateContract> */
    public function from(): array
    {
        return [
            WorkflowStatus::InProgress,
            WorkflowStatus::Awaiting,
        ];
    }

    public function to(): StateContract
    {
        return WorkflowStatus::Compensating;
    }

    /** @return class-string<DomainEvent> */
    public function eventClass(): string
    {
        return WorkflowStatusChanged::class;
    }

    public function guard(mixed $context): ?string
    {
        return null;
    }
}
