<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class GotoWorkflowDefinition implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'test_goto_workflow';
    }

    public function steps(): array
    {
        return [
            GotoRouterStep::class,
            GotoSkippedStep::class,
            GotoTargetStep::class,
        ];
    }
}
