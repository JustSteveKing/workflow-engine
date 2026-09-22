<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;

/**
 * Stop an instance awaiting a signal that will never arrive, without faking
 * the signal and letting the workflow act on a decision nobody made.
 */
final class WorkflowCancelCommand extends WorkflowCommand
{
    protected $signature = 'workflow:cancel {id : The workflow instance to cancel} {--reason=Cancelled by operator} {--by=cli-operator}';

    protected $description = 'Stop a workflow instance that is not going to finish on its own.';

    public function handle(WorkflowEngine $engine): int
    {
        $id = $this->stringArgument('id');
        $reason = $this->stringOption('reason') ?? 'Cancelled by operator';

        try {
            $engine->cancel($id, $reason, $this->stringOption('by') ?? 'cli-operator');
        } catch (WorkflowNotFoundException) {
            $this->error("Workflow instance [{$id}] not found.");

            return self::FAILURE;
        }

        $this->info("Workflow instance [{$id}] cancelled.");

        return self::SUCCESS;
    }
}
