<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            // When true, the "have doubts" checkbox is shown to users on the transcription form.
            $table->integer('have_doubts')->nullable()->default(1)->after('deducted');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('have_doubts');
        });
    }
};
