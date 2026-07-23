<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Exceptions;

use Exception;

final class WorkflowNotFoundException extends Exception
{
    public function __construct(int|string $workflowInstanceId)
    {
        parent::__construct("Workflow instance {$workflowInstanceId} not found.");
    }
}
