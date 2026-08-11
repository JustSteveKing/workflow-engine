<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Exceptions;

use DomainException;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\TransitionContract;

final class InvalidTransitionException extends DomainException
{
    public static function notRegistered(
        TransitionContract $transition,
        StateContract $current,
    ): self {
        return new self(sprintf(
            'Transition [%s] is not registered on this state machine. Current state: [%s].',
            $transition::class,
            $current->value(),
        ));
    }

    public static function wrongState(
        TransitionContract $transition,
        StateContract $current,
    ): self {
        $allowed = implode(', ', array_map(
            static fn(StateContract $state): string => $state->value(),
            $transition->from(),
        ));

        return new self(sprintf(
            'Cannot apply transition [%s] from state [%s]. Allowed from: [%s].',
            $transition::class,
            $current->value(),
            $allowed,
        ));
    }

    public static function guardFailed(
        TransitionContract $transition,
        string $reason,
    ): self {
        return new self(sprintf(
            'Transition [%s] was blocked by guard: %s',
            $transition::class,
            $reason,
        ));
    }
}
