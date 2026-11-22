<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

/**
 * Site-wide branding (logo, loading-indicator GIF) — admin-only, singleton
 * (no index/show/create/destroy; there's exactly one settings row).
 */
class SettingsController extends Controller
{
    public function edit()
    {
        if (!\Illuminate\Support\Facades\Gate::any(['manage-settings', 'manage-access'])) {
            return redirect('/projects')->with('error', 'Unauthorized Page');
        }

        return view('settings.edit', ['setting' => Setting::current()]);
    }

    public function update(Request $request)
    {
        if (!\Illuminate\Support\Facades\Gate::allows('manage-settings')) {
            return redirect('/projects')->with('error', 'Unauthorized Page');
        }

        $this->validate($request, [
            'logo'        => 'image|nullable|max:2048',
            'loading_gif' => 'mimes:gif|nullable|max:2048',
        ]);

        $setting = Setting::current();

        if ($request->hasFile('logo')) {
            $setting->logo = $this->storeBrandingFile($request->file('logo'), 'logo');
        } elseif ($request->boolean('reset_logo')) {
            $setting->logo = null;
        }

        if ($request->hasFile('loading_gif')) {
            $setting->loading_gif = $this->storeBrandingFile($request->file('loading_gif'), 'loading');
        } elseif ($request->boolean('reset_loading_gif')) {
            $setting->loading_gif = null;
        }

        $setting->save();

        return redirect('/settings')->with('success', 'Settings updated');
    }

    /** Who may use the platform without logging in. */
    public function updateAccess(Request $request)
    {
        $data = $request->validate([
            'anonymous_access' => ['required', 'in:' . implode(',', config('access.modes'))],
        ]);

        $setting = Setting::current();
        $setting->anonymous_access = $data['anonymous_access'];
        $setting->save();

        return redirect('/settings')->with('success', 'Settings updated');
    }

    /** The master switch of the automatic sync, and the time zone its schedules are written in. */
    public function updateScheduling(Request $request)
    {
        $data = $request->validate([
            'schedule_timezone' => ['required', 'timezone:all'],
        ]);

        $setting = Setting::current();
        $setting->auto_sync = $request->boolean('auto_sync');
        $setting->schedule_timezone = $data['schedule_timezone'];
        $setting->save();

        return redirect('/settings')->with('success', 'Settings updated');
    }

    /** Whether people may create their own account (otherwise: by invitation, or Google / GitHub). */
    public function updateRegistration(Request $request)
    {
        $setting = Setting::current();
        $setting->self_registration = $request->boolean('self_registration');
        $setting->save();

        return redirect('/settings')->with('success', 'Settings updated');
    }

    /**
     * Stores an uploaded branding file under public/branding/, named with a
     * timestamp so browsers/CDNs don't keep serving a cached old logo/gif
     * under the same filename after a replace.
     */
    private function storeBrandingFile($file, string $prefix): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename  = $prefix . '_' . time() . '.' . $extension;

        $file->move(public_path('branding'), $filename);

        return $filename;
    }
}
