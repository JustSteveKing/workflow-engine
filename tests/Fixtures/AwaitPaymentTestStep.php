<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class AwaitPaymentTestStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::await('payment_succeeded');
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}
