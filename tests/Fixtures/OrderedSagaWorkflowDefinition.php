<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Tests\Fixtures;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;

final class OrderedSagaWorkflowDefinition implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'test_ordered_saga_workflow';
    }

    public function steps(): array
    {
        return [
            RecordingCompensatingStepOne::class,
            RecordingCompensatingStepTwo::class,
            FailingStep::class,
        ];
    }
}
