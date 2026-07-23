<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Database\Factories\WorkflowInstanceFactory;

/**
 * @property int $id
 * @property string $workflow_name
 * @property string $workflow_definition_class
 * @property int $definition_version
 * @property list<class-string>|null $step_sequence
 * @property string $aggregate_id
 * @property string $aggregate_type
 * @property string $status
 * @property int $step_index
 * @property int $attempts
 * @property list<class-string>|null $completed_steps
 * @property string|null $awaiting_signal
 * @property Carbon|null $wake_at
 * @property array<string, mixed>|null $context
 * @property string|null $failed_reason
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class WorkflowInstance extends Model
{
    /** @use HasFactory<WorkflowInstanceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
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
    ];

    /** @return HasMany<WorkflowSignal, $this> */
    public function signals(): HasMany
    {
        return $this->hasMany(
            related: WorkflowSignal::class,
            foreignKey: 'workflow_instance_id',
        );
    }

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return WorkflowInstanceFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'definition_version' => 'integer',
            'step_sequence' => 'array',
            'step_index' => 'integer',
            'attempts' => 'integer',
            'completed_steps' => 'array',
            'wake_at' => 'datetime',
            'context' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
