<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Transitions;

use JustSteveKing\StateMachine\Contracts\StateContract;
use JustSteveKing\StateMachine\Contracts\TransitionContract;
use JustSteveKing\StateMachine\Events\DomainEvent;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStatusChanged;

/**
 * Move an instance into active processing: starting, resuming a signal, waking
 * from sleep, or retrying a failed instance.
 */
final class Proceed implements TransitionContract
{
    /** @return array<int, StateContract> */
    public function from(): array
    {
        return [
            WorkflowStatus::Pending,
            WorkflowStatus::InProgress,
            WorkflowStatus::Awaiting,
            WorkflowStatus::Sleeping,
            WorkflowStatus::Failed,
        ];
    }

    public function to(): StateContract
    {
        return WorkflowStatus::InProgress;
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
