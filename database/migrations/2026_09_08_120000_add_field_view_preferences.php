<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Transcription form preference: 'single' (one field per screen),
            // 'all' (every field on one screen), or NULL to inherit the
            // project default / global default.
            $table->string('field_view', 10)->nullable()->after('banned_fields');
        });

        Schema::table('projects', function (Blueprint $table) {
            // Default transcription form workflow for this project: 'single',
            // 'all', or NULL to inherit the global default.
            $table->string('field_view_default', 10)->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('field_view');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('field_view_default');
        });
    }
};
