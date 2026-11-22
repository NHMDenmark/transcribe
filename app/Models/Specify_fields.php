<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specify_fields extends Model
{
    // Table Name
    protected $table = 'specify_fields';
    // Primary Key
    public $primaryKey = 'id';

	public function specimen() {
        return $this->belongsTo('App\Specimen', 'specimen_id', 'id');
	}
	
	public function field() {
        return $this->belongsTo('App\Fields');
	}

	public function user(){
        return $this->belongsTo('App\User');
	}
	
	public function collaborative_fields() {
        return $this->hasMany('App\Collaborative_fields');
	}

}
