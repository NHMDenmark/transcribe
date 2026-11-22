<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'category',
        'field_view',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'public_name' => 'boolean',
        'anonymized_at' => 'datetime',
    ];

    public function user_categories()
    {
        return $this->belongsTo(User_categories::class, 'category', 'id');
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role ?? 'user', $roles, true);
    }

    /** Root and admin: not limited to particular projects or levels. */
    public function isAdmin(): bool
    {
        return $this->hasRole('root', 'admin');
    }

    public function managedProjects()
    {
        return $this->belongsToMany(Project::class, 'project_user', 'user_id', 'project_id');
    }

    /** Ids of the projects a project manager created or was given. */
    public function managedProjectIds(): array
    {
        return Project::where('user_id', $this->id)
            ->orWhereIn('id', $this->managedProjects()->pluck('projects.id'))
            ->pluck('id')
            ->all();
    }

    /** Root/admin manage every project; a project manager only the ones they created or were given. */
    public function managesProject(Project $project): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->hasRole('project_manager')
            && ((int) $project->user_id === (int) $this->id || $this->managedProjects()->where('projects.id', $project->id)->exists());
    }

    /**
     * Points used to decide which fields and specimens this user may see. Root and admin
     * are not held back by a minimum level, so they count as the highest one; everyone
     * else is limited by their own level.
     */
    public function visibilityPoints(): int
    {
        if ($this->hasRole(...config('roles.ignore_level', []))) {
            return (int) User_categories::max('points');
        }

        return (int) ($this->user_categories->points ?? User_categories::min('points'));
    }
    public function languages()
    {
        return $this->hasMany(Language::class);
    }
    public function collaborative_fields()
    {
        return $this->hasMany(Collaborative_fields::class);
    }
    public function week_report()
    {
        return $this->hasMany(Week_report::class);
    }

public function socialAccounts()
{
    return $this->hasMany(SocialAccount::class);
}    

}
