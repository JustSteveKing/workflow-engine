<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Jobs\AdvanceWorkflow;
use JustSteveKing\WorkflowEngine\Jobs\CompensateWorkflow;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

/**
 * Ages an instance without touching it through the model's timestamps, so the
 * row looks like one nothing has moved for a while.
 */
function strand(WorkflowInstance $instance, int $minutes): WorkflowInstance
{
    $instance->timestamps = false;
    $instance->forceFill(['updated_at' => now()->subMinutes($minutes)])->save();

    return $instance;
}

it('re-dispatches an advance for a stranded instance', function (string $status): void {
    Queue::fake();

    $stranded = strand(WorkflowInstance::factory()->create(['status' => $status]), 60);

    $this->artisan('workflow:recover')->assertExitCode(0);

    Queue::assertPushed(
        AdvanceWorkflow::class,
        fn(AdvanceWorkflow $job): bool => $job->workflowInstanceId === $stranded->id,
    );
})->with([
    'pending' => [WorkflowStatus::Pending->value],
    'in progress' => [WorkflowStatus::InProgress->value],
]);

it('re-drives a stranded compensation with the job that can move it', function (): void {
    Queue::fake();

    $stranded = strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::Compensating->value]), 60);

    $this->artisan('workflow:recover')->assertExitCode(0);

    Queue::assertPushed(
        CompensateWorkflow::class,
        fn(CompensateWorkflow $job): bool => $job->workflowInstanceId === $stranded->id,
    );
    Queue::assertNotPushed(AdvanceWorkflow::class);
});

it('leaves an instance alone until it has been idle past the threshold', function (): void {
    Queue::fake();

    strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::InProgress->value]), 5);

    $this->artisan('workflow:recover')->assertExitCode(0);

    Queue::assertNothingPushed();
});

it('honours a custom idle threshold', function (): void {
    Queue::fake();

    strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::InProgress->value]), 5);

    $this->artisan('workflow:recover', ['--minutes' => 2])->assertExitCode(0);

    Queue::assertPushed(AdvanceWorkflow::class, 1);
});

it('ignores statuses another sweep owns, or that own themselves', function (string $status): void {
    Queue::fake();

    strand(WorkflowInstance::factory()->create([
        'status' => $status,
        'awaiting_signal' => WorkflowStatus::Awaiting->value === $status ? 'something' : null,
    ]), 60 * 24 * 30);

    $this->artisan('workflow:recover')->assertExitCode(0);

    Queue::assertNothingPushed();
})->with([
    'awaiting, covered by workflow:reconcile' => [WorkflowStatus::Awaiting->value],
    'sleeping, covered by workflow:tick' => [WorkflowStatus::Sleeping->value],
    'completed' => [WorkflowStatus::Completed->value],
    'failed' => [WorkflowStatus::Failed->value],
]);

it('re-dispatches the oldest first and stops at the limit', function (): void {
    Queue::fake();

    $oldest = strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::InProgress->value]), 180);
    $middle = strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::InProgress->value]), 120);
    $newest = strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::InProgress->value]), 60);

    $this->artisan('workflow:recover', ['--limit' => 2])->assertExitCode(0);

    Queue::assertPushed(AdvanceWorkflow::class, 2);
    Queue::assertPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $j): bool => $j->workflowInstanceId === $oldest->id);
    Queue::assertPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $j): bool => $j->workflowInstanceId === $middle->id);
    Queue::assertNotPushed(AdvanceWorkflow::class, fn(AdvanceWorkflow $j): bool => $j->workflowInstanceId === $newest->id);
});

it('refuses a threshold or limit below one rather than sweeping everything', function (array $options): void {
    Queue::fake();

    strand(WorkflowInstance::factory()->create(['status' => WorkflowStatus::InProgress->value]), 60);

    $this->artisan('workflow:recover', $options)->assertExitCode(1);

    Queue::assertNothingPushed();
})->with([
    'zero minutes' => [['--minutes' => 0]],
    'zero limit' => [['--limit' => 0]],
]);
