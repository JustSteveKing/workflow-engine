<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Exceptions;

use Exception;

final class InvalidSignalException extends Exception
{
    public function __construct(int|string $workflowInstanceId, string $signalName, ?string $expectedSignal)
    {
        $message = "Workflow instance {$workflowInstanceId} received signal '{$signalName}'";
        if (null !== $expectedSignal) {
            $message .= " but was waiting for '{$expectedSignal}'";
        }
        parent::__construct($message . '.');
    }
}
