<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class ThrowingWorkflowDefinition implements WorkflowDefinition
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
