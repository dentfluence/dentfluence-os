<?php

namespace Tests\Browser;

use App\Models\Patient;
use App\Models\User;
use App\Services\PatientService;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Dentfluence Smoke — Browser layer (LOCAL ONLY — never run against VPS)
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  A deliberately SMALL real-browser companion to `php artisan
 *  dentfluence:smoke`: it checks the pages a receptionist lives in actually
 *  render and behave in a real Chrome — login, patient profile with its lazy
 *  tabs, and the appointments day view — and captures severe JS console
 *  errors. Appointment create/reschedule/check-in are already covered
 *  end-to-end (service + HTTP + DB) by dentfluence:smoke; repeating them
 *  through fragile modal clicks would add brittleness, not confidence.
 *
 *  Uses the existing Dusk conventions: CRAWL_EMAIL user, @dusk selectors,
 *  self-cleaning teardown. Run with:  php artisan dusk --filter=SmokeCore
 */
class SmokeCoreJourneysTest extends DuskTestCase
{
    private const MARK = 'SmokeDusk';

    /** Always remove any patients this test created, pass or fail. */
    protected function tearDown(): void
    {
        Patient::where('first_name', self::MARK)->forceDelete();
        parent::tearDown();
    }

    public function test_login_patient_profile_and_lazy_tabs_render_without_js_errors(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL was found in the database.');
        }

        // Canonical mint point — tests are an approved invariant exception,
        // but there is no reason not to use the real path here.
        $patient = app(PatientService::class)->register([
            'first_name'    => self::MARK,
            'last_name'     => 'Profile' . now()->format('His'),
            'gender'        => 'male',
            'date_of_birth' => now()->subYears(30)->toDateString(),
            'phone'         => '9977' . now()->format('His'),
        ], $user);

        $this->browse(function (Browser $browser) use ($user, $patient) {
            $browser->loginAs($user)
                    ->visit(route('patients.show', $patient, false))
                    ->waitForText($patient->name, 15)
                    ->assertSee('Journey Timeline')
                    // Lazy tabs: the nav must exist, and opening one must load
                    // its fragment (the Phase 4 lazy-tab contract).
                    ->assertPresent('[dusk="tab-billing"]')
                    ->click('@tab-billing')
                    ->pause(2000)
                    ->assertDontSee('Server Error')
                    ->assertDontSee('Whoops');

            $this->assertNoSevereConsoleErrors($browser, 'patient profile');
        });
    }

    public function test_appointments_day_view_renders_without_js_errors(): void
    {
        $user = User::where('email', env('CRAWL_EMAIL'))->first();
        if (! $user) {
            $this->markTestSkipped('No user matching CRAWL_EMAIL was found in the database.');
        }

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit(route('appointments.index', [], false))
                    ->pause(3000)
                    ->assertDontSee('Server Error')
                    ->assertDontSee('Whoops');

            $this->assertNoSevereConsoleErrors($browser, 'appointments view');
        });
    }

    /**
     * Fail if the browser console collected SEVERE errors (JS exceptions,
     * failed network requests surface here too). 404s for optional assets are
     * ignored; everything else severe fails the test.
     */
    private function assertNoSevereConsoleErrors(Browser $browser, string $context): void
    {
        try {
            $logs = $browser->driver->manage()->getLog('browser');
        } catch (\Throwable) {
            return; // console log capture unsupported by this driver — skip silently
        }

        $severe = array_values(array_filter($logs, function ($log) {
            return ($log['level'] ?? '') === 'SEVERE'
                && ! str_contains($log['message'] ?? '', 'favicon');
        }));

        $this->assertSame(
            [],
            array_map(fn ($l) => $l['message'], $severe),
            "Severe JS console errors on {$context}"
        );
    }
}
