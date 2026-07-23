<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_signals', static function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignId('workflow_instance_id')
                ->constrained('workflow_instances')
                ->cascadeOnDelete();
            $table->string('signal');
            $table->json('signal_data')->nullable();
            $table->string('delivered_by')->nullable();
            $table->timestamp('consumed_at')->nullable(); // null while buffered/unconsumed
            $table->timestamps();

            $table->index('signal');
            $table->index('workflow_instance_id');
            // Supports the early-signal buffer lookup (unconsumed signal by instance + name).
            $table->index(['workflow_instance_id', 'signal', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_signals');
    }
};
