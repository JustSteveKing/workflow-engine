<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Exceptions\InvalidSignalException;
use JustSteveKing\WorkflowEngine\Exceptions\WorkflowNotFoundException;

final class WorkflowSignalCommand extends WorkflowCommand
{
    protected $signature = 'workflow:signal
        {id : The workflow instance id}
        {signal : The signal name to deliver}
        {--data= : JSON object merged into the context}
        {--by= : Who delivered the signal (provenance label)}';

    protected $description = 'Deliver a signal to a workflow instance.';

    public function handle(WorkflowEngine $engine): int
    {
        $data = $this->jsonObjectOption('data');

        if (false === $data) {
            $this->error('The --data option must be a valid JSON object.');

            return self::FAILURE;
        }

        $id = $this->stringArgument('id');
        $signal = $this->stringArgument('signal');

        try {
            $engine->signal($id, $signal, $data, $this->stringOption('by'));
        } catch (WorkflowNotFoundException|InvalidSignalException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Signal '{$signal}' delivered to instance {$id}.");

        return self::SUCCESS;
    }
}
