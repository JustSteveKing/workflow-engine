<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use JustSteveKing\WorkflowEngine\Models\WorkflowInstance;
use JustSteveKing\WorkflowEngine\Models\WorkflowSignal;

/** @extends Factory<WorkflowSignal> */
final class WorkflowSignalFactory extends Factory
{
    /** @var class-string<WorkflowSignal> */
    protected $model = WorkflowSignal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workflow_instance_id' => WorkflowInstance::factory(),
            'signal' => $this->faker->randomElement(['confirmed', 'rejected', 'timeout']),
            'signal_data' => [],
            'delivered_by' => $this->faker->optional()->uuid(),
        ];
    }
}
