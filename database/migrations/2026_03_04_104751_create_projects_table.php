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
        Schema::create('projects', function (Blueprint $table) {
            $table->increments('id');
            $table->text('name')->nullable();
            $table->timestamps();
            $table->integer('user_id');
            $table->string('cover_image')->nullable();
            $table->integer('specimens_count')->nullable();
            $table->integer('fields_completed')->nullable();
            $table->integer('possible_fields')->nullable();
            $table->integer('active')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
