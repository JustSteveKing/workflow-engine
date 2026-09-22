<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;

final class CompensateWorkflow implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
