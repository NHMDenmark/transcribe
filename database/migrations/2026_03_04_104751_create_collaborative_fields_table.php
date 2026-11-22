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
        Schema::create('collaborative_fields', function (Blueprint $table) {
            $table->integer('id', true);
            $table->text('value')->fulltext('value')->comment('the value submitted');
            $table->integer('position')->comment('the position of this value when multiple values are allowed');
            $table->text('specimen_id')->index('specimen_id')->comment('the id of the specimen where this value belongs to');
            $table->integer('field_id')->comment('the id of the field');
            $table->string('user_id')->comment('the user who submitted the value');
            $table->integer('status')->default(1)->comment('statis of the value (0 = deleted, 1 =  pending, 2 = approved, 3 = rejected)');
            $table->integer('doubt')->nullable()->default(0)->comment('if the user selects doubt checkbox on the form');
            $table->integer('deducted')->nullable()->default(0)->comment('if the user selects the deducted checkbox on the form');
            $table->integer('points')->comment('the points assigned to this answer (based on the user level)');
            $table->integer('validation_id')->nullable()->comment('if validation has been processed on this specimen-field, it stores the id of the validation table record');
            $table->dateTime('created_at')->nullable()->useCurrent();
            $table->dateTime('updated_at')->nullable();
            $table->text('remarks')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborative_fields');
    }
};
