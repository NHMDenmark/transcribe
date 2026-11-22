<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Every page in this app sits behind the 'auth' middleware group, so an
     * unauthenticated visit redirects to login rather than rendering.
     */
    public function test_an_unauthenticated_visit_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
