<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Site-wide branding (logo, loading-indicator GIF) — one row, id=1. See the
 * create_settings_table migration for why this isn't a general key/value
 * store.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $fillable = ['logo', 'loading_gif'];

    protected $casts = ['scheduler_seen_at' => 'datetime', 'auto_sync' => 'boolean', 'self_registration' => 'boolean'];

    /**
     * What visitors who are not logged in may do: login_required (nothing), browse (read-only pages) or
     * submit (browse and transcribe anonymously, tied to the browser session). Anything unknown, or a
     * database without the column yet, counts as login_required.
     */
    public function anonymousAccess(): string
    {
        $mode = $this->anonymous_access ?? 'login_required';

        return in_array($mode, config('access.modes'), true) ? $mode : 'login_required';
    }

    /** Whether a visitor may create an account for themselves (Settings page). Off unless an administrator switches it on. */
    public function allowsRegistration(): bool
    {
        return (bool) ($this->self_registration ?? false);
    }

    /** Written every minute by the scheduler; see App\Console\Kernel. */
    public static function markSchedulerSeen(): void
    {
        static::current()->forceFill(['scheduler_seen_at' => now()])->save();
    }

    /** Whether the scheduler (the cron line) ran in the last few minutes. */
    public function schedulerRunning(): bool
    {
        return $this->scheduler_seen_at !== null && $this->scheduler_seen_at->greaterThan(now()->subMinutes(5));
    }

    /**
     * The single settings row, creating it (empty — falls back to the
     * built-in generic defaults, see logoUrl()/loadingGifUrl()) if it's
     * somehow missing rather than failing every page load over it.
     *
     * Not firstOrCreate(['id' => 1]): 'id' isn't $fillable (mass-assigning a
     * primary key is asking for trouble in general), so its create() path
     * would silently drop the id and let auto-increment hand out whatever's
     * next — never actually landing on id=1, so every future call would
     * again find nothing at id=1 and create yet another row. Setting id
     * directly (not through mass assignment) sidesteps that.
     */
    public static function current(): self
    {
        return static::find(1) ?? tap(new static(), function (self $setting) {
            $setting->id = 1;
            $setting->save();
        });
    }

    /**
     * Null when no logo is configured — the navbar falls back to the app
     * name as text in that case, not a missing-image icon.
     */
    public function logoUrl(): ?string
    {
        return $this->logo ? asset('branding/' . $this->logo) : null;
    }

    /**
     * Null when no custom loading GIF is configured — the field-suggestion
     * loading indicator falls back to a plain CSS spinner in that case,
     * which (unlike a fixed image) adapts to any institution's colors
     * without needing an uploaded asset at all.
     */
    public function loadingGifUrl(): ?string
    {
        return $this->loading_gif ? asset('branding/' . $this->loading_gif) : null;
    }
}
