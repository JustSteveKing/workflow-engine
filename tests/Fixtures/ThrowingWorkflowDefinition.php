<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class ThrowingWorkflowDefinition implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'test_throwing_workflow';
    }

    public function steps(): array
    {
        return [
            ThrowingTestStep::class,
        ];
    }
}
