<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class GotoSkippedStep implements WorkflowStepContract
{
    public static int $executed = 0;

    public static function reset(): void
    {
        self::$executed = 0;
    }

    public function execute(WorkflowContext $context): StepResult
    {
        self::$executed++;

        return StepResult::complete(['skipped_ran' => true]);
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
