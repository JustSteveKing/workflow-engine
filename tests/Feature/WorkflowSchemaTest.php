<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

it('creates workflow tables with expected columns', function (): void {
    expect(Schema::hasTable('workflow_instances'))->toBeTrue()
        ->and(Schema::hasColumns('workflow_instances', [
            'workflow_name',
            'workflow_definition_class',
            'definition_version',
            'step_sequence',
            'aggregate_id',
            'aggregate_type',
            'status',
            'step_index',
            'attempts',
            'completed_steps',
            'awaiting_signal',
            'wake_at',
            'context',
            'failed_reason',
            'started_at',
            'completed_at',
            'failed_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('workflow_signals'))->toBeTrue()
        ->and(Schema::hasColumns('workflow_signals', [
            'workflow_instance_id',
            'signal',
            'signal_data',
            'delivered_by',
            'consumed_at',
        ]))->toBeTrue();
});

it('persists context and signal payloads as arrays', function (): void {
    $instance = WorkflowInstance::query()->create([
        'workflow_name' => 'test_workflow',
        'workflow_definition_class' => 'TestWorkflow',
        'aggregate_id' => 'agg_001',
        'aggregate_type' => 'member',
        'status' => 'pending',
        'step_index' => 0,
        'context' => ['invoice_id' => 'inv_001'],
    ]);

    $signal = $instance->signals()->create([
        'signal' => 'payment_succeeded',
        'signal_data' => ['paid_amount' => 1000],
        'delivered_by' => 'mollie_webhook',
    ]);

    expect($instance->refresh()->context)->toBe(['invoice_id' => 'inv_001'])
        ->and($signal->refresh()->signal_data)->toBe(['paid_amount' => 1000])
        ->and($signal->workflowInstance->is($instance))->toBeTrue();
});
