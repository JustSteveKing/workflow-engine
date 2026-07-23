<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class RetryingTestStep implements WorkflowStepContract
{
    private static int $executionCount = 0;

    public static function reset(): void
    {
        self::$executionCount = 0;
    }

    public static function executionCount(): int
    {
        return self::$executionCount;
    }

    public function execute(WorkflowContext $context): StepResult
    {
        self::$executionCount++;

        // Fail the first two attempts, then succeed on the third.
        if (self::$executionCount < 3) {
            return StepResult::fail('transient failure');
        }

        return StepResult::complete(['retried_ok' => true]);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 3;
    }
}
