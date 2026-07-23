<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class CountingAwaitTestStep implements WorkflowStepContract
{
    private static int $executionCount = 0;

    public static function resetExecutionCount(): void
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

        return StepResult::await('counting_signal');
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
