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
        Schema::create('messages_tray', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('id_message');
            $table->text('email');
            $table->timestamp('timestamp')->useCurrentOnUpdate()->useCurrent();
            $table->timestamp('sent')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages_tray');
    }
};
