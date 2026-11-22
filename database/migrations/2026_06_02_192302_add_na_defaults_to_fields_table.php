<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            // Default value to substitute when the validated value is "n.a.".
            // Stored in the same format as the field value (same explode_char separator).
            // Example for a 9-part locality field: "n.a.||Denmark|||||Denmark|Denmark"
            $table->text('na_defaults')->nullable()->after('required_defaults');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('na_defaults');
        });
    }
};
