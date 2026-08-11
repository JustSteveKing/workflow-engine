<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\StateMachine\Contracts;

/**
 * @internal
 */
interface StateContract
{
    public function value(): string;

    public function label(): string;
}
