<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Domain;

/**
 * A signal that was delivered and buffered before its step was awaiting it,
 * returned when the step finally parks and consumes it.
 */
final readonly class BufferedSignal
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public array $data,
        public ?string $deliveredBy,
    ) {}
}
