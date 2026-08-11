<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\CustomRetryBackoff;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class BackoffFailingStep implements CustomRetryBackoff, WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
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
