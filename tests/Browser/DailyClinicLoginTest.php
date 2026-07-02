<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Daily Clinic Helper — Test 1: Login
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (in plain language):
 *  Opens the real login page in a real browser, types your email + password,
 *  clicks "Sign In", and makes sure you land on the dashboard.
 *
 *  If this ever fails, it means logging in is broken — the single most
 *  important thing for your clinic to keep working.
 *
 *  It uses the CRAWL_EMAIL / CRAWL_PASSWORD you already put in .env, so there
 *  are no passwords written inside this test file.
 */
class DailyClinicLoginTest extends DuskTestCase
{
    public function test_a_user_can_log_in_and_reach_the_dashboard(): void
    {
        $email    = env('CRAWL_EMAIL');
        $password = env('CRAWL_PASSWORD');

        // Safety: skip (don't fail) if credentials aren't configured.
        if (! $email || ! $password) {
            $this->markTestSkipped('Set CRAWL_EMAIL and CRAWL_PASSWORD in .env.dusk.local to run this test.');
        }

        $this->browse(function (Browser $browser) use ($email, $password) {
            $browser->visit('/login')
                    ->waitFor('#email')
                    ->type('#email', $email)
                    ->type('#password', $password)
                    ->click('#login-btn')
                    // After a good login the app redirects to /dashboard.
                    ->waitForLocation('/dashboard', 15)
                    ->assertPathIs('/dashboard');
        });
    }
}
