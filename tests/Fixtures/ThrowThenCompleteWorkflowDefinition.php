<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class ThrowThenCompleteWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_throw_then_complete_workflow';
    }

    public function steps(): array
    {
        return [
            ThrowThenCompleteStep::class,
        ];
    }
}
