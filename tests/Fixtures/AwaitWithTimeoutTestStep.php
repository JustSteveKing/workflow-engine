<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class AwaitWithTimeoutTestStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        return StepResult::await('payment_confirmed');
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
