<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\CompensatingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;
use RuntimeException;

/**
 * A step that completes but throws while compensating, to exercise the
 * compensation-failure path.
 */
final class FailingCompensationStep implements CompensatingStep, WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        return StepResult::complete(['charged' => true]);
    }

    public function compensate(WorkflowContext $context): void
    {
        throw new RuntimeException('refund failed');
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
