<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Whether a visitor may create an account for themselves. Off by default: an installation stays
            // "by invitation" (an administrator creates the account, or Google / GitHub sign-in) until it is switched on.
            $table->boolean('self_registration')->default(false);
        });

        Schema::table('projects', function (Blueprint $table) {
            // The Specify collection this project's sync logs in to. Empty = the installation's default (API_COLLECTION).
            $table->string('specify_collection', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('self_registration');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('specify_collection');
        });
    }
};
