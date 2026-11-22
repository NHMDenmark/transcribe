<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // The master switch of the automatic sync, and the time zone the times of the schedules are written in.
            $table->boolean('auto_sync')->default(false);
            $table->string('schedule_timezone', 64)->default('UTC');
        });

        Schema::table('projects', function (Blueprint $table) {
            // The schedule of a project's automatic sync: hourly | every_n_hours | daily | weekly.
            $table->boolean('auto_sync')->default(false);
            $table->string('auto_sync_frequency', 20)->default('daily');
            $table->string('auto_sync_time', 5)->default('02:00');
            $table->unsignedTinyInteger('auto_sync_weekday')->default(1);   // 0 = Sunday
            $table->unsignedTinyInteger('auto_sync_every')->default(6);     // hours, for every_n_hours
            // The last scheduled moment that was handled; the project is due when a later one has come.
            $table->dateTime('auto_sync_last_slot')->nullable();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();             // who started a manual run
            $table->string('trigger', 10);                                  // auto | manual | command
            $table->string('status', 10);                                   // running | ok | failed
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('conflicts')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedInteger('pending')->default(0);                 // still waiting when the run ended
            $table->string('message', 500)->nullable();
            $table->longText('log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['auto_sync', 'auto_sync_frequency', 'auto_sync_time', 'auto_sync_weekday', 'auto_sync_every', 'auto_sync_last_slot']);
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['auto_sync', 'schedule_timezone']);
        });
    }
};
