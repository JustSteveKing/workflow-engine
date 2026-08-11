<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Events;

use DateTimeImmutable;
use JustSteveKing\WorkflowEngine\StateMachine\Contracts\StateContract;

/**
 * @internal
 */
abstract class DomainEvent
{
    public readonly DateTimeImmutable $occurredAt;

    public function __construct(
        public readonly StateContract $from,
        public readonly StateContract $to,
        public readonly mixed $context,
    ) {
        $this->occurredAt = new DateTimeImmutable();
    }

    abstract public function name(): string;
}
