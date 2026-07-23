<?php

declare(strict_types=1);

namespace JustSteveKing\WorkflowEngine\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use JustSteveKing\WorkflowEngine\Database\Factories\WorkflowSignalFactory;

/**
 * @property int $id
 * @property int $workflow_instance_id
 * @property string $signal
 * @property array<string, mixed>|null $signal_data
 * @property string|null $delivered_by
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class WorkflowSignal extends Model
{
    /** @use HasFactory<WorkflowSignalFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'workflow_instance_id',
        'signal',
        'signal_data',
        'delivered_by',
        'consumed_at',
    ];

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(
            related: WorkflowInstance::class,
            foreignKey: 'workflow_instance_id',
        );
    }

    /** @return Factory<self> */
    protected static function newFactory(): Factory
    {
        return WorkflowSignalFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'signal_data' => 'array',
            'consumed_at' => 'datetime',
        ];
    }
}
