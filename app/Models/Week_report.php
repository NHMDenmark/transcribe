<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Week_report extends Model
{
    // Table Name
    protected $table = 'week_record';
    // Primary Key
    public $primaryKey = 'id';
    // Timestamps
    public $timestamps = true;
	
	public function user(){
        return $this->belongsTo(User::class);
	}	
	
}
