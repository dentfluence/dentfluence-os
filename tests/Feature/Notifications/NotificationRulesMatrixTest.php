<?php

namespace Tests\Feature\Notifications;

use App\Models\AppNotification;
use App\Models\Consultation;
use App\Models\NotificationRule;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\NotificationCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * N-4 — the Settings → Notifications matrix is the authority, and only the
 * admin holds the pen.
 *
 *  - saving the matrix writes an explicit rule for every posted cell and the
 *    dispatcher obeys it on the very next event;
 *  - push is recorded only at popup level, whatever box was ticked;
 *  - unknown events / roles and an Owner cell on an owner-less event are
 *    dropped, never stored;
 *  - a non-admin — even one with settings edit — cannot save.
 */
class NotificationRulesMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin   = $this->userWithRole(Role::ADMIN, 'admin');
        $this->manager = $this->userWithRole(Role::MANAGER, 'manager');
    }

    private function userWithRole(string $slug, string $legacy): User
    {
        return User::factory()->create([
            'role'      => $legacy,
            'role_id'   => Role::where('slug', $slug)->firstOrFail()->id,
            'branch_id' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_saves_the_matrix_and_the_dispatcher_obeys_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.notifications.save'), [
                'rules' => [
                    'consultation__saved' => [
                        Role::FRONT_DESK => ['level' => 'bell', 'push' => '1'],   // demoted; push must be dropped
                        Role::MANAGER    => ['level' => 'popup', 'push' => '1'],  // promoted
                        Role::DOCTOR     => ['level' => 'off'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('notification_rules', ['event_key' => 'consultation.saved', 'role' => Role::FRONT_DESK, 'level' => 'bell', 'push' => 0]);
        $this->assertDatabaseHas('notification_rules', ['event_key' => 'consultation.saved', 'role' => Role::MANAGER, 'level' => 'popup', 'push' => 1]);
        $this->assertDatabaseHas('notification_rules', ['event_key' => 'consultation.saved', 'role' => Role::DOCTOR, 'level' => 'off']);

        $effective = NotificationRule::effectiveFor('consultation.saved', 1);
        $this->assertSame('bell', $effective[Role::FRONT_DESK]['level']);
        $this->assertSame('popup', $effective[Role::MANAGER]['level']);
        $this->assertArrayNotHasKey(Role::DOCTOR, $effective->all(), 'off rules are invisible to the dispatcher');

        // Now the engine follows the matrix, not the catalogue.
        $desk    = $this->userWithRole(Role::FRONT_DESK, 'front_desk');
        $doctor  = $this->userWithRole(Role::DOCTOR, 'doctor');
        $patient = Patient::create(['name' => 'Matrix Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);

        $this->actingAs($doctor);
        Consultation::create([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'branch_id' => 1,
            'consultation_date' => now()->toDateString(), 'chief_complaint' => 'x',
        ]);

        $this->assertSame('bell', AppNotification::where('user_id', $desk->id)->firstOrFail()->priority);
        $this->assertSame('popup', AppNotification::where('user_id', $this->manager->id)->firstOrFail()->priority);
        $this->assertTrue(AppNotification::where('user_id', $this->manager->id)->firstOrFail()->push);
    }

    public function test_unknown_events_roles_and_ownerless_owner_cells_are_dropped(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.notifications.save'), [
                'rules' => [
                    'not__an__event'     => [Role::ADMIN => ['level' => 'popup']],
                    'payment__received'  => ['janitor' => ['level' => 'popup'], NotificationCatalog::OWNER => ['level' => 'popup']],
                    'invoice__cancelled' => [Role::ADMIN => ['level' => 'bell']],
                ],
            ])
            ->assertRedirect();

        $this->assertSame(1, NotificationRule::count(), 'only the one legitimate cell was stored');
        $this->assertDatabaseHas('notification_rules', ['event_key' => 'invoice.cancelled', 'role' => Role::ADMIN, 'level' => 'bell']);
    }

    public function test_an_invalid_level_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('settings.index'))
            ->post(route('settings.notifications.save'), [
                'rules' => ['consultation__saved' => [Role::FRONT_DESK => ['level' => 'shout']]],
            ])
            ->assertSessionHasErrors('rules.consultation__saved.front_desk.level');

        $this->assertSame(0, NotificationRule::count());
    }

    public function test_only_admin_can_save_the_matrix(): void
    {
        $this->actingAs($this->manager)
            ->post(route('settings.notifications.save'), [
                'rules' => ['consultation__saved' => [Role::FRONT_DESK => ['level' => 'off']]],
            ]);

        $this->assertSame(0, NotificationRule::count(), 'a manager cannot silence the front desk');
    }

    public function test_the_dot_encoding_round_trips(): void
    {
        // 🪤 Laravel resolves a validation attribute by splitting on dots, so a
        // field literally named rules[consultation.saved][...] is unreachable —
        // every cell would validate as null. The form encodes the dot; this
        // pins that the controller decodes it back to a real catalogue event.
        $this->actingAs($this->admin)
            ->post(route('settings.notifications.save'), [
                'rules' => ['lab__draft_stale' => [Role::MANAGER => ['level' => 'popup', 'push' => '1']]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_rules', [
            'event_key' => 'lab.draft_stale', 'role' => Role::MANAGER, 'level' => 'popup', 'push' => 1,
        ]);
    }

    public function test_the_settings_page_renders_the_matrix_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.index', ['tab' => 'notifications']))
            ->assertOk()
            ->assertSee('Who is told what')
            ->assertSee('rules[consultation__saved][front_desk][level]', false)
            ->assertSee('rules[invoice__created][owner][level]', false)
            ->assertDontSee('rules[payment__received][owner][level]', false);
    }
}
