<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

/**
 * An operator stopped an instance that was not going to finish on its own.
 *
 * Dispatched before the instance terminates, so a listener can tell a
 * cancellation from the WorkflowFailed or WorkflowCompensating that follows it.
 */
final readonly class WorkflowCancelled
{
    public function __construct(
        public int|string $instanceId,
        public string $reason,
        public ?string $cancelledBy = null,
    ) {}
}
