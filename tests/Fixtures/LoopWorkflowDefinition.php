<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class LoopWorkflowDefinition implements WorkflowDefinition
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
