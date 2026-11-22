<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('api_table_field');
            $table->json('required_defaults')->nullable()->after('updated_at')
                  ->comment('JSON map of Tabela.campo => default value for required fields not covered by mapping');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn('required_defaults');
            $table->text('api_table_field')->nullable();
        });
    }
};
