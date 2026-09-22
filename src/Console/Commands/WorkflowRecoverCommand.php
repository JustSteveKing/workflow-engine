<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Jobs\CompensateWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

/**
 * Safety net for instances that are mid-flight but have no job left to move
 * them.
 *
 * Progression is push-based: every transition dispatches the next job, so
 * nothing polls. An instance strands when that dispatch is lost rather than
 * delayed — the job exhausted its retries into the failed queue, or start()
 * succeeded and the caller died before driving it. It then sits in pending,
 * in_progress or compensating indefinitely. workflow:tick covers sleeping
 * instances, whose delay is known in advance; these have no wake time to wait
 * for and nothing else looks at them.
 *
 * That matters beyond the stuck workflow: a non-terminal instance still owns
 * its aggregate, so whatever it is about — the member, the order, the
 * subscription — is refused every further change for as long as it sits there.
 *
 * Re-dispatching is safe to repeat. Both jobs take a row lock and return
 * immediately unless the instance is still in a state they can move.
 */
final class WorkflowRecoverCommand extends WorkflowCommand
{
    protected $signature = 'workflow:recover
        {--minutes=15 : Minutes an instance must have been idle before it is re-dispatched}
        {--limit=100 : Most instances to re-dispatch in a single run}';

    protected $description = 'Re-dispatch jobs for instances stranded mid-flight with nothing left to move them.';

    public function handle(Dispatcher $bus, WorkflowEngine $engine): int
    {
        $minutes = $this->intOption('minutes', 15);
        $limit = $this->intOption('limit', 100);

        if ($minutes < 1 || $limit < 1) {
            $this->error('Both --minutes and --limit must be at least 1.');

            return self::FAILURE;
        }

        /*
         * The threshold has to clear work that is legitimately in flight: a
         * failing step is re-dispatched after the configured backoff, and the
         * queue's own retry window sits on top of that, so a healthy instance
         * can be quiet for minutes at a time.
         */
        $stranded = WorkflowInstance::query()
            ->whereIn('status', [
                WorkflowStatus::Pending->value,
                WorkflowStatus::InProgress->value,
                WorkflowStatus::Compensating->value,
            ])
            ->where('updated_at', '<', Carbon::now()->subMinutes($minutes))
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();

        foreach ($stranded as $instance) {
            // A compensating instance needs its rollback re-driven; advance()
            // returns early for anything that is not running or sleeping.
            $job = WorkflowStatus::Compensating->value === $instance->status
                ? new CompensateWorkflow($instance->id)
                : new AdvanceWorkflow($instance->id);

            $engine->routeOntoWorkflowQueue($job);

            $bus->dispatch($job);
        }

        $this->info("Re-dispatched {$stranded->count()} stranded instance(s).");

        return self::SUCCESS;
    }
}
