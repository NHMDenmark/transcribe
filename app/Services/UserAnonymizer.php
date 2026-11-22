<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Deleting an account: the person is removed, their work stays.
 *
 * Name, e-mail address, photo and password are gone, the account can no longer be used, and every
 * submission, validation, comment and point they gave stays where it was, now shown as "Deleted user"
 * - deleting the row would leave the work of everybody else with holes in it. It cannot be undone.
 */
class UserAnonymizer
{
    public const NAME = 'Deleted user';

    public function __construct(private AvatarStore $avatars)
    {
    }

    /** Why this account may not be deleted by this person, or null if it may. */
    public function refusal(User $actor, User $target): ?string
    {
        if ($target->anonymized_at) {
            return 'This account was already deleted.';
        }

        if ($target->hasRole('root')) {
            if (!$actor->hasRole('root')) {
                return 'Only a root user can delete a root account.';
            }
            if (User::where('role', 'root')->whereNull('anonymized_at')->count() <= 1) {
                return 'This is the only root account, so it cannot be deleted. Make someone else root first.';
            }
        }

        return null;
    }

    public function anonymize(User $user): void
    {
        DB::transaction(function () use ($user) {
            $oldEmail = (string) $user->email;

            // what could still lead back to the person or let somebody act as them
            foreach (['password_reset_tokens', 'password_resets'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('email', $oldEmail)->delete();
                }
            }
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')->where('tokenable_type', User::class)->where('tokenable_id', $user->id)->delete();
            }
            DB::table('project_user')->where('user_id', $user->id)->delete();
            $this->avatars->remove($user);   // the file and the reference

            $user->name = self::NAME;
            // the e-mail address is unique and has to look like one: one that can never be anybody's
            $user->email = 'deleted-' . $user->id . '@anonymous.invalid';
            $user->password = Hash::make(Str::random(64));   // nobody knows it
            $user->remember_token = null;
            $user->email_verified_at = null;
            $user->public_name = true;   // there is no name left to hide
            $user->role = 'user';
            $user->banned_fields = null;
            $user->field_view = null;
            $user->active_language = null;
            $user->automatic_notifications = 0;
            $user->comment_notifications = 0;
            $user->collaborative_notifications = 0;
            $user->anonymized_at = now();
            $user->save();
        });
    }
}
