<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;

final class TwoStepAwaitTimeoutWorkflowDefinition implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'test_two_step_await_timeout_workflow';
    }

    public function steps(): array
    {
        return [
            // Step 0 awaits with a timeout; step 1 awaits a different signal with no timeout.
            AwaitWithTimeoutTestStep::class,
            AwaitPaymentTestStep::class,
        ];
    }
}
