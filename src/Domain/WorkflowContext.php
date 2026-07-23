<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

final readonly class WorkflowContext
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public int|string $workflowInstanceId,
        public string $aggregateId,
        public string $aggregateType,
        private array $data,
    ) {}

    /**
     * Create a new context.
     *
     * @param  array<string, mixed>  $initialData
     */
    public static function make(
        int|string $workflowInstanceId,
        string $aggregateId,
        string $aggregateType,
        array $initialData = [],
    ): self {
        return new self(
            workflowInstanceId: $workflowInstanceId,
            aggregateId: $aggregateId,
            aggregateType: $aggregateType,
            data: $initialData,
        );
    }

    /**
     * Get a value from the context.
     *
     * @param  string  $key  The key to retrieve
     * @param  mixed  $default  The default value if key does not exist
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a key exists in the context.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Get all context data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Create a new context with merged updates.
     * This context remains immutable.
     *
     * @param  array<string, mixed>  $updates
     */
    public function with(array $updates): self
    {
        return new self(
            workflowInstanceId: $this->workflowInstanceId,
            aggregateId: $this->aggregateId,
            aggregateType: $this->aggregateType,
            data: array_merge($this->data, $updates),
        );
    }
}
