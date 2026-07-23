<?php

declare(strict_types=1);

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Examples\AbandonedCartReminderWorkflow;
use JustSteveKing\WorkflowEngine\Examples\ExpenseApprovalWorkflow;
use JustSteveKing\WorkflowEngine\Examples\MemberRegistrationWorkflow;
use JustSteveKing\WorkflowEngine\Examples\OrderFulfilmentWorkflow;

it('ships example workflows whose steps all implement the step contract', function (string $definitionClass): void {
    $definition = new $definitionClass();

    expect($definition)->toBeInstanceOf(WorkflowDefinitionContract::class)
        ->and($definition::name())->toBeString()->not->toBe('')
        ->and($definition->steps())->not->toBeEmpty();

    foreach ($definition->steps() as $stepClass) {
        expect(new $stepClass())->toBeInstanceOf(WorkflowStepContract::class);
    }
})->with([
    MemberRegistrationWorkflow::class,
    OrderFulfilmentWorkflow::class,
    ExpenseApprovalWorkflow::class,
    AbandonedCartReminderWorkflow::class,
]);
