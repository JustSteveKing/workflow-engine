<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

/**
 * Safety net for sleeping instances whose delayed advance job was lost (queue
 * restart, flushed store). Run from the scheduler, e.g. every minute.
 */
final class WorkflowTickCommand extends WorkflowCommand
{
    protected $signature = 'workflow:tick {--limit=100 : Maximum instances to re-dispatch per run}';

    protected $description = 'Re-dispatch advance jobs for sleeping instances past their wake time.';

    public function handle(): int
    {
        $limit = $this->intOption('limit', 100);

        /** @var iterable<int, WorkflowInstance> $due */
        $due = WorkflowInstance::query()
            ->where('status', WorkflowStatus::Sleeping->value)
            ->where('wake_at', '<=', Carbon::now())
            ->orderBy('wake_at')
            ->limit($limit)
            ->get();

        $count = 0;

        foreach ($due as $instance) {
            $job = AdvanceWorkflow::dispatch($instance->id);

            if (is_string($connection = config('workflow-engine.queue.connection'))) {
                $job->onConnection($connection);
            }

            if (is_string($queue = config('workflow-engine.queue.name'))) {
                $job->onQueue($queue);
            }

            $count++;
        }

        $this->info("Re-dispatched advance for {$count} sleeping instance(s) past their wake time.");

        return self::SUCCESS;
    }
}
