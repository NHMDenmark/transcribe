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
        Schema::create('week_record', function (Blueprint $table) {
            $table->integer('id', true)->unique('id_unique');
            $table->integer('user_id')->nullable();
            $table->string('year_week', 45)->nullable();
            $table->integer('submitted')->nullable();
            $table->integer('validated')->nullable();
            $table->integer('rejected')->nullable();
            $table->integer('total')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('week_record');
    }
};
