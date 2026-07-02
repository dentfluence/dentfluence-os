<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The home page is auth-protected, so an unauthenticated visitor to "/"
     * is redirected to the login screen (HTTP 302). This confirms the app
     * boots and the guard is active.
     */
    public function test_guest_is_redirected_from_home(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }
}
