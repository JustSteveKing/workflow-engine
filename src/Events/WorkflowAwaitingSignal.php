<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

final readonly class WorkflowAwaitingSignal
{
    public function __construct(
        public int|string $instanceId,
        public string $signal,
    ) {}
}
