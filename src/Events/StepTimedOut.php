<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Events;

final readonly class StepTimedOut
{
    public function __construct(
        public int|string $instanceId,
        public string $signal,
        public int $stepIndex,
        /**
         * The step the instance continued from, when the timed-out step routes
         * its deadline somewhere (see TimeoutRoutingStep). Null when the
         * timeout failed the instance, which remains the default.
         *
         * @var class-string|null
         */
        public ?string $reroutedTo = null,
    ) {}
}
