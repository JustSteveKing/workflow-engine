<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

/**
 * A step with side effects to undo, followed by a wait — the shape an operator
 * actually cancels: work has been done, and the signal releasing it never came.
 */
final class CancellableSagaWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_cancellable_saga_workflow';
    }

    public function steps(): array
    {
        return [
            CompensatingChargeStep::class,
            AwaitPaymentTestStep::class,
        ];
    }
}
