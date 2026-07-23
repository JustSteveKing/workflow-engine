<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine;

use JustSteveKing\StateMachine\Events\DomainEvent;

/**
 * The domain event produced by the state machine on a valid status transition.
 * Its `from`/`to` are WorkflowStatus values.
 */
final class WorkflowStatusChanged extends DomainEvent
{
    public function name(): string
    {
        return 'workflow.status.changed';
    }
}
