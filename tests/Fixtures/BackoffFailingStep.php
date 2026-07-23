<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\HasRetryBackoff;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class BackoffFailingStep implements HasRetryBackoff, WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        return StepResult::fail('always fails');
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 3;
    }

    public function retryBackoff(int $attempt): int
    {
        return $attempt * 10;
    }
}
