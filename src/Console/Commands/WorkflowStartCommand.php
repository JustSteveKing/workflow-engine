<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use InvalidArgumentException;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;

final class WorkflowStartCommand extends WorkflowCommand
{
    protected $signature = 'workflow:start
        {workflow : The registered workflow name}
        {aggregateId : The aggregate id the run is about}
        {aggregateType : The aggregate type}
        {--context= : JSON object of initial context}';

    protected $description = 'Start a new workflow instance.';

    public function handle(WorkflowEngine $engine): int
    {
        $context = $this->jsonObjectOption('context');

        if (false === $context) {
            $this->error('The --context option must be a valid JSON object.');

            return self::FAILURE;
        }

        $workflow = $this->stringArgument('workflow');

        try {
            $instance = $engine->start(
                workflowName: $workflow,
                aggregateId: $this->stringArgument('aggregateId'),
                aggregateType: $this->stringArgument('aggregateType'),
                initialContext: $context,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Started '{$workflow}' as instance {$instance->id}.");

        return self::SUCCESS;
    }
}
