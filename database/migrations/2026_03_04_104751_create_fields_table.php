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
        Schema::create('fields', function (Blueprint $table) {
            $table->integer('id', true)->comment('key for the fields');
            $table->integer('position')->nullable()->comment('Position (order) of the field on the form');
            $table->integer('parent')->default(0)->comment('In case the fields are organised as an hierarchical tree');
            $table->string('name')->comment('The name of the field');
            $table->text('question')->nullable()->comment('a text string to be displayed on the form (can be a question)');
            $table->text('text')->nullable()->comment('A text explaining what the field is');
            $table->string('type')->comment('The kind of input (text[input], textarea, dropdown[select], radiobutton, etc.)');
            $table->integer('required')->nullable();
            $table->integer('deducted')->nullable()->default(1);
            $table->string('searchfield')->nullable()->comment('the searchfiled to be used if autocomplete is available');
            $table->string('format')->nullable()->comment('if a mask is needed (date, digits, patterns)');
            $table->longText('option_values')->nullable()->comment('if the type uses prepopulated values (dropdown[select], hierarchical tree)');
            $table->text('explode_fields')->nullable();
            $table->text('explode_char')->nullable();
            $table->text('explode_save_direction')->nullable();
            $table->integer('autocomplete')->nullable()->default(1);
            $table->integer('multiple')->comment('if the field allows multiple values');
            $table->integer('min_user_level')->nullable()->comment('if the field is to be displayed to users according to their role');
            $table->string('helper_image', 45)->nullable()->comment('an image to bedisplayed to the user as a helper');
            $table->dateTime('created_at')->comment('datestamp when created');
            $table->dateTime('updated_at')->comment('datestamp when updated');
            $table->text('api_table_field')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
