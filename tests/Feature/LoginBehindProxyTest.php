<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\User_categories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logging in, on its own and behind a reverse proxy. The proxy is trusted through config('proxies.trusted')
 * (TRUST_PROXIES), so that a cached configuration cannot turn it off.
 */
class LoginBehindProxyTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = [
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'transcription.example.org',
        'HTTP_X_FORWARDED_PORT' => '443',
        'REMOTE_ADDR' => '10.1.2.3',
    ];

    private function volunteer(): User
    {
        $cat = new User_categories();
        $cat->id = 5;
        $cat->category = 'level 5';
        $cat->points = 20;
        $cat->save();

        $user = new User();
        $user->category = 5;
        $user->role = 'user';
        $user->name = 'Volunteer';
        $user->email = 'v@example.test';
        $user->password = bcrypt('secret-pass');
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    private function logIn(array $server = [])
    {
        return $this->withServerVariables($server)->post('/login', ['email' => 'v@example.test', 'password' => 'secret-pass']);
    }

    public function test_logging_in_works_and_a_wrong_password_says_so(): void
    {
        $this->volunteer();

        $this->post('/login', ['email' => 'v@example.test', 'password' => 'nope'])->assertSessionHasErrors();
        $this->assertGuest();

        $this->logIn()->assertRedirect();
        $this->assertAuthenticated();
        $this->get('/projects')->assertOk();
    }

    public function test_behind_a_trusted_proxy_the_login_goes_back_to_the_public_address(): void
    {
        $this->volunteer();
        config(['proxies.trusted' => '*']);

        $this->withServerVariables(self::PROXY)->get('/login')->assertOk();
        $response = $this->logIn(self::PROXY);

        $this->assertAuthenticated();
        $response->assertRedirect('https://transcription.example.org/home');
    }

    public function test_a_list_of_proxy_addresses_is_trusted_for_those_addresses(): void
    {
        $this->volunteer();
        config(['proxies.trusted' => '10.9.9.9, 10.1.2.3']);

        $this->logIn(self::PROXY)->assertRedirect('https://transcription.example.org/home');
    }

    public function test_the_same_headers_from_an_address_that_is_not_in_the_list_are_not_believed(): void
    {
        $this->volunteer();
        config(['proxies.trusted' => '10.9.9.9, 10.1.2.3']);

        $this->logIn(array_merge(self::PROXY, ['REMOTE_ADDR' => '203.0.113.7']))->assertRedirect('http://localhost/home');
    }
    public function test_without_a_trusted_proxy_the_forwarded_headers_are_ignored(): void
    {
        $this->volunteer();
        config(['proxies.trusted' => null]);

        $this->logIn(self::PROXY)->assertRedirect('http://localhost/home');
        $this->assertAuthenticated();
    }


    // ---- a proxy the application does not recognise ------------------------------------------------------

    private function loginFormAction(): string
    {
        $html = $this->get('/login')->assertOk()->getContent();
        preg_match('/<form[^>]*method="POST"[^>]*action="([^"]*login)"/i', $html, $m);

        return html_entity_decode($m[1] ?? '');
    }

    public function test_the_login_form_posts_to_http_when_the_application_thinks_it_is_http(): void
    {
        config(['app.url' => 'http://localhost', 'proxies.trusted' => null]);
        (new \App\Providers\AppServiceProvider($this->app))->forcePublicScheme();

        $this->assertStringStartsWith('http://', $this->loginFormAction());
    }

    public function test_with_an_https_public_address_the_login_form_posts_to_https_even_if_the_proxy_is_not_recognised(): void
    {
        // not trusted, and the request itself arrives as http: this is what a proxy that ends https looks like
        config(['app.url' => 'https://transcription.example.org', 'proxies.trusted' => null]);
        (new \App\Providers\AppServiceProvider($this->app))->forcePublicScheme();

        $this->assertStringStartsWith('https://', $this->loginFormAction());
    }

    public function test_every_address_the_application_builds_follows_the_public_scheme(): void
    {
        config(['app.url' => 'https://transcription.example.org']);
        (new \App\Providers\AppServiceProvider($this->app))->forcePublicScheme();

        $this->assertStringStartsWith('https://', route('login'));
        $this->assertStringStartsWith('https://', url('/projects'));
        $this->assertStringStartsWith('https://', asset('build/app.css'));
    }
}
