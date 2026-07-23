<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Examples;

use JustSteveKing\WorkflowEngine\Contracts\CompensatingStep;
use JustSteveKing\WorkflowEngine\Contracts\HasRetryBackoff;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowStepContract;
use JustSteveKing\WorkflowEngine\Contracts\WorkflowDefinitionContract;
use JustSteveKing\WorkflowEngine\Domain\StepResult;
use JustSteveKing\WorkflowEngine\Domain\WorkflowContext;

/**
 * A saga. Reserve stock, charge the customer, ship the order. If shipping falls
 * over after the money has moved, you cannot roll back a distributed
 * transaction, so you run the inverse operations instead: refund the charge and
 * release the stock, in reverse order of how they happened.
 *
 * Mark the steps that have side effects to undo with CompensatingStep. When a
 * later step fails past its retries, the engine walks the completed
 * compensatable steps backwards and calls compensate() on each.
 */
final class OrderFulfilmentWorkflow implements WorkflowDefinitionContract
{
    public static function name(): string
    {
        return 'order_fulfilment';
    }

    public function steps(): array
    {
        return [
            ReserveStockStep::class,
            ChargeCustomerStep::class,
            ShipOrderStep::class,
        ];
    }
}

final class ReserveStockStep implements CompensatingStep, WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   Inventory::reserve($context->get('order_id'));

        return StepResult::complete(['stock_reserved' => true]);
    }

    public function compensate(WorkflowContext $context): void
    {
        // Runs second during rollback (reverse order).
        //   Inventory::release($context->get('order_id'));
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

final class ChargeCustomerStep implements CompensatingStep, HasRetryBackoff, WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        //   $chargeId = Payments::charge($context->get('customer_id'), $context->get('total'));

        return StepResult::complete(['charge_id' => 'ch_example']);
    }

    public function compensate(WorkflowContext $context): void
    {
        // Runs first during rollback. Guard this with an idempotency key, the
        // compensate job can be redelivered by an at-least-once queue.
        //   Payments::refund($context->get('charge_id'));
    }

    public function retryBackoff(int $attempt): int
    {
        return 10 * $attempt; // 10s, 20s, 30s
    }

    public function timeoutSeconds(): ?int
    {
        return null;
    }

    public function maxAttempts(): int
    {
        return 3; // a payment gateway blip should not fail the whole order
    }
}

final class ShipOrderStep implements WorkflowStepContract
{
    public function execute(WorkflowContext $context): StepResult
    {
        // If this throws or returns fail(), the two steps above are compensated
        // in reverse: refund, then release stock.
        //   Shipping::dispatch($context->get('order_id'));

        return StepResult::complete(['shipped' => true]);
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
