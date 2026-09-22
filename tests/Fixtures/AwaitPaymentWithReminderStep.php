<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\TimeoutRoutingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * Waits for payment, but treats silence as a deadline rather than a failure:
 * when the wait expires it continues from the reminder step.
 */
final class AwaitPaymentWithReminderStep implements TimeoutRoutingStep, WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::await('payment_received');
    }

    public function timeoutTo(): string
    {
        return SendReminderStep::class;
    }

    public function timeoutSeconds(): ?int
    {
        return 60;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
