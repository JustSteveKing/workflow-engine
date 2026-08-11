<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class GotoWorkflowDefinition implements WorkflowDefinition
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
