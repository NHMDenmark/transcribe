<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, int $level = 5): User
    {
        $cat = User_categories::find($level) ?? new User_categories();
        $cat->id = $level;
        $cat->category = 'level ' . $level;
        $cat->points = $level === 0 ? 60 : $level * 10;
        $cat->save();

        $user = new User();
        $user->category = $level;
        $user->role = $role;
        $user->name = $role;
        $user->email = uniqid() . '@example.test';
        $user->password = bcrypt('password');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function project(User $creator = null): Project
    {
        $project = new Project();
        $project->name = 'P' . uniqid();
        $project->user_id = $creator->id ?? 0;
        $project->active = 1;
        $project->save();

        return $project;
    }

    public function test_the_instance_level_capabilities_belong_to_root_only(): void
    {
        foreach (['manage-settings', 'manage-languages', 'manage-roles'] as $capability) {
            $this->assertTrue(Gate::forUser($this->user('root'))->allows($capability), "root $capability");
            $this->assertFalse(Gate::forUser($this->user('admin'))->allows($capability), "admin $capability");
            $this->assertFalse(Gate::forUser($this->user('project_manager'))->allows($capability), "pm $capability");
            $this->assertFalse(Gate::forUser($this->user('user'))->allows($capability), "user $capability");
        }
    }

    public function test_day_to_day_administration_is_for_root_and_admin(): void
    {
        foreach (['manage-users', 'edit-dialogs', 'manage-fields', 'run-sync', 'view-all-submissions'] as $capability) {
            $this->assertTrue(Gate::forUser($this->user('root'))->allows($capability), $capability);
            $this->assertTrue(Gate::forUser($this->user('admin'))->allows($capability), $capability);
            $this->assertFalse(Gate::forUser($this->user('project_manager'))->allows($capability), $capability);
            $this->assertFalse(Gate::forUser($this->user('user'))->allows($capability), $capability);
        }
    }

    public function test_a_project_manager_only_manages_the_projects_they_created_or_were_given(): void
    {
        $pm = $this->user('project_manager');
        $mine = $this->project($pm);
        $given = $this->project();
        $given->managers()->attach($pm->id);
        $someoneElses = $this->project($this->user('admin'));

        foreach (['manage-projects', 'see-others-submissions', 'resolve-conflicts'] as $capability) {
            $gate = Gate::forUser($pm);
            $this->assertTrue($gate->allows($capability), "$capability somewhere");
            $this->assertTrue($gate->allows($capability, $mine), "$capability own");
            $this->assertTrue($gate->allows($capability, $given), "$capability given");
            $this->assertFalse($gate->allows($capability, $someoneElses), "$capability someone else's");
        }
    }

    public function test_root_and_admin_manage_every_project_and_a_regular_user_none(): void
    {
        $project = $this->project($this->user('user'));

        $this->assertTrue(Gate::forUser($this->user('root'))->allows('manage-projects', $project));
        $this->assertTrue(Gate::forUser($this->user('admin'))->allows('manage-projects', $project));
        $this->assertFalse(Gate::forUser($this->user('user'))->allows('manage-projects', $project));
        $this->assertFalse(Gate::forUser($this->user('user'))->allows('manage-projects'));
    }

    public function test_the_level_has_nothing_to_do_with_what_a_role_may_do(): void
    {
        // the old rule was "category 0 or 1 = admin": neither a top level nor level 0 grants anything now
        $this->assertFalse(Gate::forUser($this->user('user', 0))->allows('manage-users'));
        $this->assertTrue(Gate::forUser($this->user('admin', 6))->allows('manage-users'));
    }

    public function test_root_and_admin_are_not_held_back_by_their_level(): void
    {
        $this->user('user', 6);          // makes the highest level (60 points) exist
        $low = $this->user('admin', 2);  // an admin on a low level
        $regular = $this->user('user', 2);

        $this->assertSame(60, $low->visibilityPoints());
        $this->assertSame(20, $regular->visibilityPoints());
    }

    public function test_the_project_list_is_scoped_by_role(): void
    {
        $pm = $this->user('project_manager');
        $admin = $this->user('admin');
        $active = $this->project();
        $inactiveMine = $this->project($pm);
        $inactiveMine->active = 0;
        $inactiveMine->save();
        $inactiveGiven = $this->project();
        $inactiveGiven->active = 0;
        $inactiveGiven->save();
        $inactiveGiven->managers()->attach($pm->id);
        $inactiveOther = $this->project();
        $inactiveOther->active = 0;
        $inactiveOther->save();

        $ids = fn(?User $u) => Project::visibleTo($u)->pluck('id')->sort()->values()->all();

        $this->assertSame([$active->id], $ids(null));
        $this->assertSame([$active->id], $ids($this->user('user')));
        $this->assertSame(collect([$active->id, $inactiveMine->id, $inactiveGiven->id])->sort()->values()->all(), $ids($pm));
        $this->assertCount(4, $ids($admin));
    }
}
