<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class BadGotoWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_bad_goto_workflow';
    }

    public function steps(): array
    {
        return [
            BadGotoStep::class,
        ];
    }
}
