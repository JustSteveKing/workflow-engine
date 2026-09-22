<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\TimeoutRoutingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/** Routes its timeout at a step that is not in the sequence. */
final class BadTimeoutRouteStep implements TimeoutRoutingStep, WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::await('payment_received');
    }

    public function timeoutTo(): string
    {
        return GotoTargetStep::class;
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
