<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // When the form was shown to the user; transcription time = created_at - displayed_at.
    public function up(): void
    {
        Schema::table('collaborative_fields', function (Blueprint $table) {
            $table->dateTime('displayed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('collaborative_fields', function (Blueprint $table) {
            $table->dropColumn('displayed_at');
        });
    }
};
