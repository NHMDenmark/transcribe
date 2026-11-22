<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Site-wide branding, configurable instead of hardcoded — the point being
 * that this platform is meant to be handed off to other digitisation
 * projects/institutions, each with their own logo (and, if they want,
 * their own loading-indicator animation).
 *
 * A single row (id=1) rather than a key/value table: there is exactly one
 * of these per deployment, and "settings" here means "branding", not a
 * general-purpose config store — no reason to build for a need that
 * doesn't exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // Filenames under public/branding/ — null means "use the built-in
            // generic default" (see Setting::logoUrl()/loadingGifUrl()).
            $table->string('logo')->nullable();
            $table->string('loading_gif')->nullable();
            $table->timestamps();
        });

        // The row starts empty: no logo (the navbar shows the name of the
        // application) and the generic loading indicator. Each installation
        // uploads its own in the Settings page.
        DB::table('settings')->insert([
            'id'           => 1,
            'logo'         => null,
            'loading_gif'  => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
