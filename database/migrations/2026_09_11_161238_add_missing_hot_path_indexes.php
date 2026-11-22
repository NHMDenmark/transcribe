<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // validated_fields is filtered by specimen_id in every query in the
        // codebase (SpecimensController::show/store, the ingestion pipeline,
        // the validation dashboard) but only has indexes on field_id/status.
        Schema::table('validated_fields', function (Blueprint $table) {
            $table->index('specimen_id', 'validated_fields_specimen_id_index');
        });

        // collaborative_fields is queried by user_id/field_id/status alongside
        // the already-indexed specimen_id in nearly every read; only specimen_id
        // is currently indexed.
        Schema::table('collaborative_fields', function (Blueprint $table) {
            $table->index('user_id', 'collaborative_fields_user_id_index');
            $table->index('field_id', 'collaborative_fields_field_id_index');
            $table->index('status', 'collaborative_fields_status_index');
        });

        // specimens is resolved by specimen_id + project_id together
        // (SpecimensController::show()); project_id alone had no index.
        Schema::table('specimens', function (Blueprint $table) {
            $table->index('project_id', 'specimens_project_id_index');
        });

        // specimen_id is TEXT, so a composite index needs an explicit prefix
        // length (a full-length TEXT column alone already exceeds MySQL's
        // 3072-byte key limit once project_id is added) — 191 chars covers
        // any realistic catalog number with room to spare.
        DB::statement(
            'ALTER TABLE specimens ADD INDEX specimens_specimen_id_project_id_index (specimen_id(191), project_id)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('validated_fields', function (Blueprint $table) {
            $table->dropIndex('validated_fields_specimen_id_index');
        });

        Schema::table('collaborative_fields', function (Blueprint $table) {
            $table->dropIndex('collaborative_fields_user_id_index');
            $table->dropIndex('collaborative_fields_field_id_index');
            $table->dropIndex('collaborative_fields_status_index');
        });

        Schema::table('specimens', function (Blueprint $table) {
            $table->dropIndex('specimens_project_id_index');
            $table->dropIndex('specimens_specimen_id_project_id_index');
        });
    }
};
