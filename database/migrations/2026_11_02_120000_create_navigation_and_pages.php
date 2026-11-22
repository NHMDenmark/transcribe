<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The structure of the menu: a tree of groups (menu headings), links and "contents" items (the
        // places where contents are shown). Its name is a dialog per language (navigation_name_{id}).
        Schema::create('navigation', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->integer('position')->default(0);
            $table->string('type', 10)->default('contents');       // group | contents | link
            $table->string('slug', 100);
            $table->string('link_url', 500)->nullable();           // type link: an internal path or an external address
            $table->boolean('active')->default(true);
            $table->boolean('show_in_menu')->default(true);
            $table->boolean('members_only')->default(false);       // only for logged-in users (root/admin always see everything)
            $table->timestamps();
        });

        // The contents themselves, kept apart from the menu and assigned to one of its items (or to none yet).
        // Title and body are dialogs per language (content_title_{id}, content_body_{id}).
        // Without a publish_at a content is a draft that only root/admin see.
        Schema::create('content_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('navigation_id')->nullable()->index();
            $table->integer('position')->default(0);
            $table->boolean('members_only')->default(false);
            $table->dateTime('publish_at')->nullable();
            $table->dateTime('archive_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $this->seedExistingMenu();
    }

    /**
     * The menu used to be Projects / About / Tutorial, written into the layout, with the About and
     * Tutorial texts kept as dialogs. They become ordinary items and contents so nothing is lost;
     * whether they need a login follows the platform's current access setting.
     */
    private function seedExistingMenu(): void
    {
        $mode = Schema::hasColumn('settings', 'anonymous_access') ? DB::table('settings')->value('anonymous_access') : null;
        $membersOnly = in_array($mode, ['browse', 'submit'], true) ? 0 : 1;
        $now = now();

        $items = [
            ['slug' => 'projects', 'type' => 'link', 'link_url' => '/projects', 'name' => 'Projects', 'title' => null, 'body' => null],
            ['slug' => 'about', 'type' => 'contents', 'link_url' => null, 'name' => 'About', 'title' => 'About', 'body' => 'about_content'],
            ['slug' => 'tutorial', 'type' => 'contents', 'link_url' => null, 'name' => 'tutorial', 'title' => 'tutorial', 'body' => 'tutorial_content'],
        ];

        foreach ($items as $position => $item) {
            $navId = DB::table('navigation')->insertGetId([
                'parent_id' => null, 'position' => $position, 'type' => $item['type'], 'slug' => $item['slug'],
                'link_url' => $item['link_url'], 'active' => 1, 'show_in_menu' => 1,
                // a link only leads somewhere the platform itself protects; the texts carry their own setting
                'members_only' => $item['type'] === 'link' ? $membersOnly : 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->copyDialog($item['name'], 'navigation_name_' . $navId);

            if ($item['type'] === 'contents') {
                $contentId = DB::table('content_items')->insertGetId([
                    'navigation_id' => $navId, 'position' => 0, 'members_only' => $membersOnly,
                    'publish_at' => $now, 'archive_at' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->copyDialog($item['title'], 'content_title_' . $contentId);
                $this->copyDialog($item['body'], 'content_body_' . $contentId);
            }
        }
    }

    /** Copies every language of one dialog to a new identifier, leaving the original in place. */
    private function copyDialog(string $from, string $to): void
    {
        $hasTimestamps = Schema::hasColumn('dialogs', 'created_at');

        foreach (DB::table('dialogs')->where('identifier', $from)->get() as $dialog) {
            $row = ['identifier' => $to, 'language' => $dialog->language, 'text' => $dialog->text];
            if ($hasTimestamps) {
                $row['created_at'] = now();
                $row['updated_at'] = now();
            }
            DB::table('dialogs')->insert($row);
        }
    }

    public function down(): void
    {
        DB::table('dialogs')->where('identifier', 'like', 'navigation\_name\_%')
            ->orWhere('identifier', 'like', 'content\_title\_%')
            ->orWhere('identifier', 'like', 'content\_body\_%')
            ->delete();

        Schema::dropIfExists('content_items');
        Schema::dropIfExists('navigation');
    }
};
