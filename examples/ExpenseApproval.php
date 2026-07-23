<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Examples;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * Branching with goto. Small expenses auto-approve, large ones wait for a human.
 *
 * The step order matters: an awaiting step resumes to the *next* step in the
 * sequence, so the manual-approval branch sits immediately before Finalise, and
 * the auto-approve branch jumps past it with an explicit goto. That is the one
 * modelling wrinkle worth internalising when you branch.
 *
 *     [ SubmitExpense, RouteExpense, AutoApprove, AwaitManagerApproval, Finalise ]
 */
final class ExpenseApprovalWorkflow implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'expense_approval';
    }

    public function steps(): array
    {
        return [
            SubmitExpenseStep::class,
            RouteExpenseStep::class,
            AutoApproveStep::class,
            AwaitManagerApprovalStep::class,
            FinaliseExpenseStep::class,
        ];
    }
}

final class SubmitExpenseStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        return StepResult::complete(['submitted_at' => now()->toIso8601String()]);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}

final class RouteExpenseStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        $amount = (int) $context->get('amount_in_cents', 0);

        // Over £1,000 needs a manager; anything else is auto-approved.
        return $amount > 100_000
            ? StepResult::goto(AwaitManagerApprovalStep::class)
            : StepResult::goto(AutoApproveStep::class);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}

final class AutoApproveStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        // Skip the manual-approval step and go straight to finalising.
        return StepResult::goto(FinaliseExpenseStep::class, ['approved_by' => 'auto']);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}

final class AwaitManagerApprovalStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   Notification::send($manager, new ExpenseNeedsApproval(...));

        return StepResult::await('manager_decision');
    }

    public function timeoutSeconds(): ?int
    {
        return 60 * 60 * 24 * 3; // escalate/fail if no decision in three days
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}

final class FinaliseExpenseStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   Ledger::record($context->all());

        return StepResult::complete(['finalised' => true]);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 3;
    }
}
