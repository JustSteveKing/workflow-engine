<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use RuntimeException;
use Throwable;

final class WorkflowRetryCommand extends WorkflowCommand
{
    protected $signature = 'workflow:retry
        {id? : A single failed instance id to retry}
        {--workflow= : Retry all failed instances of this workflow}
        {--failed-since= : Only retry instances failed at or after this time (any strtotime value)}
        {--force : Skip the confirmation prompt when retrying in bulk}';

    protected $description = 'Re-arm one or more failed workflow instances.';

    public function handle(WorkflowEngine $engine): int
    {
        $id = $this->stringArgumentOrNull('id');

        if (null !== $id) {
            try {
                $engine->retry($id);
            } catch (RuntimeException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->info("Re-armed instance {$id}.");

            return self::SUCCESS;
        }

        $query = WorkflowInstance::query()->where('status', WorkflowStatus::Failed->value);

        if (null !== $workflow = $this->stringOption('workflow')) {
            $query->where('workflow_name', $workflow);
        }

        if (null !== $since = $this->stringOption('failed-since')) {
            $query->where('failed_at', '>=', Carbon::parse($since));
        }

        $instances = $query->get();

        if ($instances->isEmpty()) {
            $this->warn('No failed instances match.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Retry %d failed instance(s)?', $instances->count()))) {
            return self::SUCCESS;
        }

        $retried = 0;

        foreach ($instances as $instance) {
            try {
                $engine->retry($instance->id);
                $retried++;
            } catch (Throwable $exception) {
                $this->warn("Skipped {$instance->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Re-armed {$retried} instance(s).");

        return self::SUCCESS;
    }
}
