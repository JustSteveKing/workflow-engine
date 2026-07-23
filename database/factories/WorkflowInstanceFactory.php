<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JustSteveKing\WorkflowEngine\Domain\WorkflowStatus;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;

/** @extends Factory<WorkflowInstance> */
final class WorkflowInstanceFactory extends Factory
{
    /** @var class-string<WorkflowInstance> */
    protected $model = WorkflowInstance::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workflow_name' => $this->faker->slug(2),
            'workflow_definition_class' => $this->faker->bothify('JustSteveKing\\WorkflowEngine\\??????????'),
            'aggregate_id' => $this->faker->uuid(),
            'aggregate_type' => $this->faker->randomElement(['member', 'invoice', 'organisation']),
            'status' => $this->faker->randomElement(WorkflowStatus::cases()),
            'step_index' => $this->faker->numberBetween(0, 5),
            'awaiting_signal' => null,
            'context' => [],
            'failed_reason' => null,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn(): array => [
            'status' => WorkflowStatus::Pending,
            'awaiting_signal' => null,
            'failed_reason' => null,
        ]);
    }

    public function awaiting(string $signal = 'confirmed'): self
    {
        return $this->state(fn(): array => [
            'status' => WorkflowStatus::Awaiting,
            'awaiting_signal' => $signal,
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn(): array => [
            'status' => WorkflowStatus::Failed,
            'failed_reason' => $this->faker->sentence(),
        ]);
    }
}
