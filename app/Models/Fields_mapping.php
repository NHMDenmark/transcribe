<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fields_mapping extends Model
{
    protected $table    = 'fields_mapping';
    public $primaryKey  = 'id';

    protected $fillable = [
        'field_id',
        'part',
        'api_table_field',
        'type',
        'parent',
        'rank_id',
        'required_defaults',  // JSON: {"source": "transcription API", "agentType": "1"}
    ];

    protected $casts = [
        'required_defaults' => 'array',
    ];

    public function field()
    {
        return $this->belongsTo(Fields::class);
    }
}