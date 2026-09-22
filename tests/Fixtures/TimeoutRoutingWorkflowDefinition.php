<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class TimeoutRoutingWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_timeout_routing_workflow';
    }

    public function steps(): array
    {
        return [
            AwaitPaymentWithReminderStep::class,
            SendReminderStep::class,
        ];
    }
}
