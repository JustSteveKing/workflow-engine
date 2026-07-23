<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class CompensatingTimeoutWorkflowDefinition implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'test_compensating_timeout_workflow';
    }

    public function steps(): array
    {
        return [
            CompensatingChargeStep::class,
            AwaitWithTimeoutTestStep::class,
        ];
    }
}
