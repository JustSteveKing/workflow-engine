<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class AwaitWithTimeoutWorkflowDefinition implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'test_await_with_timeout_workflow';
    }

    public function steps(): array
    {
        return [
            AwaitWithTimeoutTestStep::class,
        ];
    }
}
