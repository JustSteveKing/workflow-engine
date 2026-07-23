<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class LoopBackStep implements WorkflowStepContract
{
    public static int $passes = 0;

    public static function reset(): void
    {
        self::$passes = 0;
    }

    public function execute(WorkflowContext $context): StepResult
    {
        self::$passes++;

        // Loop back to the start once, then finish.
        if (1 === self::$passes) {
            return StepResult::goto(LoopStartStep::class);
        }

        return StepResult::complete(['loop_done' => true]);
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
