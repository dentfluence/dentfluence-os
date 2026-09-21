<?php

namespace Tests\Feature\Ops;

use App\Models\AppNotification;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * 1.5 — the alert script has watched this box since 19 Sep and has never once
 * reached a phone. These tests pin the route that finally carries it, and in
 * particular pin the two ways it could go wrong quietly:
 *
 *   - opening up (an unauthenticated endpoint that writes notifications), and
 *   - addressing the wrong people (ops alarms landing on a receptionist's phone
 *     because someone typed "admin" into her HR staff-type field — the exact
 *     escalation V.18 closed).
 */
class OpsAlertReachesAdminPhonesTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'severity' => 'CRIT',
            'check'    => 'backup_offsite',
            'text'     => 'last off-site backup is 31 hours old',
            'host'     => 'os',
        ], $override);
    }

    private function secretHeader(string $secret = 'test-secret'): array
    {
        return ['X-Dentfluence-Alert-Secret' => $secret];
    }

    public function test_a_correct_secret_is_accepted(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');

        $this->postJson('/api/v1/ops/alert', $this->payload(), $this->secretHeader())
            ->assertOk();
    }

    public function test_a_wrong_secret_is_refused(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');

        $this->postJson('/api/v1/ops/alert', $this->payload(), $this->secretHeader('wrong'))
            ->assertStatus(401);

        $this->assertSame(0, AppNotification::count());
    }

    public function test_a_missing_secret_is_refused(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');

        $this->postJson('/api/v1/ops/alert', $this->payload())
            ->assertStatus(401);
    }

    /**
     * The one that matters most: an UNSET secret must CLOSE the route, not open
     * it. Failing open here would leave an unauthenticated endpoint that writes
     * a notification to every admin on the internet.
     */
    public function test_an_unset_secret_closes_the_route(): void
    {
        Config::set('alerting.webhook_secret', null);

        $this->postJson('/api/v1/ops/alert', $this->payload(), $this->secretHeader(''))
            ->assertStatus(401);

        $this->assertSame(0, AppNotification::count());
    }

    public function test_the_alert_is_written_for_an_admin(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');

        // role 'admin' with no role_id: the factory's afterCreating hook then
        // attaches the REAL Admin role row, so this exercises production
        // authorization rather than the no-role transition bypass (see 2.6).
        $admin = User::factory()->create(['is_active' => true, 'role' => 'admin', 'role_id' => null]);
        $this->assertTrue($admin->fresh()->isAdminRole());

        $this->postJson('/api/v1/ops/alert', $this->payload(), $this->secretHeader())->assertOk();

        $row = AppNotification::where('user_id', $admin->id)->firstOrFail();
        $this->assertSame('ops.backup_offsite', $row->event_key);
        $this->assertSame('[CRIT] backup_offsite', $row->title);
        $this->assertTrue((bool) $row->push);
    }

    /** A recovery is good news. Good news does not buzz a phone at 3am. */
    public function test_a_recovery_does_not_raise_a_notification(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');
        User::factory()->create(['is_active' => true, 'role' => 'admin', 'role_id' => null]);

        $this->postJson('/api/v1/ops/alert', $this->payload(['severity' => 'OK']), $this->secretHeader())
            ->assertOk()
            ->assertJson(['delivered' => 0, 'skipped' => 'recovery']);

        $this->assertSame(0, AppNotification::count());
    }

    /**
     * V.18 restated for this route: a user whose STAFF TYPE says admin but whose
     * assigned role does not must NOT receive database alarms.
     */
    public function test_a_staff_type_admin_without_the_role_is_not_alerted(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');

        $realAdmin = User::factory()->create(['is_active' => true, 'role' => 'admin', 'role_id' => null]);

        $frontDesk = User::factory()->create([
            'is_active' => true,
            'role'      => 'admin',                       // the staff-type label anyone with HR edit can set
            'role_id'   => Role::firstOrCreate(
                ['slug' => 'front_desk'],
                ['name' => 'Front Desk', 'category' => Role::CATEGORY_STAFF]
            )->id,
        ]);

        $this->postJson('/api/v1/ops/alert', $this->payload(), $this->secretHeader())->assertOk();

        $this->assertDatabaseHas('app_notifications', ['user_id' => $realAdmin->id]);
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $frontDesk->id]);
    }

    /** Garbage in is refused, so a broken caller cannot write junk to every admin. */
    public function test_an_unknown_severity_is_refused(): void
    {
        Config::set('alerting.webhook_secret', 'test-secret');

        $this->postJson('/api/v1/ops/alert', $this->payload(['severity' => 'PANIC']), $this->secretHeader())
            ->assertStatus(422);
    }
}
