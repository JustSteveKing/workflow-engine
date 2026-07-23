<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

final class WorkflowInstancesCommand extends WorkflowCommand
{
    protected $signature = 'workflow:instances
        {--status= : Filter by status}
        {--workflow= : Filter by workflow name}
        {--aggregate= : Filter by aggregate id}
        {--limit=50 : Maximum rows to show}';

    protected $description = 'List workflow instances with an optional filter, plus a status summary.';

    public function handle(): int
    {
        $parts = [];
        foreach (WorkflowStatus::cases() as $case) {
            $count = WorkflowInstance::query()->where('status', $case->value)->count();
            if ($count > 0) {
                $parts[] = "{$case->value}={$count}";
            }
        }

        $this->line('<fg=gray>By status:</> ' . ([] === $parts ? 'none' : implode('  ', $parts)));
        $this->newLine();

        $query = WorkflowInstance::query()->latest('updated_at');

        if (null !== $status = $this->stringOption('status')) {
            $query->where('status', $status);
        }

        if (null !== $workflow = $this->stringOption('workflow')) {
            $query->where('workflow_name', $workflow);
        }

        if (null !== $aggregate = $this->stringOption('aggregate')) {
            $query->where('aggregate_id', $aggregate);
        }

        $instances = $query->limit($this->intOption('limit', 50))->get();

        if ($instances->isEmpty()) {
            $this->warn('No matching instances.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($instances as $instance) {
            $rows[] = [
                (string) $instance->id,
                $instance->workflow_name,
                $instance->status,
                (string) $instance->step_index,
                $instance->awaiting_signal ?? ($instance->wake_at?->toDateTimeString() ?? '—'),
                $instance->updated_at?->diffForHumans() ?? '—',
            ];
        }

        $this->table(['Id', 'Workflow', 'Status', 'Step', 'Awaiting / Wakes', 'Updated'], $rows);

        return self::SUCCESS;
    }
}
