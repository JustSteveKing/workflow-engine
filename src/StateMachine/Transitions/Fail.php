<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Transitions;

use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\TransitionContract;
use JustSteveKing\WorkflowEngine\StateMachine\Events\DomainEvent;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStatusChanged;

/**
 * Fail an instance: directly, on timeout, or after compensation finishes.
 *
 * @internal
 */
final class Fail implements TransitionContract
{
    /** @return array<int, StateContract> */
    public function from(): array
    {
        return [
            WorkflowStatus::Pending,
            WorkflowStatus::InProgress,
            WorkflowStatus::Awaiting,
            WorkflowStatus::Compensating,
        ];
    }

    public function to(): StateContract
    {
        return WorkflowStatus::Failed;
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
