<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields_mapping', function (Blueprint $table) {
            $table->dropColumn('required_defaults');
        });
    }

    public function down(): void
    {
        Schema::table('fields_mapping', function (Blueprint $table) {
            $table->json('required_defaults')->nullable()->after('rank_id');
        });
    }
};
