<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

final readonly class StepTimedOut
{
    public function __construct(
        public int|string $instanceId,
        public string $signal,
        public int $stepIndex,
    ) {}
}
