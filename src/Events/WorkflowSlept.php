<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

use DateTimeInterface;

final readonly class WorkflowSlept
{
    public function __construct(
        public int|string $instanceId,
        public DateTimeInterface $wakeAt,
    ) {}
}
