<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class FailingCompensationWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_failing_compensation_workflow';
    }

    public function steps(): array
    {
        return [
            FailingCompensationStep::class,
            FailingStep::class,
        ];
    }
}
