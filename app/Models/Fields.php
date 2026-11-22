<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fields extends Model
{
    protected $table    = 'fields';
    public $primaryKey  = 'id';

    protected $casts = [
        'required_defaults' => 'array',
        'concatenate_parts' => 'boolean',
    ];

    protected $fillable = [
    'name', 'position', 'parent', 'type', 'required', 'deducted', 'have_doubts',
    'searchfield', 'format', 'option_values', 'explode_fields',
    'explode_char', 'explode_save_direction', 'autocomplete', 'multiple',
    'min_user_level', 'helper_image', 'required_defaults', 'na_defaults',
    'concatenate_parts', 'choices_separator',
];

    public function collaborative_fields()
    {
        return $this->hasMany(Collaborative_fields::class);
    }

    public function specimen()
    {
        return $this->belongsTo(Specimen::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'projects_fields', 'field_id', 'project_id');
    }

    public function user_category()
    {
        return $this->belongsTo(User_categories::class, 'min_user_level', 'id');
    }

    public function mapping()
    {
        return $this->hasMany(Fields_mapping::class, 'field_id', 'id');
    }

    // ---- the options of a select / radio / checkboxes field ---------------------------------------

    /** The field types that choose from a list of options. */
    public const CHOICE_TYPES = ['select', 'radio', 'checkboxes'];

    protected static function booted(): void
    {
        static::deleted(fn($field) => Field_options::where('field_id', $field->id)->delete());
    }

    public function options()
    {
        return $this->hasMany(Field_options::class, 'field_id')->orderBy('position')->orderBy('id');
    }

    public function isChoice(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    /** The values of a text with an option per line (the old way of writing a list): trimmed, no blanks, no repeats. */
    public static function linesToValues(?string $text): array
    {
        $values = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $value = trim($line);
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * What the transcription form offers, in order: the value to save and what to show for it in a language
     * (its label there, else the value itself). A field that has no option rows yet (created before they
     * existed) offers the lines of its option_values.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function choices(string $language): array
    {
        if ($this->options->isNotEmpty()) {
            return $this->options->map(fn($o) => ['value' => (string) $o->value, 'label' => $o->label($language)])->all();
        }

        return array_map(fn($v) => ['value' => $v, 'label' => $v], self::linesToValues($this->option_values));
    }

    /**
     * The same for the editing form: every language's label, not just one.
     *
     * @return array<int, array{value: string, labels: array<string, string>}>
     */
    public function choicesForEditor(): array
    {
        if ($this->options->isNotEmpty()) {
            return $this->options->map(fn($o) => ['value' => (string) $o->value, 'labels' => (object) ($o->labels ?? [])])->all();
        }

        // only a list field has options to edit; for the others option_values is the plain box of suggestions
        if (!$this->isChoice()) {
            return [];
        }

        return array_map(fn($v) => ['value' => $v, 'labels' => (object) []], self::linesToValues($this->option_values));
    }
    // ---- several ticked options as one value -------------------------------------------------------

    /** Whether a checkboxes field saves its ticked options as one value (it has a separator) instead of a line each. */
    public function joinsChoices(): bool
    {
        return $this->type === 'checkboxes' && (string) $this->choices_separator !== '';
    }

    /**
     * The ticked options of a line as the one value that is saved: in the order of the list, not of the clicks, so
     * the same choice is always the same text (validation compares texts). A value the list does not have (it was
     * removed after the form was shown) is kept, at the end. Blanks and repeats are dropped.
     */
    public function joinChoices(array $ticked): string
    {
        $listed = array_column($this->choices('en'), 'value');
        $ticked = array_values(array_unique(array_filter(array_map(fn($v) => trim((string) $v), $ticked), fn($v) => $v !== '')));

        usort($ticked, function ($a, $b) use ($listed) {
            $pa = array_search($a, $listed, true);
            $pb = array_search($b, $listed, true);

            return [$pa === false ? PHP_INT_MAX : $pa] <=> [$pb === false ? PHP_INT_MAX : $pb];
        });

        return implode((string) $this->choices_separator, $ticked);
    }

    /** The options a saved value stands for (the other way round): split at the separator, trimmed. */
    public function splitChoices(string $value): array
    {
        if (!$this->joinsChoices()) {
            return $value === '' ? [] : [$value];
        }

        return array_values(array_filter(array_map('trim', explode((string) $this->choices_separator, $value)), fn($v) => $v !== ''));
    }

    /** Is the separator part of one of the options (then a joined value could not be split back)? */
    public function separatorClashesWith(array $values): ?string
    {
        $separator = (string) $this->choices_separator;
        if ($separator === '') {
            return null;
        }
        foreach ($values as $value) {
            if (str_contains((string) $value, $separator)) {
                return (string) $value;
            }
        }

        return null;
    }
}
