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
        Schema::create('specify_fields', function (Blueprint $table) {
            $table->integer('id', true);
            $table->mediumText('value')->fulltext('value');
            $table->integer('position');
            $table->integer('specimen_id')->index('specimen_id');
            $table->integer('field_id');
            $table->string('user_id');
            $table->integer('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('specify_fields');
    }
};
