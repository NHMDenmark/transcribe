<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Who sees "Submitted by other users" on the transcription form:
            // off | root | admin | project_manager | all. The default is what it was before this setting existed.
            $table->string('others_panel_audience', 20)->default('project_manager');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('others_panel_audience');
        });
    }
};
