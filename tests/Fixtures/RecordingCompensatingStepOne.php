<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\CompensatingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class RecordingCompensatingStepOne implements CompensatingStep, WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::complete(['step_one' => true]);
    }

    public function compensate(WorkflowContext $context): void
    {
        CompensationRecorder::record('one');
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
