<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One option of a select / radio / checkboxes field: the value that is saved (no language) and, per
 * language, what is shown in its place.
 */
class Field_options extends Model
{
    protected $table = 'field_options';

    protected $casts = [
        'labels' => 'array',
    ];

    public function field()
    {
        return $this->belongsTo(Fields::class, 'field_id');
    }

    /** What to show in a language: its label, or the value when that language has none. */
    public function label(string $language): string
    {
        $label = trim((string) ($this->labels[$language] ?? ''));

        return $label !== '' ? $label : (string) $this->value;
    }
}
