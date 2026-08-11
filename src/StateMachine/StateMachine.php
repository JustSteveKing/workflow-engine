<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine;

use Illuminate\Support\Collection;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateMachineContract;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\TransitionContract;
use JustSteveKing\WorkflowEngine\StateMachine\Events\DomainEvent;
use JustSteveKing\WorkflowEngine\StateMachine\Exceptions\InvalidTransitionException;

/**
 * @internal
 */
final class StateMachine
{
    public function __construct(
        private readonly StateMachineContract $machine,
    ) {}

    /**
     * Attempt a transition. Returns a domain event on success, throws
     * InvalidTransitionException on failure.
     */
    public function transition(
        TransitionContract $transition,
        mixed $context = null,
    ): DomainEvent {
        $current = $this->machine->currentState();

        // 1. Is this transition registered on this machine?
        if (! $this->isRegistered($transition)) {
            throw InvalidTransitionException::notRegistered($transition, $current);
        }

        // 2. Is the current state in the transition's allowed "from" states?
        $validFrom = (new Collection($transition->from()))
            ->contains(fn(StateContract $state): bool => $state->value() === $current->value());

        if (! $validFrom) {
            throw InvalidTransitionException::wrongState($transition, $current);
        }

        // 3. Run the guard.
        $denial = $transition->guard($context);

        if (null !== $denial) {
            throw InvalidTransitionException::guardFailed($transition, $denial);
        }

        // 4. Produce the domain event.
        $eventClass = $transition->eventClass();

        return new $eventClass(
            from: $current,
            to: $transition->to(),
            context: $context,
        );
    }

    private function isRegistered(TransitionContract $transition): bool
    {
        return (new Collection($this->machine->transitions()))
            ->contains(fn(TransitionContract $registered): bool => $registered::class === $transition::class);
    }
}
