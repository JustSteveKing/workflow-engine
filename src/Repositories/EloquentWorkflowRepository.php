<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Repositories;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowRepository;
use JustSteveKing\WorkflowEngine\Domain\BufferedSignal;
use JustSteveKing\WorkflowEngine\Domain\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance as EloquentWorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;

final class EloquentWorkflowRepository implements WorkflowRepository
{
    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    /**
     * @param  list<class-string>  $stepSequence
     * @param  array<string, mixed>  $initialContext
     */
    public function create(
        string $workflowName,
        string $workflowDefinitionClass,
        int $definitionVersion,
        array $stepSequence,
        string $aggregateId,
        string $aggregateType,
        array $initialContext = [],
    ): WorkflowInstance {
        $eloquent = EloquentWorkflowInstance::query()->create([
            'workflow_name' => $workflowName,
            'workflow_definition_class' => $workflowDefinitionClass,
            'definition_version' => $definitionVersion,
            'step_sequence' => $stepSequence,
            'aggregate_id' => $aggregateId,
            'aggregate_type' => $aggregateType,
            'status' => WorkflowStatus::Pending->value,
            'step_index' => 0,
            'attempts' => 0,
            'completed_steps' => [],
            'context' => $initialContext,
            'started_at' => Carbon::now(),
        ]);

        return WorkflowInstance::fromEloquent($eloquent);
    }

    public function findById(int|string $id, bool $lock = false): ?WorkflowInstance
    {
        $query = EloquentWorkflowInstance::query();

        if ($lock) {
            $query->lockForUpdate();
        }

        $eloquent = $query->find($id);

        return $eloquent ? WorkflowInstance::fromEloquent($eloquent) : null;
    }

    public function findAwaitingSignal(
        string $signal,
        string $aggregateId,
        ?string $aggregateType = null,
    ): array {
        $query = EloquentWorkflowInstance::query()->where('awaiting_signal', $signal)
            ->where('aggregate_id', $aggregateId)
            ->where('status', 'awaiting');

        if (null !== $aggregateType) {
            $query->where('aggregate_type', $aggregateType);
        }

        return $query->get()
            ->map(fn(EloquentWorkflowInstance $eloquent) => WorkflowInstance::fromEloquent($eloquent))
            ->all();
    }

    public function save(WorkflowInstance $instance): void
    {
        EloquentWorkflowInstance::query()->findOrFail($instance->id)->update([
            'status' => $instance->status->value,
            'step_index' => $instance->stepIndex,
            'attempts' => $instance->attempts,
            'completed_steps' => $instance->completedSteps,
            'awaiting_signal' => $instance->awaitingSignal,
            'wake_at' => $instance->wakeAt,
            'context' => $instance->context->all(),
            'failed_reason' => $instance->failedReason,
            'completed_at' => $instance->completedAt,
            'failed_at' => $instance->failedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $signalData
     */
    public function recordSignal(
        int|string $workflowInstanceId,
        string $signal,
        array $signalData = [],
        ?string $deliveredBy = null,
        bool $consumed = true,
    ): void {
        WorkflowSignal::query()->create([
            'workflow_instance_id' => $workflowInstanceId,
            'signal' => $signal,
            'signal_data' => $signalData,
            'delivered_by' => $deliveredBy,
            'consumed_at' => $consumed ? Carbon::now() : null,
        ]);
    }

    public function pullBufferedSignal(int|string $workflowInstanceId, string $signal): ?BufferedSignal
    {
        $buffered = WorkflowSignal::query()
            ->where('workflow_instance_id', $workflowInstanceId)
            ->where('signal', $signal)
            ->whereNull('consumed_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if (null === $buffered) {
            return null;
        }

        $buffered->update(['consumed_at' => Carbon::now()]);

        return new BufferedSignal(
            data: $buffered->signal_data ?? [],
            deliveredBy: $buffered->delivered_by,
        );
    }

    public function discardBufferedSignals(int|string $workflowInstanceId): void
    {
        WorkflowSignal::query()
            ->where('workflow_instance_id', $workflowInstanceId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now()]);
    }
}
