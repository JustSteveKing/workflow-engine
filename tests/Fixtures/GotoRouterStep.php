<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class GotoRouterStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        // Route straight to the target, skipping the step in between.
        return StepResult::goto(GotoTargetStep::class, ['routed' => true]);
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
