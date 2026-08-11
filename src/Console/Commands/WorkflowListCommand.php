<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Console\Commands;

use JustSteveKing\WorkflowEngine\Contracts\VersionedWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;

final class WorkflowListCommand extends WorkflowCommand
{
    protected $signature = 'workflow:list';

    protected $description = 'List the registered workflow definitions.';

    public function handle(WorkflowRegistry $registry): int
    {
        $names = $registry->all();

        if ([] === $names) {
            $this->warn('No workflows are registered.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($names as $name) {
            $class = $registry->get($name);
            $definition = $this->laravel->make($class);

            $steps = $definition instanceof WorkflowDefinition ? count($definition->steps()) : 0;
            $version = $definition instanceof VersionedWorkflowDefinition ? $definition->version() : 1;

            $rows[] = [$name, $class, $version, $steps];
        }

        $this->table(['Name', 'Definition', 'Version', 'Steps'], $rows);

        return self::SUCCESS;
    }
}
