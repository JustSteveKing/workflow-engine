<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\StateMachine\StateMachine;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance as EloquentWorkflowInstance;
use JustSteveKing\WorkflowEngine\StateMachine\WorkflowStateMachine;

/**
 * The in-memory state of one workflow run. Its properties are public to read,
 * and they mirror the persisted columns one to one. Mutate them only through the
 * transition methods on this class (advanceStep(), awaitSignal(), retry(), and so
 * on), which validate the change against the state machine first. Do not write
 * the properties from outside.
 */
final class WorkflowInstance
{
    /**
     * @param  list<class-string>  $stepSequence
     * @param  list<class-string>  $completedSteps
     */
    private function __construct(
        public readonly int|string $id,
        public readonly string $workflowName,
        public readonly string $workflowDefinitionClass,
        public readonly int $definitionVersion,
        public readonly array $stepSequence,
        public readonly string $aggregateId,
        public readonly string $aggregateType,
        public WorkflowStatus $status,
        public int $stepIndex,
        public int $attempts,
        public array $completedSteps,
        public ?string $awaitingSignal,
        public ?CarbonInterface $wakeAt,
        public WorkflowContext $context,
        public ?string $failedReason,
        public ?CarbonInterface $startedAt,
        public ?CarbonInterface $completedAt,
        public ?CarbonInterface $failedAt,
    ) {}

    /**
     * Create a new workflow instance.
     *
     * @param  list<class-string>  $stepSequence
     * @param  array<string, mixed>  $initialContext
     */
    public static function create(
        string $workflowName,
        string $workflowDefinitionClass,
        int $definitionVersion,
        array $stepSequence,
        string $aggregateId,
        string $aggregateType,
        array $initialContext = [],
    ): self {
        return new self(
            id: '',
            workflowName: $workflowName,
            workflowDefinitionClass: $workflowDefinitionClass,
            definitionVersion: $definitionVersion,
            stepSequence: $stepSequence,
            aggregateId: $aggregateId,
            aggregateType: $aggregateType,
            status: WorkflowStatus::Pending,
            stepIndex: 0,
            attempts: 0,
            completedSteps: [],
            awaitingSignal: null,
            wakeAt: null,
            context: WorkflowContext::make('', $aggregateId, $aggregateType, $initialContext),
            failedReason: null,
            startedAt: null,
            completedAt: null,
            failedAt: null,
        );
    }

    /**
     * Hydrate from a database record.
     */
    public static function fromEloquent(EloquentWorkflowInstance $model): self
    {
        /** @var array<string, mixed> $context */
        $context = $model->context ?? [];

        /** @var list<class-string> $stepSequence */
        $stepSequence = $model->step_sequence ?? [];

        /** @var list<class-string> $completedSteps */
        $completedSteps = $model->completed_steps ?? [];

        return new self(
            id: $model->id,
            workflowName: $model->workflow_name,
            workflowDefinitionClass: $model->workflow_definition_class,
            definitionVersion: $model->definition_version,
            stepSequence: $stepSequence,
            aggregateId: $model->aggregate_id,
            aggregateType: $model->aggregate_type,
            status: WorkflowStatus::from($model->status),
            stepIndex: $model->step_index,
            attempts: $model->attempts,
            completedSteps: $completedSteps,
            awaitingSignal: $model->awaiting_signal,
            wakeAt: $model->wake_at,
            context: WorkflowContext::make($model->id, $model->aggregate_id, $model->aggregate_type, $context),
            failedReason: $model->failed_reason,
            startedAt: $model->started_at,
            completedAt: $model->completed_at,
            failedAt: $model->failed_at,
        );
    }

    public function isRunning(): bool
    {
        return WorkflowStatus::Pending === $this->status || WorkflowStatus::InProgress === $this->status;
    }

    public function isAwaiting(): bool
    {
        return WorkflowStatus::Awaiting === $this->status;
    }

    public function isSleeping(): bool
    {
        return WorkflowStatus::Sleeping === $this->status;
    }

    public function isCompensating(): bool
    {
        return WorkflowStatus::Compensating === $this->status;
    }

    public function isCompleted(): bool
    {
        return WorkflowStatus::Completed === $this->status;
    }

    public function isFailed(): bool
    {
        return WorkflowStatus::Failed === $this->status;
    }

    /**
     * The class of the step at the current cursor, or null if the cursor is
     * past the end of the sequence.
     *
     * @return class-string|null
     */
    public function currentStepClass(): ?string
    {
        return $this->stepSequence[$this->stepIndex] ?? null;
    }

    public function stepCount(): int
    {
        return count($this->stepSequence);
    }

    /**
     * Find the index of a step class within the snapshotted sequence.
     */
    public function indexOfStep(string $stepClass): ?int
    {
        $index = array_search($stepClass, $this->stepSequence, true);

        return false === $index ? null : $index;
    }

    /**
     * Merge updates into the (immutable) context.
     *
     * @param  array<string, mixed>  $updates
     */
    public function mergeContext(array $updates): void
    {
        if ([] === $updates) {
            return;
        }

        $this->context = $this->context->with($updates);
    }

    /**
     * Record that the current step ran forward, for later compensation. Recorded
     * once per distinct step, so a workflow that loops via goto() does not grow
     * the list without bound or compensate the same step repeatedly.
     */
    public function markStepCompleted(): void
    {
        $current = $this->currentStepClass();

        if (null !== $current && ! in_array($current, $this->completedSteps, true)) {
            $this->completedSteps[] = $current;
        }
    }

    /**
     * Advance the cursor to the next step and transition to in_progress.
     */
    public function advanceStep(): void
    {
        $this->transitionTo(WorkflowStatus::InProgress);
        $this->stepIndex++;
        $this->attempts = 0;
        $this->wakeAt = null;
    }

    /**
     * Jump the cursor to a specific step index and transition to in_progress.
     */
    public function jumpToStep(int $stepIndex): void
    {
        $this->transitionTo(WorkflowStatus::InProgress);
        $this->stepIndex = $stepIndex;
        $this->attempts = 0;
        $this->wakeAt = null;
    }

    /**
     * Sleep until the given time before running the current step.
     */
    public function sleepUntil(CarbonInterface $wakeAt): void
    {
        $this->transitionTo(WorkflowStatus::Sleeping);
        $this->attempts = 0;
        $this->wakeAt = $wakeAt;
    }

    /**
     * Wake a sleeping instance so its current step can run.
     */
    public function wake(): void
    {
        $this->transitionTo(WorkflowStatus::InProgress);
        $this->wakeAt = null;
    }

    /**
     * Record a failed attempt at the current step without advancing.
     */
    public function recordFailedAttempt(): void
    {
        $this->transitionTo(WorkflowStatus::InProgress);
        $this->attempts++;
    }

    /**
     * Mark the workflow as waiting for a signal.
     */
    public function awaitSignal(string $signal): void
    {
        $this->transitionTo(WorkflowStatus::Awaiting);
        $this->attempts = 0;
        $this->awaitingSignal = $signal;
    }

    /**
     * Merge an external signal into the context and clear the awaited signal.
     *
     * @param  array<string, mixed>  $signalData
     */
    public function receiveSignal(array $signalData): void
    {
        $this->mergeContext($signalData);
        $this->awaitingSignal = null;
    }

    /**
     * Mark the workflow as completed.
     */
    public function complete(): void
    {
        $this->transitionTo(WorkflowStatus::Completed);
        $this->awaitingSignal = null;
        $this->wakeAt = null;
        $this->completedAt = Carbon::now();
    }

    /**
     * Begin saga compensation after a failure.
     */
    public function beginCompensation(string $reason): void
    {
        $this->transitionTo(WorkflowStatus::Compensating);
        $this->failedReason = $reason;
        $this->awaitingSignal = null;
        $this->wakeAt = null;
    }

    /**
     * Mark the workflow as failed.
     */
    public function fail(string $reason): void
    {
        $this->transitionTo(WorkflowStatus::Failed);
        $this->failedReason = $reason;
        $this->awaitingSignal = null;
        $this->wakeAt = null;
        $this->failedAt = Carbon::now();
    }

    /**
     * Re-arm a failed instance so its current step can be retried.
     */
    public function retry(): void
    {
        $this->transitionTo(WorkflowStatus::InProgress);
        $this->attempts = 0;
        $this->failedReason = null;
        $this->failedAt = null;
        $this->wakeAt = null;
    }

    /**
     * Validate a status change through the state machine, then apply it. Throws
     * JustSteveKing\WorkflowEngine\StateMachine\Exceptions\InvalidTransitionException if the
     * move is not legal from the current status.
     */
    private function transitionTo(WorkflowStatus $target): void
    {
        $machine = new StateMachine(
            machine: new WorkflowStateMachine($this->status),
        );

        $machine->transition(WorkflowStateMachine::transitionFor($target));

        $this->status = $target;
    }
}
