<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Examples;

use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinition;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStep;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * The canonical "process with waiting in it": charge the card, wait for the
 * payment provider's webhook, provision the account, wait for the member to
 * verify their email, then send a welcome pack.
 *
 * Register it in a service provider's boot():
 *     $this->app->make(WorkflowRegistry::class)->register(MemberRegistrationWorkflow::class);
 *
 * Start it when a member signs up:
 *     app(WorkflowEngine::class)->start(
 *         workflowName: 'member_registration',
 *         aggregateId: (string) $member->id,
 *         aggregateType: 'member',
 *         initialContext: ['member_id' => $member->id, 'plan' => 'annual'],
 *     );
 */
final class MemberRegistrationWorkflow implements WorkflowDefinition
{
    public static function name(): string
    {
        return 'member_registration';
    }

    public function steps(): array
    {
        return [
            ChargeMemberStep::class,
            ProvisionAccountStep::class,
            AwaitEmailVerificationStep::class,
            SendWelcomePackStep::class,
        ];
    }
}

final class ChargeMemberStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        // Charge the card through your payment gateway here, then hand control
        // back to the engine and wait for the provider to confirm via webhook.
        //   $ref = Payments::charge($context->get('member_id'), $context->get('plan'));

        return StepResult::await('payment_succeeded', [
            'charge_requested_at' => now()->toIso8601String(),
        ]);
    }

    public function timeoutSeconds(): ?int
    {
        return 3600; // give the webhook an hour, then fail the run
    }

    public function maxAttempts(): int
    {
        return 1; // charging a card twice is worse than failing once
    }
}

final class ProvisionAccountStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        // The signal that resumed us merged its data into the context, so the
        // payment id delivered by the webhook is available here.
        //   Accounts::provision($context->get('member_id'), $context->get('payment_id'));

        return StepResult::complete(['provisioned' => true]);
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 3; // provisioning is idempotent on our side, so retry it
    }
}

final class AwaitEmailVerificationStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        //   Mail::to($context->get('member_id'))->send(new VerifyEmail(...));

        return StepResult::await('email_verified');
    }

    public function timeoutSeconds(): ?int
    {
        return 60 * 60 * 24 * 3; // three days to click the link
    }

    public function maxAttempts(): int
    {
        return 1;
    }
}

final class SendWelcomePackStep implements WorkflowStep
{
    public function handle(WorkflowContext $context): StepResult
    {
        //   Mail::to($context->get('member_id'))->send(new WelcomePack(...));

        return StepResult::complete(['welcomed' => true]);
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
