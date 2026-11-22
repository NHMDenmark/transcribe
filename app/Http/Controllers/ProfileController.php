<?php

namespace App\Http\Controllers;

use App\Services\AvatarStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** A user's own profile: the name (or pseudonym) others see, whether it is shown at all, the photo and the password. */
class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function edit(Request $request)
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->public_name = $request->boolean('public_name');
        $user->save();

        return redirect('/profile')->with('success', 'Profile updated');
    }

    public function updateAvatar(Request $request, AvatarStore $avatars)
    {
        if (!AvatarStore::available()) {
            return back()->withErrors(['avatar' => 'Photos are not available on this server.']);
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:' . implode(',', AvatarStore::formats()), 'max:2048'],
        ]);

        try {
            $avatars->store($request->file('avatar'), $request->user());
        } catch (\RuntimeException $e) {
            if ($e->getCode() === AvatarStore::SERVER_PROBLEM) {
                // not the picture's fault: say so, and leave the reason where whoever looks after the server can read it
                $avatars->logFailure($e);

                return back()->withErrors(['avatar' => 'The server could not save the photo (' . $e->getMessage() . '). Nothing is wrong with the image: please tell an administrator.']);
            }

            return back()->withErrors(['avatar' => 'This image could not be used. Try another one.']);
        }

        return redirect('/profile')->with('success', 'Photo updated');
    }

    public function removeAvatar(Request $request, AvatarStore $avatars)
    {
        $avatars->remove($request->user());

        return redirect('/profile')->with('success', 'Photo removed');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();
        $user->password = Hash::make($data['password']);
        $user->save();

        return redirect('/profile')->with('success', 'Password updated');
    }
    /**
     * Deletes the signed-in person's own account: their data goes, their work stays as "Deleted user".
     * Confirmed by typing the e-mail address; it cannot be undone.
     */
    public function destroy(Request $request, \App\Services\UserAnonymizer $anonymizer)
    {
        $user = $request->user();
        $request->validate(['confirm_email' => ['required', 'string']]);

        if (strtolower(trim($request->input('confirm_email'))) !== strtolower((string) $user->email)) {
            return back()->withErrors(['confirm_email' => 'That is not the e-mail address of this account. Nothing was deleted.']);
        }
        if ($reason = $anonymizer->refusal($user, $user)) {
            return back()->withErrors(['confirm_email' => $reason]);
        }

        $anonymizer->anonymize($user);

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Your account was deleted.');
    }
}
