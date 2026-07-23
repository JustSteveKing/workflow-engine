<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

interface WorkflowStepContract
{
    /**
     * Execute the step and return its result.
     */
    public function execute(WorkflowContext $context): StepResult;

    /**
     * Return the number of seconds this step can run before timing out.
     * Return null for no timeout.
     */
    public function timeoutSeconds(): ?int;

    /**
     * Return the maximum number of times this step will be attempted before the
     * workflow is failed. A value of 1 means no retry; higher values retry the
     * step on failure until the attempt count is exhausted.
     */
    public function maxAttempts(): int;
}
