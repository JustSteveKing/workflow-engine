<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

final readonly class StepFailed
{
    /**
     * @param  class-string  $stepClass
     */
    public function __construct(
        public int|string $instanceId,
        public string $stepClass,
        public string $reason,
        public int $attempt,
        public bool $willRetry,
    ) {}
}
