<?php

namespace Tests\Feature\Appointments;

use App\Models\AppSetting;
use App\Models\Module;
use App\Models\Role;
use App\Models\RoleModulePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Appointments\Concerns\InteractsWithAppointments;
use Tests\Feature\Appointments\Concerns\InteractsWithPermissions;
use Tests\TestCase;

/**
 * Slice 3 — per-role appointment data scope
 * (role_module_permissions.data_scope, set in Settings → Roles & Permissions).
 *
 * Resolution order under test:
 *   1. the role's data_scope for the appointments module
 *   2. AppSetting `calendar_doctor_scope` (clinic-wide default)
 *   3. own_default
 * …with two hard guards that no configuration may override: the Clinic Owner
 * always sees everything, and a non-doctor role is never scoped down.
 */
class AppointmentRoleDataScopeTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithAppointments;
    use InteractsWithPermissions;

    private function setRoleScope(string $roleSlug, ?string $scope): void
    {
        $this->seedRoles();

        RoleModulePermission::updateOrCreate(
            [
                'role_id'   => Role::where('slug', $roleSlug)->firstOrFail()->id,
                'module_id' => Module::where('slug', 'appointments')->firstOrFail()->id,
            ],
            ['data_scope' => $scope]
        );
    }

    // ── 1. the role column wins ───────────────────────────────────────────

    public function test_role_scope_all_overrides_the_clinic_default(): void
    {
        AppSetting::set('calendar_doctor_scope', User::APPT_SCOPE_OWN_ONLY, 'calendar');
        $this->setRoleScope('doctor', User::APPT_SCOPE_ALL);

        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);

        $this->assertSame(User::APPT_SCOPE_ALL, $doctor->fresh()->appointmentScope());

        $ids = $this->actingAs($doctor)
            ->getJson(route('appointments.index', ['json' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($theirs->id, $ids);
    }

    public function test_role_scope_own_only_is_enforced_end_to_end(): void
    {
        $this->setRoleScope('doctor', User::APPT_SCOPE_OWN_ONLY);

        $doctor = $this->userForSystemRole('doctor');
        $other  = $this->doctorUser();
        $theirs = $this->makeAppointment(['doctor_id' => $other->id]);
        $mine   = $this->makeAppointment(['doctor_id' => $doctor->id]);

        // The toggle cannot widen a locked scope…
        $ids = $this->actingAs($doctor)
            ->getJson(route('appointments.index', ['json' => 1, 'all_doctors' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);

        // …and the record itself is refused.
        $this->actingAs($doctor)
            ->getJson(route('appointments.quick', $theirs))
            ->assertForbidden();
    }

    // ── 2. fallbacks ──────────────────────────────────────────────────────

    public function test_unset_role_scope_falls_back_to_the_clinic_default(): void
    {
        $this->setRoleScope('doctor', null);
        AppSetting::set('calendar_doctor_scope', User::APPT_SCOPE_OWN_ONLY, 'calendar');

        $this->assertSame(
            User::APPT_SCOPE_OWN_ONLY,
            $this->userForSystemRole('doctor')->appointmentScope()
        );
    }

    public function test_nothing_configured_anywhere_is_own_default(): void
    {
        $this->assertSame(
            User::APPT_SCOPE_OWN_DEFAULT,
            $this->userForSystemRole('doctor')->appointmentScope()
        );
    }

    public function test_a_junk_value_in_the_column_does_not_take_effect(): void
    {
        $this->setRoleScope('doctor', 'everything');

        $this->assertSame(
            User::APPT_SCOPE_OWN_DEFAULT,
            $this->userForSystemRole('doctor')->appointmentScope()
        );
    }

    // ── 3. guards no configuration may override ───────────────────────────

    public function test_front_desk_is_never_scoped_down_even_if_configured(): void
    {
        // Misconfiguring a non-doctor role must not blank reception's calendar:
        // the scope filters on "appointments where I am the doctor", which for
        // Front Desk is always nothing.
        $this->setRoleScope('front_desk', User::APPT_SCOPE_OWN_ONLY);

        $frontDesk = $this->userForSystemRole('front_desk');
        $appt      = $this->makeAppointment();

        $this->assertSame(User::APPT_SCOPE_ALL, $frontDesk->appointmentScope());

        $ids = $this->actingAs($frontDesk)
            ->getJson(route('appointments.index', ['json' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($appt->id, $ids);
    }

    public function test_clinic_owner_is_never_scoped_down_even_if_configured(): void
    {
        $this->setRoleScope('admin', User::APPT_SCOPE_OWN_ONLY);
        AppSetting::set('calendar_doctor_scope', User::APPT_SCOPE_OWN_ONLY, 'calendar');

        $owner = $this->userForSystemRole('admin', ['name' => 'Dr. Firke']);
        $appt  = $this->makeAppointment();

        $this->assertSame(User::APPT_SCOPE_ALL, $owner->appointmentScope());

        $ids = $this->actingAs($owner)
            ->getJson(route('appointments.index', ['json' => 1]))
            ->assertOk()
            ->json('*.id');

        $this->assertContains($appt->id, $ids);
    }

    // ── 4. the Settings round trip ────────────────────────────────────────

    public function test_saving_roles_persists_the_scope_and_keeps_can_settings(): void
    {
        $this->seedRoles();
        $role   = Role::where('slug', 'doctor')->firstOrFail();
        $module = Module::where('slug', 'appointments')->firstOrFail();

        RoleModulePermission::updateOrCreate(
            ['role_id' => $role->id, 'module_id' => $module->id],
            ['can_view' => true, 'can_edit' => true, 'can_delete' => false, 'can_settings' => true]
        );

        $this->actingAs($this->userForSystemRole('admin'))
            ->postJson(route('hr.roles.update', $role), [
                'permissions' => [
                    'appointments' => [
                        'view' => true, 'edit' => true, 'delete' => false,
                        'settings' => true, 'scope' => 'own_only',
                    ],
                ],
            ])
            ->assertOk();

        $perm = RoleModulePermission::where('role_id', $role->id)
            ->where('module_id', $module->id)
            ->firstOrFail();

        $this->assertSame('own_only', $perm->data_scope);
        // Regression guard: this screen posts the whole permission map back, so
        // a key the page forgets to send is written as false. can_settings was
        // being wiped that way before 2026-08-26.
        $this->assertTrue((bool) $perm->can_settings);
    }

    public function test_blank_scope_clears_back_to_the_clinic_default(): void
    {
        $this->seedRoles();
        $role   = Role::where('slug', 'doctor')->firstOrFail();
        $module = Module::where('slug', 'appointments')->firstOrFail();

        $this->setRoleScope('doctor', User::APPT_SCOPE_OWN_ONLY);

        $this->actingAs($this->userForSystemRole('admin'))
            ->postJson(route('hr.roles.update', $role), [
                'permissions' => [
                    'appointments' => ['view' => true, 'edit' => true, 'delete' => false, 'scope' => ''],
                ],
            ])
            ->assertOk();

        $this->assertNull(
            RoleModulePermission::where('role_id', $role->id)
                ->where('module_id', $module->id)
                ->value('data_scope')
        );
    }

    public function test_an_invalid_scope_is_rejected_by_validation(): void
    {
        $this->seedRoles();
        $role = Role::where('slug', 'doctor')->firstOrFail();

        $this->actingAs($this->userForSystemRole('admin'))
            ->postJson(route('hr.roles.update', $role), [
                'permissions' => [
                    'appointments' => ['view' => true, 'scope' => 'everything'],
                ],
            ])
            ->assertStatus(422);
    }
}
