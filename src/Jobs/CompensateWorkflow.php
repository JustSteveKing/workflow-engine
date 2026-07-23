<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;

final class CompensateWorkflow implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int|string $workflowInstanceId,
    ) {}

    /**
     * @throws WorkflowNotFoundException
     */
    public function handle(WorkflowEngine $engine): void
    {
        $engine->compensate($this->workflowInstanceId);
    }
}
