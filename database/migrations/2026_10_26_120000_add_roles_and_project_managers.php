<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Level 0 used to be "the administrator": it is only a level now (the one whose single
    // submission is worth enough points to validate), so it gets a name that says that.
    private const OLD_NAME = 'Administrator';
    private const NEW_NAME = 'Validator';

    private function dialogName(string $language, bool $new): string
    {
        $names = ['es' => ['Administrador', 'Validador']];

        return ($names[$language] ?? [self::OLD_NAME, self::NEW_NAME])[$new ? 1 : 0];
    }

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // What a user may do (see config/roles.php). The level (users.category) only
            // drives points and progression; it no longer doubles as the admin flag.
            $table->string('role', 20)->default('user');
        });

        // Until now "category 0" meant administrator and could do everything, so those
        // users become root: nobody loses a permission on the day this is deployed.
        DB::table('users')->where('category', 0)->update(['role' => 'root']);

        // Extra people allowed to manage a project besides its creator.
        Schema::create('project_user', function (Blueprint $table) {
            $table->id();
            $table->integer('project_id')->index();
            $table->integer('user_id')->index();
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        // A half-built permission flag on the level that nothing reads any more.
        if (Schema::hasColumn('user_categories', 'permissions')) {
            Schema::table('user_categories', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }

        // Level 0 stops being called "Administrator". Only what still carries the old
        // name is renamed, so a name an admin has already changed is left alone.
        $level = DB::table('user_categories')->where('id', 0)->first();
        if ($level && $level->category === self::OLD_NAME) {
            DB::table('user_categories')->where('id', 0)->update([
                'category' => self::NEW_NAME,
                'description' => $level->description === 'root' ? null : $level->description,
            ]);
        }

        // Its display name and description exist once per language (as HTML): rename the
        // untouched ones ("Administrator", or the "root" an earlier setup left behind).
        $dialogs = DB::table('dialogs')->whereIn('identifier', ['user_categories_name_0', 'user_categories_description_0'])->get();
        foreach ($dialogs as $dialog) {
            $plain = trim(strip_tags((string) $dialog->text));
            if (in_array($plain, [$this->dialogName($dialog->language, false), self::OLD_NAME, 'root'], true)) {
                DB::table('dialogs')->where('id', $dialog->id)->update([
                    'text' => str_replace($plain, $this->dialogName($dialog->language, true), $dialog->text),
                ]);
            }
        }
    }

    public function down(): void
    {
        $dialogs = DB::table('dialogs')->whereIn('identifier', ['user_categories_name_0', 'user_categories_description_0'])->get();
        foreach ($dialogs as $dialog) {
            $plain = trim(strip_tags((string) $dialog->text));
            if ($plain === $this->dialogName($dialog->language, true)) {
                DB::table('dialogs')->where('id', $dialog->id)->update([
                    'text' => str_replace($plain, $this->dialogName($dialog->language, false), $dialog->text),
                ]);
            }
        }

        if (!Schema::hasColumn('user_categories', 'permissions')) {
            Schema::table('user_categories', function (Blueprint $table) {
                $table->integer('permissions')->nullable();
            });
        }
        DB::table('user_categories')->where('id', 0)->where('category', self::NEW_NAME)->update([
            'category' => self::OLD_NAME,
            'permissions' => 0,
        ]);

        Schema::dropIfExists('project_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
