<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     *
     * One Gate per capability in config/roles.php. A capability that is project-scoped
     * (see 'project_scoped') is given a project as its argument; a project manager only
     * has it on the projects they manage, while without a project the question is just
     * "do they have it anywhere".
     */
    public function boot(): void
    {
        $scoped = config('roles.project_scoped', []);

        foreach (config('roles.capabilities', []) as $capability => $roles) {
            Gate::define($capability, function (User $user, ?Project $project = null) use ($capability, $roles, $scoped) {
                if (!$user->hasRole(...$roles)) {
                    return false;
                }

                if ($project !== null && in_array($capability, $scoped, true)) {
                    return $user->managesProject($project);
                }

                return true;
            });
        }
    }
}
