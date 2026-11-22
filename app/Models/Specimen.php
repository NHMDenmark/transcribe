<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specimen extends Model
{
        use HasFactory;

        // Table Name
        protected $table = 'specimens';
        // Primary Key
        public $primaryKey = 'id';
        // Timestamps
        public $timestamps = true;

        protected $fillable = ['specimen_id','images', 'project_id', 'min_user_level'];     

        public function project()
        {
                return $this->belongsTo(Project::class, 'project_id', 'id');
        }
        public function min_user_category()
        {
                return $this->belongsTo(User_categories::class, 'min_user_level', 'id');
        }

}
