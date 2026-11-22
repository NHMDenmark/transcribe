<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('autocompletefields', function (Blueprint $table) {
            $table->integer('id', true);
            $table->text('value')->nullable()->fulltext('values');
            $table->integer('field_id')->nullable()->index('field_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('autocompletefields');
    }
};
