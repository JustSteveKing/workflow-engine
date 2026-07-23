<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_instances', static function (Blueprint $table): void {
            $table->id();
            $table->string('workflow_name');
            $table->string('workflow_definition_class');
            $table->unsignedInteger('definition_version')->default(1);
            $table->json('step_sequence')->nullable(); // snapshot of ordered step classes at start
            $table->string('aggregate_id');
            $table->string('aggregate_type');
            $table->string('status')->default('pending'); // pending, in_progress, awaiting, sleeping, compensating, completed, failed
            $table->integer('step_index')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->json('completed_steps')->nullable(); // steps that ran forward, for compensation
            $table->string('awaiting_signal')->nullable();
            $table->timestamp('wake_at')->nullable(); // when a sleeping instance should resume
            $table->json('context')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['aggregate_id', 'aggregate_type']);
            $table->index('status');
            $table->index('awaiting_signal');
            $table->index('workflow_name');
            $table->index('wake_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_instances');
    }
};
