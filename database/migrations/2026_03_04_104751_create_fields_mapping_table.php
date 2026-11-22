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
        Schema::create('fields_mapping', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('field_id');
            $table->integer('part');
            $table->text('api_table_field');
            $table->integer('parent')->nullable();
            $table->text('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields_mapping');
    }
};
