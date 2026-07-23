<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowEngine;
use JustSteveKing\WorkflowEngine\Domain\WorkflowRegistry;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AutoWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\AwaitWorkflowDefinition;
use JustSteveKing\WorkflowEngine\Tests\Fixtures\ThrowingWorkflowDefinition;

function registerWorkflow(string $definition): void
{
    app(WorkflowRegistry::class)->register($definition);
}

it('lists registered workflows', function (): void {
    registerWorkflow(AutoWorkflowDefinition::class);

    $this->artisan('workflow:list')
        ->assertSuccessful()
        ->expectsOutputToContain('test_auto_workflow');
});

it('starts a workflow from the console', function (): void {
    Queue::fake();
    registerWorkflow(AutoWorkflowDefinition::class);

    $this->artisan('workflow:start', [
        'workflow' => AutoWorkflowDefinition::name(),
        'aggregateId' => 'member_cli',
        'aggregateType' => 'member',
        '--context' => '{"plan":"annual"}',
    ])->assertSuccessful();

    expect(WorkflowInstance::query()->where('aggregate_id', 'member_cli')->exists())->toBeTrue();
});

it('rejects an invalid context payload when starting', function (): void {
    Queue::fake();
    registerWorkflow(AutoWorkflowDefinition::class);

    $this->artisan('workflow:start', [
        'workflow' => AutoWorkflowDefinition::name(),
        'aggregateId' => 'member_bad',
        'aggregateType' => 'member',
        '--context' => 'not-json',
    ])->assertFailed();
});

it('shows a workflow instance', function (): void {
    Queue::fake();
    registerWorkflow(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_show', 'invoice');
    $engine->advance($instance->id);

    $this->artisan('workflow:show', ['id' => $instance->id])
        ->assertSuccessful()
        ->expectsOutputToContain('test_await_workflow');
});

it('reports a missing instance on show', function (): void {
    $this->artisan('workflow:show', ['id' => '999999'])->assertFailed();
});

it('advances a workflow from the console', function (): void {
    Queue::fake();
    registerWorkflow(AutoWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AutoWorkflowDefinition::name(), 'member_adv', 'member');

    $this->artisan('workflow:advance', ['id' => $instance->id])->assertSuccessful();

    expect(WorkflowInstance::query()->findOrFail($instance->id)->step_index)->toBe(1);
});

it('delivers a signal from the console', function (): void {
    Queue::fake();
    registerWorkflow(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_sig', 'invoice');
    $engine->advance($instance->id); // now awaiting payment_succeeded

    $this->artisan('workflow:signal', [
        'id' => $instance->id,
        'signal' => 'payment_succeeded',
        '--data' => '{"payment_id":"pay_cli"}',
        '--by' => 'cli',
    ])->assertSuccessful();

    $persisted = WorkflowInstance::query()->findOrFail($instance->id);
    expect($persisted->status)->toBe('in_progress')
        ->and($persisted->context)->toMatchArray(['payment_id' => 'pay_cli']);
});

it('retries a failed instance from the console', function (): void {
    Queue::fake();
    registerWorkflow(ThrowingWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(ThrowingWorkflowDefinition::name(), 'invoice_retry', 'invoice');
    $engine->advance($instance->id); // fails

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('failed');

    $this->artisan('workflow:retry', ['id' => $instance->id])->assertSuccessful();

    expect(WorkflowInstance::query()->findOrFail($instance->id)->status)->toBe('in_progress');
});

it('retries failed instances in bulk with force', function (): void {
    Queue::fake();
    registerWorkflow(ThrowingWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    foreach (['a', 'b'] as $suffix) {
        $instance = $engine->start(ThrowingWorkflowDefinition::name(), "invoice_bulk_{$suffix}", 'invoice');
        $engine->advance($instance->id);
    }

    $this->artisan('workflow:retry', ['--workflow' => ThrowingWorkflowDefinition::name(), '--force' => true])
        ->assertSuccessful();

    expect(WorkflowInstance::query()->where('status', 'failed')->count())->toBe(0);
});

it('re-dispatches sleeping instances past their wake time', function (): void {
    Queue::fake();

    $instance = WorkflowInstance::query()->create([
        'workflow_name' => 'x',
        'workflow_definition_class' => 'X',
        'step_sequence' => ['A'],
        'aggregate_id' => 'agg',
        'aggregate_type' => 'thing',
        'status' => 'sleeping',
        'step_index' => 0,
        'wake_at' => Carbon::now()->subMinute(),
        'context' => [],
    ]);

    $this->artisan('workflow:tick')->assertSuccessful();

    Queue::assertPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $job): bool => $job->workflowInstanceId === $instance->id);
});

it('lists instances with a status summary', function (): void {
    Queue::fake();
    registerWorkflow(AutoWorkflowDefinition::class);
    app(WorkflowEngine::class)->start(AutoWorkflowDefinition::name(), 'member_inst', 'member');

    $this->artisan('workflow:instances')
        ->assertSuccessful()
        ->expectsOutputToContain('test_auto_workflow');
});

it('prunes old terminal instances', function (): void {
    $instance = WorkflowInstance::query()->create([
        'workflow_name' => 'x',
        'workflow_definition_class' => 'X',
        'step_sequence' => ['A'],
        'aggregate_id' => 'agg',
        'aggregate_type' => 'thing',
        'status' => 'completed',
        'step_index' => 1,
        'context' => [],
    ]);

    // Age it past the retention window without touching timestamps via the model.
    WorkflowInstance::query()->where('id', $instance->id)->update(['updated_at' => Carbon::now()->subDays(40)]);

    $this->artisan('workflow:prune', ['--days' => 30, '--force' => true])->assertSuccessful();

    expect(WorkflowInstance::query()->find($instance->id))->toBeNull();
});

it('fails to advance an unknown instance', function (): void {
    $this->artisan('workflow:advance', ['id' => '999999'])->assertFailed();
});

it('fails to start an unregistered workflow', function (): void {
    $this->artisan('workflow:start', [
        'workflow' => 'nope',
        'aggregateId' => 'a',
        'aggregateType' => 'thing',
    ])->assertFailed();
});

it('rejects an invalid signal data payload', function (): void {
    Queue::fake();
    registerWorkflow(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_baddata', 'invoice');
    $engine->advance($instance->id);

    $this->artisan('workflow:signal', [
        'id' => $instance->id,
        'signal' => 'payment_succeeded',
        '--data' => 'not-json',
    ])->assertFailed();
});

it('fails to signal a terminal instance', function (): void {
    Queue::fake();
    registerWorkflow(AutoWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AutoWorkflowDefinition::name(), 'member_term', 'member');
    $engine->advance($instance->id);
    $engine->advance($instance->id); // completed

    $this->artisan('workflow:signal', ['id' => $instance->id, 'signal' => 'anything'])->assertFailed();
});

it('fails to retry an instance that has not failed', function (): void {
    Queue::fake();
    registerWorkflow(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_notfailed', 'invoice');
    $engine->advance($instance->id); // awaiting, not failed

    $this->artisan('workflow:retry', ['id' => $instance->id])->assertFailed();
});

it('reports when a bulk retry matches nothing', function (): void {
    $this->artisan('workflow:retry', ['--workflow' => 'anything', '--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('No failed instances');
});

it('rejects pruning a non-terminal status', function (): void {
    $this->artisan('workflow:prune', ['--status' => 'awaiting', '--force' => true])->assertFailed();
});

it('reports when there is nothing to prune', function (): void {
    $this->artisan('workflow:prune', ['--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Nothing to prune');
});

it('filters the instances list by status', function (): void {
    Queue::fake();
    registerWorkflow(AwaitWorkflowDefinition::class);
    $engine = app(WorkflowEngine::class);

    $instance = $engine->start(AwaitWorkflowDefinition::name(), 'invoice_filter', 'invoice');
    $engine->advance($instance->id); // awaiting

    $this->artisan('workflow:instances', ['--status' => 'awaiting'])
        ->assertSuccessful()
        ->expectsOutputToContain('awaiting');
});
