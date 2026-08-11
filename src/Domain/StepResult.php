<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

final readonly class StepResult
{
    /**
     * @param  array<string, mixed>  $contextUpdates
     */
    private function __construct(
        public StepOutcome $outcome,
        public ?string $awaitingSignal = null,
        public array $contextUpdates = [],
        public ?string $failureReason = null,
        public ?string $gotoStep = null,
        public ?int $sleepSeconds = null,
    ) {}

    /**
     * Advance to the next step.
     *
     * @param  array<string, mixed>  $contextUpdates  Data to merge into the context for downstream steps
     */
    public static function complete(array $contextUpdates = []): self
    {
        return new self(
            outcome: StepOutcome::Completed,
            contextUpdates: $contextUpdates,
        );
    }

    /**
     * Pause and wait for an external signal.
     *
     * @param  string  $signal  The name of the signal to wait for
     * @param  array<string, mixed>  $contextUpdates  Data to merge into the context for future steps
     */
    public static function await(string $signal, array $contextUpdates = []): self
    {
        return new self(
            outcome: StepOutcome::Awaiting,
            awaitingSignal: $signal,
            contextUpdates: $contextUpdates,
        );
    }

    /**
     * Mark the step as failed and stop the workflow (subject to retries).
     *
     * @param  string  $reason  Why the step failed
     */
    public static function fail(string $reason): self
    {
        return new self(
            outcome: StepOutcome::Failed,
            failureReason: $reason,
        );
    }

    /**
     * Jump to another step in the workflow instead of advancing linearly.
     *
     * @param  class-string  $step  The step class to route to; must be part of the workflow's step sequence
     * @param  array<string, mixed>  $contextUpdates  Data to merge into the context
     */
    public static function goto(string $step, array $contextUpdates = []): self
    {
        return new self(
            outcome: StepOutcome::Goto,
            contextUpdates: $contextUpdates,
            gotoStep: $step,
        );
    }

    /**
     * Advance to the next step, but only after the given delay has elapsed.
     *
     * @param  int  $seconds  How long to sleep before the next step runs
     * @param  array<string, mixed>  $contextUpdates  Data to merge into the context for downstream steps
     */
    public static function sleep(int $seconds, array $contextUpdates = []): self
    {
        return new self(
            outcome: StepOutcome::Sleeping,
            contextUpdates: $contextUpdates,
            sleepSeconds: $seconds,
        );
    }

    public function isCompleted(): bool
    {
        return StepOutcome::Completed === $this->outcome;
    }

    public function isAwaiting(): bool
    {
        return StepOutcome::Awaiting === $this->outcome;
    }

    public function isFailed(): bool
    {
        return StepOutcome::Failed === $this->outcome;
    }

    public function isGoto(): bool
    {
        return StepOutcome::Goto === $this->outcome;
    }

    public function isSleeping(): bool
    {
        return StepOutcome::Sleeping === $this->outcome;
    }
}
