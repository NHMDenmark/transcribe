<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Without this, a submission/validation/comment row only carries
     * specimen_id + field_id — and a specimen_id can exist under more than
     * one project (a specimen can legitimately belong to several projects,
     * and — with sync_root_table/sync_root_field now configurable per
     * project — two different projects can easily reuse the same specimen_id
     * values for entirely different Specify records, e.g. one project's
     * catalognumber "3" and another project's preparation identifier "3").
     * Every place that resolves "which project is this row about" from
     * specimen_id alone (the /validated dashboard, submissions lists, the
     * sync pipeline) was guessing. Backfilled best-effort for existing rows
     * (arbitrary pick if a specimen_id is genuinely ambiguous — no worse
     * than the guessing this replaces); every new row going forward is
     * written with the project id known at submission time, so it's never
     * ambiguous again.
     */
    public function up(): void
    {
        foreach (['collaborative_fields', 'validated_fields', 'comments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->integer('project_id')->nullable()->index();
            });
        }

        // Best-effort backfill from the specimens table for pre-existing rows.
        foreach (['collaborative_fields', 'validated_fields', 'comments'] as $table) {
            DB::statement("
                UPDATE {$table} t
                JOIN specimens s ON s.specimen_id = t.specimen_id
                SET t.project_id = s.project_id
                WHERE t.project_id IS NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['collaborative_fields', 'validated_fields', 'comments'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('project_id');
            });
        }
    }
};
