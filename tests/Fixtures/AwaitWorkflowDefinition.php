<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class AwaitWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_await_workflow';
    }

    public function steps(): array
    {
        return [
            AwaitPaymentTestStep::class,
            ReadSignalDataTestStep::class,
        ];
    }
}
