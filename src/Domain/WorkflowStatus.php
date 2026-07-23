<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

use JustSteveKing\StateMachine\Contracts\StateContract;

enum WorkflowStatus: string implements StateContract
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Awaiting = 'awaiting';
    case Sleeping = 'sleeping';
    case Compensating = 'compensating';
    case Completed = 'completed';
    case Failed = 'failed';

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In progress',
            self::Awaiting => 'Awaiting signal',
            self::Sleeping => 'Sleeping',
            self::Compensating => 'Compensating',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether this is a terminal status the workflow cannot leave on its own.
     */
    public function isTerminal(): bool
    {
        return self::Completed === $this || self::Failed === $this;
    }
}
