<?php

namespace App\Services;

use App\Models\Collaborative_fields;
use App\Models\User;

/** How many submissions of each person are valid, rejected or still waiting (stored on the user for the rankings). */
class UserCounts
{
    /** @param  int|string|null  $userId  one person, or null for everybody; an anonymous visitor (a session id) has no row to recount */
    public function recount($userId = null): void
    {
        if ($userId !== null && ! is_numeric($userId)) {
            return;
        }
        ini_set('max_execution_time', 0);
        $users = $userId != null ? User::where('id', '=', $userId)->get() : User::orderBy('name', 'asc')->get();

        foreach ($users as $user) {
            $user->validated = $this->count(2, $user->id);
            $user->rejected = $this->count(3, $user->id);
            $user->submitted = $this->count(1, $user->id);
            $user->save();
        }
    }

    private function count(int $status, $userId): int
    {
        return Collaborative_fields::where('status', '=', $status)->groupBy('specimen_id', 'field_id')->where('user_id', '=', "$userId")->get()->count();
    }
}
