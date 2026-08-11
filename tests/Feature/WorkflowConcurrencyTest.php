<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The engine's concurrency safety rests on `findById(lock: true)` taking a real
 * `SELECT ... FOR UPDATE` row lock inside `transaction()`. SQLite has no row
 * locks, so this can only be proven against a driver that does. It runs on
 * PostgreSQL and is skipped elsewhere.
 *
 * The test uses two dedicated connections and a row it commits itself, because
 * RefreshDatabase wraps each test in a transaction on the default connection,
 * which a second connection could not see.
 */
it('serialises concurrent access to an instance row with FOR UPDATE', function (): void {
    $cfg = config('database.connections.' . config('database.default'));
    config([
        'database.connections.contend_a' => $cfg,
        'database.connections.contend_b' => $cfg,
    ]);

    $a = DB::connection('contend_a');
    $b = DB::connection('contend_b');

    $id = 900001;
    $a->table('workflow_instances')->insert([
        'id' => $id,
        'workflow_name' => 'lock_probe',
        'workflow_definition_class' => 'lock_probe',
        'definition_version' => 1,
        'aggregate_id' => 'a',
        'aggregate_type' => 't',
        'status' => 'pending',
        'step_index' => 0,
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        // Fail fast rather than hang if the lock is not honoured.
        $b->statement("SET lock_timeout = '500ms'");

        // A holds the row lock, exactly what findById(lock: true) emits.
        $a->beginTransaction();
        $a->table('workflow_instances')->where('id', $id)->lockForUpdate()->first();

        // B cannot take the same lock while A holds it.
        expect(fn() => $b->table('workflow_instances')->where('id', $id)->lockForUpdate()->first())
            ->toThrow(QueryException::class);

        // A releases the lock; B can now take it.
        $a->commit();

        $b->beginTransaction();
        $row = $b->table('workflow_instances')->where('id', $id)->lockForUpdate()->first();
        expect($row->id)->toEqual($id);
        $b->commit();
    } finally {
        if ($a->transactionLevel() > 0) {
            $a->rollBack();
        }
        if ($b->transactionLevel() > 0) {
            $b->rollBack();
        }
        $a->table('workflow_instances')->where('id', $id)->delete();
        DB::purge('contend_a');
        DB::purge('contend_b');
    }
})->skip(fn(): bool => 'pgsql' !== DB::connection()->getDriverName(), 'Row-lock contention requires PostgreSQL.');
