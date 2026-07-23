<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

final class ReadSignalDataTestStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        return StepResult::complete([
            'payment_id' => $context->get('payment_id'),
            'paid_amount' => $context->get('paid_amount'),
        ]);
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
