<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Examples;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * A time-spaced drip campaign built on sleep(). Send a nudge, sleep for a day,
 * send another, sleep for a few more, send a last one. The whole thing costs a
 * single database row while it waits — no scheduled command scanning for carts,
 * no per-cart cron entry.
 *
 * Each step sends its email and then returns sleep($seconds), which advances the
 * cursor but parks the instance until the wake time. Run `workflow:tick` on the
 * scheduler so a sleeper still resumes if its delayed job is ever lost.
 */
final class AbandonedCartReminderWorkflow implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'abandoned_cart_reminder';
    }

    public function steps(): array
    {
        return [
            FirstNudgeStep::class,
            SecondNudgeStep::class,
            FinalNudgeStep::class,
        ];
    }
}

final class FirstNudgeStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   Mail::to($context->get('email'))->send(new CartReminder(...));

        return StepResult::sleep(60 * 60, ['first_nudge_sent' => true]); // then wait an hour
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

final class SecondNudgeStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   Mail::to($context->get('email'))->send(new CartReminderWithDiscount(...));

        return StepResult::sleep(60 * 60 * 24, ['second_nudge_sent' => true]); // then wait a day
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

final class FinalNudgeStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   Mail::to($context->get('email'))->send(new LastChanceCart(...));

        return StepResult::complete(['final_nudge_sent' => true]);
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
