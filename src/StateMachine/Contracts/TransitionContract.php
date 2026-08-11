<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Contracts;

use JustSteveKing\WorkflowEngine\StateMachine\Events\DomainEvent;

/**
 * @internal
 */
interface TransitionContract
{
    /**
     * The states this transition can be applied FROM.
     *
     * @return array<int, StateContract>
     */
    public function from(): array;

    /**
     * The state this transition leads TO.
     */
    public function to(): StateContract;

    /**
     * The domain event class name to produce on success.
     *
     * @return class-string<DomainEvent>
     */
    public function eventClass(): string;

    /**
     * Guard: additional runtime conditions beyond a valid "from" state. Return
     * null to allow, or a reason string to deny.
     */
    public function guard(mixed $context): ?string;
}
