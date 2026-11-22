<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collaborative_fields extends Model
{
    // Table Name
    protected $table = 'collaborative_fields';
    // Primary Key
    public $primaryKey = 'id';

	public function specimen() {
        return $this->belongsTo(Specimen::class, 'specimen_id', 'specimen_id');
	}
	
	public function field() {
        return $this->belongsTo(Fields::class);
	}

	public function user(){
        return $this->belongsTo(User::class);
	}

	public function validation(){
        return $this->belongsTo(Validated_fields::class);
	}
		
}
