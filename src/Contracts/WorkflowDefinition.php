<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

interface WorkflowDefinition
{
    /**
     * Return the machine-name of this workflow.
     */
    public static function name(): string;

    /**
     * Return an ordered array of step class names.
     *
     * @return class-string<WorkflowStep>[]
     */
    public function steps(): array;
}
