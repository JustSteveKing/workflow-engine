<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class ContextualTimeoutWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_contextual_timeout_workflow';
    }

    public function steps(): array
    {
        return [
            AwaitDeadlineFromContextStep::class,
            SendReminderStep::class,
        ];
    }
}
