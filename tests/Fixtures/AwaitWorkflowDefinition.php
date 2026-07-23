<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class AwaitWorkflowDefinition implements WorkflowDefinitionContract
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
