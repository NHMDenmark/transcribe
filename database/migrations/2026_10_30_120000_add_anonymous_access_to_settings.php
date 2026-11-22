<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // What people who are not logged in may do: login_required | browse | submit.
            // The default keeps the platform exactly as locked down as it is today.
            $table->string('anonymous_access', 20)->default('login_required');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('anonymous_access');
        });
    }
};
