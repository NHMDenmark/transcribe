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
        Schema::create('validated_fields', function (Blueprint $table) {
            $table->integer('id', true);
            $table->text('value')->fulltext('value');
            $table->integer('position');
            $table->text('specimen_id');
            $table->integer('field_id')->index('field_id');
            $table->string('user_id');
            $table->integer('status')->default(1)->index('status');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validated_fields');
    }
};
