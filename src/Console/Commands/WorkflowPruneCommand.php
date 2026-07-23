<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

final class WorkflowPruneCommand extends WorkflowCommand
{
    protected $signature = 'workflow:prune
        {--days=30 : Delete terminal instances not updated within this many days}
        {--status=completed,failed : Comma-separated terminal statuses to prune}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete old terminal workflow instances (their signals cascade).';

    public function handle(): int
    {
        $statuses = array_values(array_filter(array_map(
            'trim',
            explode(',', $this->stringOption('status') ?? ''),
        )));

        $terminal = [WorkflowStatus::Completed->value, WorkflowStatus::Failed->value];
        $invalid = array_diff($statuses, $terminal);

        if ([] !== $invalid) {
            $this->error('Only terminal statuses may be pruned: ' . implode(', ', $terminal) . '.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays(max(0, $this->intOption('days', 30)));

        $query = WorkflowInstance::query()
            ->whereIn('status', $statuses)
            ->where('updated_at', '<', $cutoff);

        $count = $query->count();

        if (0 === $count) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$count} instance(s) updated before {$cutoff->toDateTimeString()}?")) {
            return self::SUCCESS;
        }

        $query->delete();

        $this->info("Pruned {$count} instance(s).");

        return self::SUCCESS;
    }
}
