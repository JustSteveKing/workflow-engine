<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use JustSteveKing\WorkflowEngine\Contracts\CompensatingStep;
use JustSteveKing\WorkflowEngine\Contracts\ContextualTimeout;
use JustSteveKing\WorkflowEngine\Contracts\CustomRetryBackoff;
use JustSteveKing\WorkflowEngine\Contracts\VersionedWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Contracts\TimeoutRoutingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowRepository;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Events\SignalReceived;
use JustSteveKing\WorkflowEngine\Events\StepCompensationFailed;
use JustSteveKing\WorkflowEngine\Events\StepCompleted;
use JustSteveKing\WorkflowEngine\Events\StepFailed;
use JustSteveKing\WorkflowEngine\Events\StepTimedOut;
use JustSteveKing\WorkflowEngine\Events\WorkflowAwaitingSignal;
use JustSteveKing\WorkflowEngine\Events\WorkflowCancelled;
use JustSteveKing\WorkflowEngine\Events\WorkflowCompensating;
use JustSteveKing\WorkflowEngine\Events\WorkflowCompleted;
use JustSteveKing\WorkflowEngine\Events\WorkflowFailed;
use JustSteveKing\WorkflowEngine\Events\WorkflowRetried;
use JustSteveKing\WorkflowEngine\Events\WorkflowSlept;
use JustSteveKing\WorkflowEngine\Events\WorkflowStarted;
use JustSteveKing\WorkflowEngine\Exceptions\InvalidSignalException;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Jobs\CompensateWorkflow;
use JustSteveKing\WorkflowEngine\Jobs\TimeoutWorkflowStep;
use RuntimeException;
use Throwable;

final readonly class WorkflowEngine
{
    public function __construct(
        private WorkflowRepository $repository,
        private WorkflowRegistry $registry,
        private Container $container,
        private Dispatcher $events,
        private BusDispatcher $bus,
        private Repository $config,
    ) {}

    /**
     * Start a new workflow instance. The definition's ordered step list and
     * version are snapshotted onto the instance, so it keeps running against the
     * definition it started with even if the definition later changes.
     *
     * @param  array<string, mixed>  $initialContext
     */
    public function start(
        string $workflowName,
        string $aggregateId,
        string $aggregateType,
        array $initialContext = [],
    ): WorkflowInstance {
        if (! $this->registry->has($workflowName)) {
            throw new InvalidArgumentException("Workflow '{$workflowName}' not found in registry.");
        }

        $definitionClass = $this->registry->get($workflowName);
        $definition = $this->container->make($definitionClass);
        if (! $definition instanceof WorkflowDefinition) {
            throw new RuntimeException("Workflow definition '{$definitionClass}' must implement WorkflowDefinition.");
        }

        $stepSequence = array_values($definition->steps());
        $version = $definition instanceof VersionedWorkflowDefinition ? $definition->version() : 1;

        $instance = $this->repository->create(
            $workflowName,
            $definitionClass,
            $version,
            $stepSequence,
            $aggregateId,
            $aggregateType,
            $initialContext,
        );

        $this->events->dispatch(new WorkflowStarted($instance->id, $workflowName));

        return $instance;
    }

    /**
     * Advance a workflow to the next step.
     */
    public function advance(int|string $instanceId): void
    {
        $this->runDeferred($this->repository->transaction(function () use ($instanceId): array {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            /** @var list<Closure> $deferred */
            $deferred = [];

            // A sleeping instance only proceeds once its wake time has passed;
            // an early/stray advance before then is ignored.
            if ($instance->isSleeping()) {
                if (null !== $instance->wakeAt && $instance->wakeAt->isFuture()) {
                    return $deferred;
                }

                $instance->wake();
            } elseif (! $instance->isRunning()) {
                // Stale advance jobs can race with terminal/awaiting states; ignore safely.
                return $deferred;
            }

            $stepClass = $instance->currentStepClass();

            // Cursor past the end of the sequence: the workflow is complete.
            if (null === $stepClass) {
                $instance->complete();
                $this->repository->save($instance);
                $this->repository->discardBufferedSignals($instance->id);
                $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowCompleted($instance->id)));

                return $deferred;
            }

            $step = $this->container->make($stepClass);
            if (! $step instanceof WorkflowStep) {
                throw new RuntimeException("Workflow step '{$stepClass}' must implement WorkflowStep.");
            }

            // An uncaught exception must not strand the instance in_progress; it
            // is treated as a step failure (subject to retry/compensation).
            try {
                $result = $step->handle($instance->context);
            } catch (Throwable $exception) {
                return $this->applyStepFailure($instance, $step, $stepClass, $exception->getMessage(), $deferred);
            }

            if ($result->isCompleted()) {
                return $this->moveForward($instance, $stepClass, $result->contextUpdates, $deferred);
            }

            if ($result->isGoto()) {
                $target = $result->gotoStep;
                $targetIndex = null === $target ? null : $instance->indexOfStep($target);
                if (null === $targetIndex) {
                    throw new RuntimeException("Step '{$stepClass}' tried to goto '{$target}', which is not part of the workflow's step sequence.");
                }

                $instance->mergeContext($result->contextUpdates);
                $fromIndex = $instance->stepIndex;
                $instance->markStepCompleted();
                $instance->jumpToStep($targetIndex);
                $this->repository->save($instance);
                $this->defer($deferred, fn() => $this->events->dispatch(new StepCompleted($instance->id, $stepClass, $fromIndex)));
                $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id));

                return $deferred;
            }

            if ($result->isSleeping()) {
                $instance->mergeContext($result->contextUpdates);
                $fromIndex = $instance->stepIndex;
                $instance->markStepCompleted();
                $instance->advanceStep();
                $wakeAt = Carbon::now()->addSeconds(max(0, (int) $result->sleepSeconds));
                $instance->sleepUntil($wakeAt);
                $this->repository->save($instance);
                $this->defer($deferred, fn() => $this->events->dispatch(new StepCompleted($instance->id, $stepClass, $fromIndex)));
                $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowSlept($instance->id, $wakeAt)));
                $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id, $wakeAt));

                return $deferred;
            }

            if ($result->isAwaiting()) {
                $instance->mergeContext($result->contextUpdates);
                $signal = $result->awaitingSignal;
                if (null === $signal) {
                    throw new RuntimeException("Step '{$stepClass}' returned await without a signal name.");
                }

                // Early-signal race: if a matching signal was already delivered
                // (buffered), consume it now instead of parking.
                if ($this->bufferEarlySignals()) {
                    $buffered = $this->repository->pullBufferedSignal($instance->id, $signal);
                    if (null !== $buffered) {
                        $instance->receiveSignal($buffered->data);
                        $this->defer($deferred, fn() => $this->events->dispatch(new SignalReceived($instance->id, $signal, $buffered->data, $buffered->deliveredBy, false)));

                        return $this->moveForwardFromAwait($instance, $stepClass, $deferred);
                    }
                }

                $instance->awaitSignal($signal);
                $this->repository->save($instance);
                $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowAwaitingSignal($instance->id, $signal)));

                $timeout = $this->timeoutSecondsFor($step, $instance->context);
                if (null !== $timeout) {
                    $stepIndex = $instance->stepIndex;
                    $this->defer($deferred, fn() => $this->dispatchTimeoutJob($instance->id, $stepIndex, $timeout));
                }

                return $deferred;
            }

            // Step failed.
            return $this->applyStepFailure($instance, $step, $stepClass, $result->failureReason ?? 'Step failed without a reason.', $deferred);
        }));
    }

    /**
     * Deliver a signal to a paused workflow instance.
     *
     * @param  array<string, mixed>  $signalData
     * @param  string|null  $consumingStep  The step expected to consume this signal. When given, the
     *                                      delivery is refused unless that step is in the instance's
     *                                      pinned sequence and still at or ahead of the cursor, and
     *                                      unless nothing of this name is already buffered.
     */
    public function signal(
        int|string $instanceId,
        string $signal,
        array $signalData = [],
        ?string $deliveredBy = null,
        ?string $consumingStep = null,
    ): void {
        $this->runDeferred($this->repository->transaction(function () use ($instanceId, $signal, $signalData, $deliveredBy, $consumingStep): array {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            /** @var list<Closure> $deferred */
            $deferred = [];

            /*
             * Buffering means this method stops refusing anything short of a
             * terminal instance, so a caller naming the step that will consume
             * the signal gets that checked instead: the step has to be in this
             * instance's pinned sequence, and the cursor must not have passed
             * it. Without it a signal aimed at the wrong workflow, or at a
             * decision already taken, is held rather than rejected and the
             * caller is told it landed.
             */
            if (null !== $consumingStep) {
                $targetIndex = $instance->indexOfStep($consumingStep);

                if (null === $targetIndex || $targetIndex < $instance->stepIndex) {
                    throw new InvalidSignalException($instanceId, $signal, $instance->awaitingSignal);
                }
            }

            if ($instance->isAwaiting() && $instance->awaitingSignal === $signal) {
                $this->repository->recordSignal($instanceId, $signal, $signalData, $deliveredBy, consumed: true);
                $stepClass = $instance->currentStepClass();
                $instance->receiveSignal($signalData);
                $this->defer($deferred, fn() => $this->events->dispatch(new SignalReceived($instance->id, $signal, $signalData, $deliveredBy, false)));

                return $this->moveForwardFromAwait($instance, $stepClass, $deferred);
            }

            /*
             * A second signal of the same name, held for the same await, can
             * never be consumed: the engine takes the oldest and discards the
             * rest at termination. Where two of them carry opposite verdicts,
             * silently keeping the first is worse than refusing the second,
             * so a caller that named its consuming step is told.
             */
            if (null !== $consumingStep && $this->repository->hasBufferedSignal($instanceId, $signal)) {
                throw new InvalidSignalException($instanceId, $signal, $instance->awaitingSignal);
            }

            // Not awaiting this signal (yet). Buffer it so a later step can
            // consume it, rather than losing it.
            if (! $instance->status->isTerminal() && $this->bufferEarlySignals()) {
                $this->repository->recordSignal($instanceId, $signal, $signalData, $deliveredBy, consumed: false);
                $this->defer($deferred, fn() => $this->events->dispatch(new SignalReceived($instance->id, $signal, $signalData, $deliveredBy, true)));

                return $deferred;
            }

            throw new InvalidSignalException($instanceId, $signal, $instance->awaitingSignal);
        }));
    }

    /**
     * Mark an awaiting workflow step as timed out.
     *
     * @param  int  $stepIndex  The step this timeout was scheduled for; guards against stale timeouts.
     * @param  array<string, mixed>  $signalData
     */
    public function timeout(
        int|string $instanceId,
        int $stepIndex,
        array $signalData = [],
        ?string $deliveredBy = 'system',
    ): void {
        $this->runDeferred($this->repository->transaction(function () use ($instanceId, $stepIndex, $signalData, $deliveredBy): array {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            /** @var list<Closure> $deferred */
            $deferred = [];

            // Timeout jobs can race with real signals and outlive the step they
            // guard. Ignore unless still awaiting the exact step it was scheduled for.
            if (! $instance->isAwaiting() || $instance->stepIndex !== $stepIndex) {
                return $deferred;
            }

            $awaitingSignal = (string) $instance->awaitingSignal;

            $this->repository->recordSignal(
                $instanceId,
                'timeout',
                array_merge(['awaiting_signal' => $awaitingSignal], $signalData),
                $deliveredBy,
                consumed: true,
            );

            /*
             * A step may treat its timeout as a deadline rather than a failure
             * and name where to continue from. The jump is the one goto uses,
             * so the step it lands on runs as it would on any other entry.
             */
            $stepClass = $instance->currentStepClass();
            $route = null === $stepClass ? null : $this->timeoutRouteFor($stepClass);

            if (null !== $route) {
                $targetIndex = $instance->indexOfStep($route);

                if (null === $targetIndex) {
                    $reason = "Step '{$stepClass}' timed out and routes to '{$route}', which is not part of the workflow's step sequence.";
                    $this->defer($deferred, fn() => $this->events->dispatch(new StepTimedOut($instance->id, $awaitingSignal, $stepIndex)));

                    return $this->terminateWithFailure($instance, $reason, $deferred);
                }

                $instance->markStepCompleted();
                $instance->jumpToStep($targetIndex);
                $this->repository->save($instance);
                $this->defer($deferred, fn() => $this->events->dispatch(new StepTimedOut($instance->id, $awaitingSignal, $stepIndex, $route)));
                $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id));

                return $deferred;
            }

            $reason = "Step timed out while waiting for signal '{$awaitingSignal}'.";
            $this->defer($deferred, fn() => $this->events->dispatch(new StepTimedOut($instance->id, $awaitingSignal, $stepIndex)));

            return $this->terminateWithFailure($instance, $reason, $deferred);
        }));
    }

    /**
     * Stop a workflow instance that is not going to finish on its own.
     *
     * The operator escape hatch for an instance awaiting a signal that will
     * never arrive — a webhook that was never sent, an approval nobody is
     * going to give — where the alternative is faking the signal and letting
     * the workflow act on a decision no one made.
     *
     * It terminates through the same path as any other failure, so a workflow
     * with completed compensating steps rolls those back rather than simply
     * stopping: cancelling a half-finished process usually means undoing what
     * it already did. Cancelling something already finished does nothing.
     */
    public function cancel(int|string $instanceId, string $reason, ?string $cancelledBy = 'system'): void
    {
        $this->runDeferred($this->repository->transaction(function () use ($instanceId, $reason, $cancelledBy): array {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            /** @var list<Closure> $deferred */
            $deferred = [];

            if ($instance->status->isTerminal()) {
                return $deferred;
            }

            /*
             * Fail::from() does not accept Sleeping, and advance() is what
             * normally wakes an instance. Cancelling has to do it here, or the
             * escape hatch throws on exactly the instance an operator is most
             * likely to be stopping.
             */
            if ($instance->isSleeping()) {
                $instance->wake();
            }

            $this->repository->recordSignal(
                $instanceId,
                'cancelled',
                ['reason' => $reason],
                $cancelledBy,
                consumed: true,
            );

            $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowCancelled($instance->id, $reason, $cancelledBy)));

            return $this->terminateWithFailure($instance, "Cancelled: {$reason}", $deferred);
        }));
    }

    /**
     * Re-arm a failed workflow instance so its current step is attempted again.
     */
    public function retry(int|string $instanceId): void
    {
        $this->runDeferred($this->repository->transaction(function () use ($instanceId): array {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            if (! $instance->isFailed()) {
                throw new RuntimeException("Workflow instance {$instanceId} cannot be retried because it is not failed.");
            }

            $instance->retry();
            $this->repository->save($instance);

            /** @var list<Closure> $deferred */
            $deferred = [];
            $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowRetried($instance->id, $instance->stepIndex)));
            $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id));

            return $deferred;
        }));
    }

    /**
     * Run saga compensation for a failing instance, then fail it.
     *
     * Compensation callbacks perform external side effects (refunds, API calls),
     * so they run OUTSIDE the database transaction and row lock — the instance is
     * loaded and the plan captured under a short lock, the callbacks run
     * unlocked, and a second short lock records the terminal failure.
     */
    public function compensate(int|string $instanceId): void
    {
        // 1) Capture the compensation plan under a short lock (no side effects).
        $context = $this->repository->transaction(function () use ($instanceId): ?WorkflowContext {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            // Stale compensate jobs should be ignored.
            return $instance->isCompensating() ? $instance->context : null;
        });

        if (null === $context) {
            return;
        }

        $instance = $this->repository->findById($instanceId);
        if (null === $instance || ! $instance->isCompensating()) {
            return;
        }

        // 2) Run compensations in reverse, unlocked. Collect failures to report.
        /** @var list<Closure> $deferred */
        $deferred = [];

        foreach (array_reverse($instance->completedSteps) as $stepClass) {
            if (! is_a($stepClass, CompensatingStep::class, true)) {
                continue;
            }

            $step = $this->container->make($stepClass);
            if (! $step instanceof CompensatingStep) {
                continue;
            }

            try {
                $step->compensate($context);
            } catch (Throwable $exception) {
                $message = $exception->getMessage();
                $this->defer($deferred, fn() => $this->events->dispatch(new StepCompensationFailed($instanceId, $stepClass, $message)));
            }
        }

        // 3) Record the terminal failure under a short lock.
        $this->runDeferred($this->repository->transaction(function () use ($instanceId, $deferred): array {
            $instance = $this->repository->findById($instanceId, lock: true);
            if (null === $instance) {
                throw new WorkflowNotFoundException($instanceId);
            }

            // A concurrent run may already have finalized it.
            if (! $instance->isCompensating()) {
                return [];
            }

            $reason = $instance->failedReason ?? 'Workflow failed.';
            $instance->fail($reason);
            $this->repository->save($instance);
            $this->repository->discardBufferedSignals($instance->id);
            $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowFailed($instance->id, $reason)));

            return $deferred;
        }));
    }

    /**
     * Complete the current step, advance the cursor, and queue the next advance.
     *
     * @param  class-string  $stepClass
     * @param  array<string, mixed>  $contextUpdates
     * @param  list<Closure>  $deferred
     * @return list<Closure>
     */
    private function moveForward(WorkflowInstance $instance, string $stepClass, array $contextUpdates, array $deferred): array
    {
        $instance->mergeContext($contextUpdates);
        $fromIndex = $instance->stepIndex;
        $instance->markStepCompleted();
        $instance->advanceStep();
        $this->repository->save($instance);
        $this->defer($deferred, fn() => $this->events->dispatch(new StepCompleted($instance->id, $stepClass, $fromIndex)));
        $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id));

        return $deferred;
    }

    /**
     * Advance past a step that was awaiting a signal now delivered/consumed.
     *
     * @param  class-string|null  $stepClass
     * @param  list<Closure>  $deferred
     * @return list<Closure>
     */
    private function moveForwardFromAwait(WorkflowInstance $instance, ?string $stepClass, array $deferred): array
    {
        $fromIndex = $instance->stepIndex;
        $instance->markStepCompleted();
        $instance->advanceStep();
        $this->repository->save($instance);

        if (null !== $stepClass) {
            $this->defer($deferred, fn() => $this->events->dispatch(new StepCompleted($instance->id, $stepClass, $fromIndex)));
        }

        $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id));

        return $deferred;
    }

    /**
     * Handle a failed step: retry it while attempts remain, otherwise begin
     * compensation or fail the workflow.
     *
     * @param  class-string  $stepClass
     * @param  list<Closure>  $deferred
     * @return list<Closure>
     */
    private function applyStepFailure(WorkflowInstance $instance, WorkflowStep $step, string $stepClass, string $reason, array $deferred): array
    {
        $attempt = $instance->attempts + 1;

        if ($attempt < $step->maxAttempts()) {
            $instance->recordFailedAttempt();
            $this->repository->save($instance);
            $backoff = $this->retryBackoff($step, $attempt);
            $this->defer($deferred, fn() => $this->events->dispatch(new StepFailed($instance->id, $stepClass, $reason, $attempt, true)));
            $this->defer($deferred, fn() => $this->dispatchAdvanceJob($instance->id, $backoff));

            return $deferred;
        }

        $this->defer($deferred, fn() => $this->events->dispatch(new StepFailed($instance->id, $stepClass, $reason, $attempt, false)));

        return $this->terminateWithFailure($instance, $reason, $deferred);
    }

    /**
     * Either begin compensation (if any completed step can compensate) or fail
     * the workflow outright.
     *
     * @param  list<Closure>  $deferred
     * @return list<Closure>
     */
    private function terminateWithFailure(WorkflowInstance $instance, string $reason, array $deferred): array
    {
        if ($this->hasCompensatableSteps($instance)) {
            $instance->beginCompensation($reason);
            $this->repository->save($instance);
            $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowCompensating($instance->id, $reason)));
            $this->defer($deferred, fn() => $this->dispatchCompensateJob($instance->id));

            return $deferred;
        }

        $instance->fail($reason);
        $this->repository->save($instance);
        $this->repository->discardBufferedSignals($instance->id);
        $this->defer($deferred, fn() => $this->events->dispatch(new WorkflowFailed($instance->id, $reason)));

        return $deferred;
    }

    /**
     * How long this step waits before timing out, for the instance in front of
     * it. A deadline already past is clamped to zero so the timeout fires on
     * the next pass rather than being scheduled into history.
     */
    private function timeoutSecondsFor(WorkflowStep $step, WorkflowContext $context): ?int
    {
        $seconds = $step instanceof ContextualTimeout
            ? $step->timeoutSecondsFor($context)
            : $step->timeoutSeconds();

        return null === $seconds ? null : max(0, $seconds);
    }

    /**
     * The step a timed-out step routes to, or null when its timeout is fatal.
     *
     * @param  class-string  $stepClass
     * @return class-string|null
     */
    private function timeoutRouteFor(string $stepClass): ?string
    {
        /*
         * Checked statically before resolving, as hasCompensatableSteps() does.
         * A pinned step class can outlive the deploy that removed it — that is
         * what pinning the sequence is for — and make() on a missing class
         * would throw out of the surrounding transaction, turning a clean
         * timeout failure into a rolled-back job that retries into the failed
         * queue. is_a() answers false for it instead, and the timeout falls
         * through to failing the instance with a reason. It also keeps the
         * step's dependencies out of the row lock for every step that does not
         * route its timeout.
         */
        if (! is_a($stepClass, TimeoutRoutingStep::class, true)) {
            return null;
        }

        $step = $this->container->make($stepClass);

        return $step instanceof TimeoutRoutingStep ? $step->timeoutTo() : null;
    }

    private function hasCompensatableSteps(WorkflowInstance $instance): bool
    {
        foreach ($instance->completedSteps as $stepClass) {
            if (is_a($stepClass, CompensatingStep::class, true)) {
                return true;
            }
        }

        return false;
    }

    private function retryBackoff(WorkflowStep $step, int $attempt): int
    {
        if ($step instanceof CustomRetryBackoff) {
            return max(0, $step->retryBackoff($attempt));
        }

        $base = $this->intConfig('workflow-engine.retry.backoff', 5);
        if ($base <= 0) {
            return 0;
        }

        $max = $this->intConfig('workflow-engine.retry.max_backoff', 300);
        $delay = $base * (2 ** ($attempt - 1));

        return (int) min($delay, $max);
    }

    private function bufferEarlySignals(): bool
    {
        return (bool) $this->config->get('workflow-engine.signals.buffer_early', true);
    }

    private function intConfig(string $key, int $default): int
    {
        $value = $this->config->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Push a deferred side effect (event dispatch or queue dispatch) to run
     * after the transaction commits.
     *
     * @param  list<Closure>  $deferred
     */
    private function defer(array &$deferred, Closure $action): void
    {
        $deferred[] = $action;
    }

    /**
     * @param  list<Closure>  $deferred
     */
    private function runDeferred(array $deferred): void
    {
        foreach ($deferred as $action) {
            $action();
        }
    }

    private function dispatchAdvanceJob(int|string $instanceId, DateTimeInterface|int|null $delay = null): mixed
    {
        $job = new AdvanceWorkflow($instanceId);
        $this->onWorkflowQueue($job);

        if ($delay instanceof DateTimeInterface || (is_int($delay) && $delay > 0)) {
            $job->delay($delay);
        }

        return $this->bus->dispatch($job);
    }

    private function dispatchTimeoutJob(int|string $instanceId, int $stepIndex, int $delaySeconds): mixed
    {
        $job = new TimeoutWorkflowStep($instanceId, $stepIndex);
        $this->onWorkflowQueue($job);
        $job->delay(Carbon::now()->addSeconds($delaySeconds));

        return $this->bus->dispatch($job);
    }

    private function dispatchCompensateJob(int|string $instanceId): mixed
    {
        $job = new CompensateWorkflow($instanceId);
        $this->onWorkflowQueue($job);

        return $this->bus->dispatch($job);
    }

    private function onWorkflowQueue(AdvanceWorkflow|CompensateWorkflow|TimeoutWorkflowStep $job): void
    {
        $connection = $this->config->get('workflow-engine.queue.connection');
        $queue = $this->config->get('workflow-engine.queue.name');

        if (is_string($connection)) {
            $job->onConnection($connection);
        }

        if (is_string($queue)) {
            $job->onQueue($queue);
        }
    }
}
