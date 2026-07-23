<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowRepositoryContract;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;

final class WorkflowShowCommand extends WorkflowCommand
{
    protected $signature = 'workflow:show {id : The workflow instance id}';

    protected $description = 'Inspect a workflow instance: status, cursor, context, and signal log.';

    public function handle(WorkflowRepositoryContract $repository): int
    {
        $id = $this->stringArgument('id');
        $instance = $repository->findById($id);

        if (null === $instance) {
            $this->error("Workflow instance [{$id}] not found.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=gray>Instance</>', (string) $instance->id);
        $this->components->twoColumnDetail('<fg=gray>Workflow</>', $instance->workflowName . ' v' . $instance->definitionVersion);
        $this->components->twoColumnDetail('<fg=gray>Aggregate</>', $instance->aggregateType . ' / ' . $instance->aggregateId);
        $this->components->twoColumnDetail('<fg=gray>Status</>', $this->paintStatus($instance->status->value));
        $this->components->twoColumnDetail('<fg=gray>Step</>', $instance->stepIndex . ' / ' . $instance->stepCount() . ' (attempts: ' . $instance->attempts . ')');

        if (null !== $instance->awaitingSignal) {
            $this->components->twoColumnDetail('<fg=gray>Awaiting signal</>', $instance->awaitingSignal);
        }

        if (null !== $instance->wakeAt) {
            $this->components->twoColumnDetail('<fg=gray>Wakes at</>', $instance->wakeAt->toDateTimeString());
        }

        if (null !== $instance->failedReason) {
            $this->components->twoColumnDetail('<fg=gray>Failed reason</>', $instance->failedReason);
        }

        $this->components->twoColumnDetail('<fg=gray>Started / Completed / Failed</>', sprintf(
            '%s / %s / %s',
            $instance->startedAt?->toDateTimeString() ?? '—',
            $instance->completedAt?->toDateTimeString() ?? '—',
            $instance->failedAt?->toDateTimeString() ?? '—',
        ));

        $this->newLine();
        $this->line('<fg=gray>Steps</>');
        foreach ($instance->stepSequence as $index => $stepClass) {
            $marker = $index === $instance->stepIndex ? '<fg=cyan>➤</>' : ' ';
            $done = in_array($stepClass, $instance->completedSteps, true) ? '<fg=green>✓</>' : ' ';
            $this->line("  {$marker} {$done} {$index}  {$stepClass}");
        }

        $this->newLine();
        $this->line('<fg=gray>Context</>');
        $this->line('  ' . (json_encode($instance->context->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'));

        /** @var iterable<int, WorkflowSignal> $signals */
        $signals = WorkflowSignal::query()
            ->where('workflow_instance_id', $instance->id)
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($signals as $signal) {
            $rows[] = [
                $signal->signal,
                $signal->delivered_by ?? '—',
                null === $signal->consumed_at ? '<fg=yellow>BUFFERED</>' : $signal->consumed_at->toDateTimeString(),
            ];
        }

        $this->newLine();
        if ([] === $rows) {
            $this->line('<fg=gray>No signals recorded.</>');
        } else {
            $this->table(['Signal', 'Delivered by', 'Consumed at'], $rows);
        }

        return self::SUCCESS;
    }

    private function paintStatus(string $status): string
    {
        return match ($status) {
            'completed' => '<fg=green>completed</>',
            'failed' => '<fg=red>failed</>',
            'awaiting', 'sleeping' => "<fg=yellow>{$status}</>",
            default => $status,
        };
    }
}
