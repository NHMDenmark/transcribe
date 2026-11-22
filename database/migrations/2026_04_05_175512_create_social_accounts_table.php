<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider_name', 255);
            $table->string('provider_id', 255);
            $table->timestamps();

            $table->unique(['provider_name', 'provider_id'], 'social_accounts_provider_unique');
            $table->foreign('user_id', 'fk_social_user')
                  ->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
