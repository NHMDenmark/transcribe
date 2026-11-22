<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields_mapping', function (Blueprint $table) {
            $table->string('rank_id', 11)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('fields_mapping', function (Blueprint $table) {
            $table->dropColumn('rank_id');
        });
    }
};
