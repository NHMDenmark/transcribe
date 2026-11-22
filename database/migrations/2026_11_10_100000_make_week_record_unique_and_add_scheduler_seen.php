<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rows of the same person and week were written more than once (two visitors at the same time, or the manual
        // route opened twice): keep the first of each, then make it impossible.
        $keep = DB::table('week_record')->selectRaw('min(id) as id')->groupBy('user_id', 'year_week')->pluck('id');
        DB::table('week_record')->whereNotIn('id', $keep)->delete();

        Schema::table('week_record', function (Blueprint $table) {
            $table->unique(['user_id', 'year_week'], 'week_record_user_week_unique');
        });

        Schema::table('settings', function (Blueprint $table) {
            // The last time the scheduler (the cron line) ran, so the Settings page can say when it is not running.
            $table->dateTime('scheduler_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('week_record', function (Blueprint $table) {
            $table->dropUnique('week_record_user_week_unique');
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('scheduler_seen_at');
        });
    }
};
