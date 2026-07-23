<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * A step that can undo its own side effects when a later step fails and the
 * workflow rolls back (saga compensation). Completed steps are compensated in
 * reverse order.
 */
interface CompensatingStep
{
    /**
     * Undo the work this step performed. Called during compensation with the
     * context as it stood when the workflow failed.
     */
    public function compensate(WorkflowContext $context): void;
}
