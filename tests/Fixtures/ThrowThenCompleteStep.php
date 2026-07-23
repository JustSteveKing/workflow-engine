<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;
use RuntimeException;

final class ThrowThenCompleteStep implements WorkflowStepContract
{
    public static int $executions = 0;

    public static function reset(): void
    {
        self::$executions = 0;
    }

    public function execute(WorkflowContext $context): StepResult
    {
        self::$executions++;

        // Throw on the first attempt, succeed on the retry.
        if (1 === self::$executions) {
            throw new RuntimeException('boom once');
        }

        return StepResult::complete(['recovered' => true]);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 2;
    }
}
