<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The conflict screen reads Specify live; no snapshot of Specify's values is kept.
    public function up(): void
    {
        if (Schema::hasColumn('validated_fields', 'conflict')) {
            Schema::table('validated_fields', function (Blueprint $table) {
                $table->dropColumn('conflict');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('validated_fields', 'conflict')) {
            Schema::table('validated_fields', function (Blueprint $table) {
                $table->text('conflict')->nullable();
            });
        }
    }
};
