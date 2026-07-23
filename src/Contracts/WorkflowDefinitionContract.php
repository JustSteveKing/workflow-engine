<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Contracts;

interface WorkflowDefinitionContract
{
    /**
     * Return the machine-name of this workflow.
     */
    public static function name(): string;

    /**
     * Return an ordered array of step class names.
     *
     * @return class-string<WorkflowStepContract>[]
     */
    public function steps(): array;
}
