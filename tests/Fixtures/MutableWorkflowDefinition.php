<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\VersionedWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

/**
 * A definition whose step list can change at runtime, to prove instances run
 * against the sequence snapshotted at start rather than the live definition.
 */
final class MutableWorkflowDefinition implements VersionedWorkflowDefinition, WorkflowDefinition
{
    public static bool $includeSecondStep = false;

    public static int $version = 1;

    public static function reset(): void
    {
        self::$includeSecondStep = false;
        self::$version = 1;
    }

    public static function name(): string
    {
        return 'test_mutable_workflow';
    }

    public function steps(): array
    {
        return self::$includeSecondStep
            ? [AutoCompleteTestStep::class, SecondAutoStep::class]
            : [AutoCompleteTestStep::class];
    }

    public function version(): int
    {
        return self::$version;
    }
}
