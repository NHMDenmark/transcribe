<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // What a specimen's specimen_id is matched against in Specify to
            // find the record to sync into — e.g. collectionobject/catalognumber
            // (the only behavior that existed before this), or
            // preparation/text1 for a project whose specimens are identified
            // at the preparation level instead. Defaults match prior hardcoded
            // behavior, so every existing project keeps working unchanged.
            $table->string('sync_root_table', 100)->default('collectionobject')->after('field_view_default');
            $table->string('sync_root_field', 100)->default('catalognumber')->after('sync_root_table');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['sync_root_table', 'sync_root_field']);
        });
    }
};
