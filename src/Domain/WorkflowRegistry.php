<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

use InvalidArgumentException;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class WorkflowRegistry
{
    /** @var array<string, class-string<WorkflowDefinition>> */
    private array $definitions = [];

    /**
     * Register a workflow definition.
     *
     * @param  class-string<WorkflowDefinition>  $definitionClass
     */
    public function register(string $definitionClass): void
    {
        $name = $definitionClass::name();
        $this->definitions[$name] = $definitionClass;
    }

    /**
     * Get a workflow definition by name.
     *
     * @return class-string<WorkflowDefinition>
     */
    public function get(string $name): string
    {
        return $this->definitions[$name] ?? throw new InvalidArgumentException("Workflow definition '{$name}' not found in registry.");
    }

    /**
     * Check if a workflow is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    /**
     * Get all registered workflow names.
     *
     * @return string[]
     */
    public function all(): array
    {
        return array_keys($this->definitions);
    }
}
