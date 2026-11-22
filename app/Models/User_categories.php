<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User_categories extends Model
{
    // Table Name
    protected $table = 'user_categories';
    // Primary Key
    public $primaryKey = 'id';

    /** The level a new person starts at: the one that needs the fewest validations (the validator level has none). */
    public static function starting(): ?self
    {
        return static::whereNotNull('validations_required')->orderBy('validations_required', 'asc')->first();
    }

	public function user_categories() {
        return $this->hasMany(User::class, 'category', 'id');
	}
			
}
