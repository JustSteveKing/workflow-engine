<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

final readonly class SignalReceived
{
    /**
     * @param  array<string, mixed>  $signalData
     */
    public function __construct(
        public int|string $instanceId,
        public string $signal,
        public array $signalData,
        public ?string $deliveredBy,
        public bool $buffered,
    ) {}
}
