<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\CompensatingStep;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class RecordingCompensatingStepTwo implements CompensatingStep, WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        return StepResult::complete(['step_two' => true]);
    }

    public function compensate(WorkflowContext $context): void
    {
        CompensationRecorder::record('two');
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
