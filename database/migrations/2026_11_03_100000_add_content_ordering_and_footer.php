<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the contents of a "contents" item are ordered: by hand (their position), or automatically.
        Schema::table('navigation', function (Blueprint $table) {
            $table->string('sort_mode', 10)->default('manual')->after('type');   // manual | newest | oldest | title
        });

        // The footer: blocks side by side (a text, or a list of links to menu items), in order. Their
        // texts are dialogs per language (footer_title_{id}, footer_body_{id}).
        Schema::create('footer_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->default('text');          // text | links
            $table->integer('position')->default(0);
            $table->unsignedTinyInteger('width')->default(4);     // columns of 12
            $table->boolean('active')->default(true);
            $table->text('data')->nullable();                     // links: {"nodes": [menu item ids]}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        DB::table('dialogs')->where('identifier', 'like', 'footer\_title\_%')
            ->orWhere('identifier', 'like', 'footer\_body\_%')
            ->delete();

        Schema::dropIfExists('footer_blocks');
        Schema::table('navigation', function (Blueprint $table) {
            $table->dropColumn('sort_mode');
        });
    }
};
