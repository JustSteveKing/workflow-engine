<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class LoopWorkflowDefinition implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'test_loop_workflow';
    }

    public function steps(): array
    {
        return [
            LoopStartStep::class,
            LoopBackStep::class,
        ];
    }
}
