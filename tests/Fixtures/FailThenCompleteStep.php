<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class FailThenCompleteStep implements WorkflowStepContract
{
    public static int $executions = 0;

    public static function reset(): void
    {
        self::$executions = 0;
    }

    public function execute(WorkflowContext $context): StepResult
    {
        self::$executions++;

        // Fail on the first run, succeed once retried/resumed.
        if (1 === self::$executions) {
            return StepResult::fail('first attempt fails');
        }

        return StepResult::complete(['recovered' => true]);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 1; // no automatic retry; recovery is via engine->retry()
    }
}
