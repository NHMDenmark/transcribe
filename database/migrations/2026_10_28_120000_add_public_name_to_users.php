<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Whether the name is shown to other users (comments, leaderboard, other users' submissions).
            // Everybody starts visible, as it has always been; each user can switch it off in their profile.
            $table->boolean('public_name')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('public_name');
        });
    }
};
