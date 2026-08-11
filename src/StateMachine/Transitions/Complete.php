<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Transitions;

use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\TransitionContract;
use JustSteveKing\WorkflowEngine\StateMachine\Events\DomainEvent;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStatusChanged;

/**
 * Finish an instance once its step sequence is exhausted.
 *
 * @internal
 */
final class Complete implements TransitionContract
{
    /** @return array<int, StateContract> */
    public function from(): array
    {
        return [
            WorkflowStatus::Pending,
            WorkflowStatus::InProgress,
        ];
    }

    public function to(): StateContract
    {
        return WorkflowStatus::Completed;
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
