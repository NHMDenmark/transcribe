<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comments extends Model
{
    // Table Name
    protected $table = 'comments';
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
	
}
