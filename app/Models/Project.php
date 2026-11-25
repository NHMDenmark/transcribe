<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
        use HasFactory;

        // Table Name
        protected $table = 'projects';
        // Primary Key
        public $primaryKey = 'id';
        // Timestamps
        public $timestamps = true;

        protected $casts = ['auto_sync' => 'boolean', 'auto_sync_last_slot' => 'datetime'];

        public function specimen()
        {
                return $this->hasMany(Specimen::class, 'project_id', 'id');
        }

        public function fields()
        {
                return $this->belongsToMany(Fields::class, 'projects_fields', 'project_id', 'field_id');
        }

        /** Choices for who sees "Submitted by other users" on the transcription form, widest last. */
        public const OTHERS_PANEL_AUDIENCES = ['off', 'root', 'admin', 'project_manager', 'all'];

        /**
         * Whether this user gets the "Submitted by other users" panel on this project's form.
         * 'project_manager' means root, admin and the managers of this project.
         */
        public function showsOthersPanelTo(?User $user): bool
        {
                if (!$user) {
                        return false;
                }

                return match ($this->others_panel_audience ?? 'project_manager') {
                        'off' => false,
                        'root' => $user->hasRole('root'),
                        'admin' => $user->isAdmin(),
                        'project_manager' => $user->managesProject($this) && $user->hasRole('root', 'admin', 'project_manager'),
                        'all' => true,
                        default => false,
                };
        }

        /** The user who created the project (user_id); null if that account no longer exists. */
        public function creator()
        {
                return $this->belongsTo(User::class, 'user_id');
        }

        /** People allowed to manage this project besides its creator (user_id). */
        public function managers()
        {
                return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id');
        }

        /**
         * Projects a visitor may see listed: everyone sees the active ones; root/admin also
         * the inactive ones; a project manager also their own and the ones they were given.
         */
        public function scopeVisibleTo($query, ?User $user)
        {
                if ($user && $user->isAdmin()) {
                        return $query;
                }

                return $query->where(function ($q) use ($user) {
                        $q->where('active', '=', 1);
                        if ($user && $user->hasRole('project_manager')) {
                                $q->orWhere('user_id', $user->id)
                                        ->orWhereIn('id', $user->managedProjects()->pluck('projects.id'));
                        }
                });
        }
}
