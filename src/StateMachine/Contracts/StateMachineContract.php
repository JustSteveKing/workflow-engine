<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Contracts;

/**
 * @internal
 */
interface StateMachineContract
{
    /**
     * The transition definitions for this machine.
     *
     * @return array<int, TransitionContract>
     */
    public function transitions(): array;

    /**
     * The current state of the entity being governed.
     */
    public function currentState(): StateContract;
}
