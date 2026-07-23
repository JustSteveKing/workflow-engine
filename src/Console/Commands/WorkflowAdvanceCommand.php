<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;

final class WorkflowAdvanceCommand extends WorkflowCommand
{
    protected $signature = 'workflow:advance {id : The workflow instance id}';

    protected $description = 'Advance a workflow instance one step synchronously (useful without a running worker).';

    public function handle(WorkflowEngine $engine): int
    {
        $id = $this->stringArgument('id');

        try {
            $engine->advance($id);
        } catch (WorkflowNotFoundException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Advanced instance {$id}.");

        return self::SUCCESS;
    }
}
