<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class SleepWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_sleep_workflow';
    }

    public function steps(): array
    {
        return [
            SleepStep::class,
            AutoCompleteTestStep::class,
        ];
    }
}
