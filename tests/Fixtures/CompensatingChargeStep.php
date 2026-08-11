<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\CompensatingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class CompensatingChargeStep implements CompensatingStep, WorkflowStep
{
    public static int $compensated = 0;

    public static function reset(): void
    {
        self::$compensated = 0;
    }

    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::complete(['charged' => true]);
    }

    public function compensate(WorkflowContext $context): void
    {
        self::$compensated++;
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
