<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            // When true, all exploded parts are joined with explode_char
            // and mapped as a single value instead of one mapping per part.
            $table->boolean('concatenate_parts')->default(false)->after('na_defaults');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('concatenate_parts');
        });
    }
};
