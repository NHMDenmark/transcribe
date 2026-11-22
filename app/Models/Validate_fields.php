<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Validate_fields extends Model
{
    // Table Name
    protected $table = 'validated_fields';
    // Primary Key
    public $primaryKey = 'id';

	public function specimen() {
        return $this->belongsTo(Specimen::class, 'specimen_id', 'id');
	}
	
	public function field() {
        return $this->belongsTo(Fields::class);
	}

	public function user(){
        return $this->belongsTo(User::class);
	}
	
	public function collaborative_fields() {
        return $this->hasMany(Collaborative_fields::class);
	}

}
