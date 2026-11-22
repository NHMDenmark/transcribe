<?php

namespace App\Providers;

use App;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use App\Models\User_categories;
use App\Models\Language;
use App\Models\Setting;
use App\Services\DialogTextRepository;
use Laravel\Sanctum\Sanctum;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // This app ships its own personal_access_tokens migration
        // (database/migrations/..._create_personal_access_tokens_table.php,
        // reverse-engineered from the existing schema); without this,
        // Sanctum's bundled migration of the same table collides with it
        // ("Base table or view already exists") the moment `migrate` is run
        // against a genuinely empty database.
        Sanctum::ignoreMigrations();

        // Shared singleton so this and the get_dialogs()/get_dialogs_html()/
        // get_dialogs_simple() helpers (app/helpers.php) query the dialogs
        // table at most once per language per request, instead of each
        // fetching it independently.
        $this->app->singleton(DialogTextRepository::class);

        // the menu tree is read (and cached) once per request, and the texts of pages go through the same repository
        $this->app->singleton(\App\Services\ContentTexts::class);
        $this->app->singleton(\App\Services\SiteNavigation::class);
        $this->app->singleton(\App\Services\SiteFooter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();
        Schema::defaultStringLength(191);
        $this->forcePublicScheme();

        // These used to be eager view()->share() calls here in boot(), which ran
        // 3 queries (dialogs' own query duplicated once more, for its stripped
        // and raw variants) on every single request — including JSON/CSV
        // endpoints (e.g. /validated/json, /validate/manual/*) that never render
        // a Blade view and so never touch any of this data. A wildcard view
        // composer only fires when a view actually gets rendered, and the
        // request-local cache keeps the underlying queries to one run each even
        // though @extends/@include make Blade compose more than one View
        // instance per response.
        View::composer('*', function ($view) {
            static $shared = null;

            if ($shared === null) {
                $shared = $this->sharedViewData();
            }

            $view->with($shared);
        });
    }

    private function sharedViewData(): array
    {
        $data = [];

        if (Schema::hasTable('user_categories')) {
            $data['user_categories'] = $this->user_categories();
        }

        if (Schema::hasTable('languages')) {
            $languages = $this->languages();
            $data['languages'] = $languages;
            $data['active_language'] = $this->active_language($languages);
        }

        if (Schema::hasTable('settings')) {
            // The navbar logo and the field-suggestion loading indicator (see
            // layouts/app.blade.php) both need this on every page, so it's
            // shared here rather than fetched ad hoc — Setting::current() has
            // no memoization of its own, so calling it more than once per
            // request would mean more than one query for the exact same row.
            $data['branding'] = Setting::current();
        }

        // The menu, for whoever is looking. Null until the navigation tables exist (a deploy that has
        // not run its migrations yet), in which case the layout draws the menu it always had.
        if (Schema::hasTable('navigation') && isset($data['active_language'])) {
            $data['nav_menu'] = app(\App\Services\SiteNavigation::class)->menu(auth()->user(), $data['active_language']);
        }

        // The footer, for whoever is looking (same guard: absent until its migration has run).
        if (Schema::hasTable('footer_blocks') && isset($data['active_language'])) {
            $data['footer_blocks'] = app(\App\Services\SiteFooter::class)->blocks(auth()->user(), $data['active_language']);
        }

        // Dialog text itself is no longer preloaded here — get_dialogs()/
        // get_dialogs_simple()/get_dialogs_html() (app/helpers.php) fetch it on
        // demand from the same DialogTextRepository singleton, so it's only ever
        // queried by views that actually call one of them. Every remaining
        // $dialogs[...]/$dialogs_html[...] call site (navbar, flash messages,
        // a couple of others) was migrated to those helpers; the one dead
        // consumer (inc/navbar.blade.php's own copy, never actually @include'd
        // anywhere) was deleted outright instead.

        return $data;
    }

    public function user_categories()
    {
        //
        $user_categories = User_categories::get();
        return $user_categories;
    }

    public function active_language($languages)
    {

        if (isset($_COOKIE['active_language'])) {
            $active_language = $_COOKIE['active_language'];
        } else {
            if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
                $acceptLang = array();
                foreach ($languages as $language) {
                    $acceptLang[] = $language->iso2;
                }

                $lang = in_array($lang, $acceptLang) ? $lang : 'en';

                $active_language = $lang;
            } else {
                $active_language = 'en';
            }
        }
        App::setLocale($active_language);
        return $active_language;
    }

    public function languages()
    {
        $languages = Language::orderBy('id')->get();   // fixed order: the options table and its import format follow it
        return $languages;
    }
    /**
     * Behind a reverse proxy that ends HTTPS the application sees plain http, unless it trusts the proxy and
     * the proxy says otherwise (TRUST_PROXIES, X-Forwarded-Proto). When it does not, every address it builds
     * (a form's action, a redirect, a link) is http://, and a proxy that only accepts https answers the form's
     * POST with a 301 to https: the browser turns it into a GET and the data is lost - a login that "does
     * nothing". The public address (APP_URL) says what the visitor uses: when it is https, so are the addresses.
     */
    public function forcePublicScheme(): void
    {
        if (str_starts_with(strtolower((string) config('app.url')), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
