<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For a checkboxes field: empty = every ticked option is a line of its own (as it has always been);
        // a separator = the ticked options are saved as ONE value, joined with it, in the order of the list.
        Schema::table('fields', function (Blueprint $table) {
            $table->string('choices_separator', 10)->nullable()->after('multiple');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('choices_separator');
        });
    }
};
