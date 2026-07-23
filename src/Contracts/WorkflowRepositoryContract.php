<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

use Closure;
use JustSteveKing\WorkflowEngine\Domain\BufferedSignal;
use JustSteveKing\WorkflowEngine\Domain\WorkflowInstance;

interface WorkflowRepositoryContract
{
    /**
     * Run the given callback inside a database transaction and return its result.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function transaction(Closure $callback): mixed;

    /**
     * Create and persist a new workflow instance.
     *
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
    ): WorkflowInstance;

    /**
     * Find a workflow instance by ID.
     *
     * @param  bool  $lock  Acquire a row-level lock for the duration of the surrounding transaction.
     */
    public function findById(int|string $id, bool $lock = false): ?WorkflowInstance;

    /**
     * Find all workflow instances waiting for a specific signal for a given aggregate.
     *
     * @return WorkflowInstance[]
     */
    public function findAwaitingSignal(
        string $signal,
        string $aggregateId,
        ?string $aggregateType = null,
    ): array;

    /**
     * Save a workflow instance.
     */
    public function save(WorkflowInstance $instance): void;

    /**
     * Record a signal delivery in the workflow signals log.
     *
     * @param  array<string, mixed>  $signalData
     * @param  bool  $consumed  Whether the signal was applied immediately (true) or buffered for a step that isn't awaiting it yet (false).
     */
    public function recordSignal(
        int|string $workflowInstanceId,
        string $signal,
        array $signalData = [],
        ?string $deliveredBy = null,
        bool $consumed = true,
    ): void;

    /**
     * Consume the earliest buffered (unconsumed) signal of the given name for an
     * instance, or null if none is buffered. Must be called within a
     * transaction; the row is locked and marked consumed.
     */
    public function pullBufferedSignal(int|string $workflowInstanceId, string $signal): ?BufferedSignal;

    /**
     * Discard any buffered (unconsumed) signals for an instance. Called when the
     * instance reaches a terminal state and can no longer consume them.
     */
    public function discardBufferedSignals(int|string $workflowInstanceId): void;
}
