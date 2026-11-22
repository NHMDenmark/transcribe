<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // 'overwrite' = current behaviour (always write); 'keep_existing' = never replace a different value already in Specify.
            $table->string('sync_overwrite', 20)->default('overwrite');
            $table->boolean('sync_compare_case_insensitive')->default(false);
            $table->unsignedTinyInteger('sync_compare_round_decimals')->nullable();
        });

        Schema::table('validated_fields', function (Blueprint $table) {
            // JSON list of the parts that differed from Specify (status 4 = conflict).
            $table->text('conflict')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['sync_overwrite', 'sync_compare_case_insensitive', 'sync_compare_round_decimals']);
        });

        Schema::table('validated_fields', function (Blueprint $table) {
            $table->dropColumn('conflict');
        });
    }
};
