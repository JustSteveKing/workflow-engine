<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class BadGotoStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        // GotoTargetStep is not part of this workflow's step sequence.
        return StepResult::goto(GotoTargetStep::class);
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
