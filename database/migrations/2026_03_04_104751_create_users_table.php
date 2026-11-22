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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('category')->default(6);
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password', 255);
            $table->rememberToken();
            $table->timestamps();
            $table->integer('validated')->nullable();
            $table->integer('rejected')->nullable();
            $table->integer('submitted')->nullable();
            $table->integer('deleted')->nullable();
            $table->string('active_language', 2)->nullable();
            $table->integer('automatic_notifications')->nullable();
            $table->integer('comment_notifications')->nullable();
            $table->integer('collaborative_notifications')->nullable();
            $table->text('banned_fields')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
