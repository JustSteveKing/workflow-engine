<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class BadTimeoutRouteWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_bad_timeout_route_workflow';
    }

    public function steps(): array
    {
        return [
            BadTimeoutRouteStep::class,
        ];
    }
}
