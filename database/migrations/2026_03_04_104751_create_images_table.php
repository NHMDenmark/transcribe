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
        Schema::create('images', function (Blueprint $table) {
            $table->integer('id', true)->unique('id_unique');
            $table->integer('CatalogNumber');
            $table->text('image_name');
            $table->string('status', 45)->nullable();
            $table->string('CollectionObjectID', 45)->nullable();
            $table->integer('tiles_depth')->nullable()->default(4);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
