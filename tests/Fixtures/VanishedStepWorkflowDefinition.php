<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

/**
 * Stands in for an instance whose pinned sequence names a step class that a
 * later deploy removed. The definition itself is only used to start the
 * instance; the test rewrites step_sequence to the missing class.
 */
final class VanishedStepWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_vanished_step_workflow';
    }

    public function steps(): array
    {
        return [
            AwaitPaymentWithReminderStep::class,
            SendReminderStep::class,
        ];
    }
}
