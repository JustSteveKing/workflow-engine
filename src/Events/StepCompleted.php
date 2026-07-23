<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

final readonly class StepCompleted
{
    /**
     * @param  class-string  $stepClass
     */
    public function __construct(
        public int|string $instanceId,
        public string $stepClass,
        public int $stepIndex,
    ) {}
}
