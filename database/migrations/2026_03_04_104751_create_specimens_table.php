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
        Schema::create('specimens', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('project_id');
            $table->text('specimen_id')->index('specimen_id');
            $table->text('images')->nullable();
            $table->integer('min_user_level')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specimens');
    }
};
