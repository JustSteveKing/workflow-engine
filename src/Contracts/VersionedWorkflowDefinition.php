<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

/**
 * A workflow definition that declares an explicit version. The version is
 * snapshotted onto each instance at start time alongside the step sequence, so
 * in-flight instances keep running against the definition they started with
 * even after the definition changes.
 */
interface VersionedWorkflowDefinition
{
    /**
     * The current version of this workflow definition.
     */
    public function version(): int;
}
