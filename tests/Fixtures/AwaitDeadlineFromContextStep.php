<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Contracts\ContextualTimeout;
use JustSteveKing\WorkflowEngine\Contracts\TimeoutRoutingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * Waits for payment until this invoice's own due date, then routes to the
 * reminder rather than failing — the shape a chasing cadence actually needs.
 */
final class AwaitDeadlineFromContextStep implements ContextualTimeout, TimeoutRoutingStep, WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::await('payment_received');
    }

    public function timeoutSecondsFor(WorkflowContext $context): ?int
    {
        $dueAt = $context->get('due_at');

        if (! is_string($dueAt)) {
            return null;
        }

        return (int) Carbon::now()->diffInSeconds(Carbon::parse($dueAt), false);
    }

    public function timeoutTo(): string
    {
        return SendReminderStep::class;
    }

    public function timeoutSeconds(): ?int
    {
        // Never reached: timeoutSecondsFor() takes precedence.
        return 60;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
