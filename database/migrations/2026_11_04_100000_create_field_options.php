<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The field types that choose from a list of options. */
    private const CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

    public function up(): void
    {
        // The options of a select / radio / checkboxes field. `value` is what is saved and sent to Specify
        // (it has no language); `labels` is what each language shows instead ({"da": "Januar", "en": "January"}),
        // the value itself where a language has none.
        Schema::create('field_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('field_id')->index();
            $table->integer('position')->default(0);
            $table->text('value');
            $table->text('labels')->nullable();
            $table->timestamps();
        });

        $this->seedFromOptionValues();
    }

    /**
     * Until now the list was one text, an option per line, which was both what the volunteer saw and what
     * was saved. Each line becomes an option with no labels, so everything looks and saves exactly as before.
     * (fields.option_values stays: it keeps mirroring the values, and still holds the suggestions of text
     * fields and the tree of a tree field.)
     */
    public function seedFromOptionValues(): void
    {
        $now = now();

        foreach (DB::table('fields')->whereIn('type', self::CHOICE_TYPES)->get(['id', 'option_values']) as $field) {
            $seen = [];
            $position = 0;

            foreach (preg_split('/\r\n|\r|\n/', (string) $field->option_values) as $line) {   // not \R: without the u flag it splits "Å" in two
                $value = trim($line);
                if ($value === '' || isset($seen[$value])) {
                    continue;
                }
                $seen[$value] = true;

                DB::table('field_options')->insert([
                    'field_id' => $field->id, 'position' => $position++, 'value' => $value,
                    'labels' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('field_options');
    }
};
