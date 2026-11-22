<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the configurable branding (logo, loading GIF)
 * added so this platform can be handed off to other institutions instead of
 * shipping one institution's own logo/GIF hardcoded into the templates.
 */
class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_returns_the_row_created_by_the_migration_with_no_logo(): void
    {
        $setting = Setting::current();

        // a new installation has no logo of its own: it shows the name of the application until one is uploaded
        $this->assertSame(1, $setting->id);
        $this->assertNull($setting->logo);
        $this->assertNull($setting->loading_gif);
    }

    public function test_current_creates_the_row_if_it_is_somehow_missing(): void
    {
        Setting::query()->delete();

        $setting = Setting::current();

        $this->assertSame(1, $setting->id);
        $this->assertNull($setting->logo);
        $this->assertNull($setting->loading_gif);
    }

    public function test_logoUrl_and_loadingGifUrl_are_null_when_unset(): void
    {
        $setting = new Setting(['logo' => null, 'loading_gif' => null]);

        $this->assertNull($setting->logoUrl());
        $this->assertNull($setting->loadingGifUrl());
    }

    public function test_logoUrl_and_loadingGifUrl_point_under_branding_when_set(): void
    {
        $setting = new Setting(['logo' => 'custom_logo.png', 'loading_gif' => 'custom.gif']);

        $this->assertStringEndsWith('/branding/custom_logo.png', $setting->logoUrl());
        $this->assertStringEndsWith('/branding/custom.gif', $setting->loadingGifUrl());
    }
}
