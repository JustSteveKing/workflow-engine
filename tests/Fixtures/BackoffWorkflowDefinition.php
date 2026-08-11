<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class BackoffWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_backoff_workflow';
    }

    public function steps(): array
    {
        return [
            BackoffFailingStep::class,
        ];
    }
}
