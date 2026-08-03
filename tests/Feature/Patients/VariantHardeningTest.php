<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Patient Management — Variants stage regression suite (2026-08-03).
 *
 * Locks in every fix from the Variants hardening pass:
 *   1. PM-003 — relationship-notes / opportunities DELETE routes need the delete tier.
 *   2. tags-routes.php override retired — patient tag writes are module-gated again.
 *   3. PM-007+ — CSV import needs patients,edit; export is admin-tier middleware.
 *   4. API list/search/same-issue-context/COHA reads need the patients View flag.
 *   5. Route-table guard: every patients.* web route declares a module/admin gate.
 *   6. gender=prefer_not_to_say rejected by validation (DB enum can't store it).
 *   7. Future date_of_birth rejected (negative age / false DPDP-minor flag).
 *   8. phone cannot be blanked via partial update.
 *   9. PM-006 — duplicate-phone detection matches across formatting/country code.
 *  10. Print view renders for patients with array-cast medical data; merged → 404.
 */
class VariantHardeningTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function patient(array $overrides = []): Patient
    {
        return Patient::create(array_merge([
            'name'      => 'Variant Test Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ], $overrides));
    }

    // ── 1. PM-003: delete-tier gates ─────────────────────────────────────────

    public function test_relationship_note_and_opportunity_deletes_require_delete_tier(): void
    {
        $editOnly = $this->userWithModulePerm('patients', true, true, false);
        $patient  = $this->patient();

        $this->actingAs($editOnly)
            ->delete(route('patients.relationship-notes.destroy', [$patient, 1]))
            ->assertRedirect(); // 302 access-denied, never reaches the controller

        $this->actingAs($editOnly)
            ->delete(route('patients.opportunities.destroy', [$patient, 1]))
            ->assertRedirect();

        // Delete tier gets past the gate (404 = gate passed, record absent).
        $withDelete = $this->userWithModulePerm('patients', true, true, true);
        $this->actingAs($withDelete)
            ->delete(route('patients.relationship-notes.destroy', [$patient, 999]))
            ->assertNotFound();
        $this->actingAs($withDelete)
            ->delete(route('patients.opportunities.destroy', [$patient, 999]))
            ->assertNotFound();
    }

    // ── 2. Tag route override regression ─────────────────────────────────────

    public function test_patient_tag_attach_is_module_gated_not_auth_only(): void
    {
        // Route-table introspection: the registered route must carry the module
        // gate. This is what catches a later-loaded file silently replacing the
        // gated registration (the tags-routes.php bug).
        $route = Route::getRoutes()->getByName('patients.tags.attach');
        $this->assertNotNull($route);
        $this->assertContains('module:patients,edit', $route->gatherMiddleware(),
            'patients.tags.attach lost its module gate — check for duplicate route registrations.');

        // Behavioral check: a zero-permission user is denied.
        $zero    = $this->zeroPermUser();
        $patient = $this->patient();
        $this->actingAs($zero)
            ->post(route('patients.tags.attach', $patient), ['tag' => 'vip'])
            ->assertRedirect();
    }

    // ── 3. Import / export perimeter ─────────────────────────────────────────

    public function test_csv_import_requires_patients_edit_not_just_settings_view(): void
    {
        $settingsViewOnly = $this->userWithModulePerm('settings', true, false, false);

        $this->actingAs($settingsViewOnly)
            ->post(route('settings.data.import.store'))
            ->assertRedirect(); // access denied at the patients,edit gate

        $this->assertContains('module:patients,edit',
            Route::getRoutes()->getByName('settings.data.import.store')->gatherMiddleware());
        $this->assertContains('module:patients,edit',
            Route::getRoutes()->getByName('settings.data.import.preview')->gatherMiddleware());
    }

    public function test_csv_export_is_admin_tier_at_the_middleware(): void
    {
        $this->assertContains('admin.only',
            Route::getRoutes()->getByName('settings.data.export')->gatherMiddleware());

        $settingsViewOnly = $this->userWithModulePerm('settings', true, false, false);
        $this->actingAs($settingsViewOnly)
            ->get(route('settings.data.export'))
            ->assertRedirect(); // 302 denial semantics, no longer a raw 403
    }

    // ── 4. API PHI reads require the View flag ───────────────────────────────

    public function test_api_patient_list_and_search_require_view_flag(): void
    {
        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $this->getJson('/api/v1/patients')->assertForbidden();
        $this->getJson('/api/v1/patients/search?q=test')->assertForbidden();

        Sanctum::actingAs($this->userWithModulePerm('patients', true, false, false), ['*']);
        $this->getJson('/api/v1/patients')->assertOk();
        $this->getJson('/api/v1/patients/search?q=test')->assertOk();
    }

    public function test_api_clinical_context_reads_require_view_flag(): void
    {
        $patient = $this->patient();

        Sanctum::actingAs($this->zeroPermUser(), ['*']);
        $this->getJson("/api/v1/patients/{$patient->id}/consultations/same-issue-context")
            ->assertForbidden();
        $this->getJson('/api/v1/coha/999')->assertForbidden();
    }

    // ── 5. Route-table guard — no patients.* route without a gate ────────────

    public function test_every_patients_web_route_declares_a_module_or_admin_gate(): void
    {
        $ungated = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (! $name || ! str_starts_with($name, 'patients.')) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $gated = collect($middleware)->contains(
                fn ($m) => str_starts_with($m, 'module:') || $m === 'admin.only'
            );
            if (! $gated) {
                $ungated[] = $name . ' [' . implode('|', $route->methods()) . ' /' . $route->uri() . ']';
            }
        }

        $this->assertSame([], $ungated,
            'Ungated patients.* routes found (a later route file may be overriding gated registrations): '
            . implode(', ', $ungated));
    }

    // ── 5b. Money surfaces follow the finance View flag ──────────────────────

    public function test_billing_and_wallet_tabs_require_finance_view(): void
    {
        $patient = $this->patient();

        // patients view+edit but NO finance (the default Doctor shape).
        $noFinance = $this->userWithModulePerm('patients', true, true, false);
        $this->actingAs($noFinance)
            ->get(route('patients.tab', [$patient, 'billing']))->assertForbidden();
        $this->actingAs($noFinance)
            ->get(route('patients.tab', [$patient, 'wallet']))->assertForbidden();

        // patients + finance view — sees both money tabs.
        $withFinance = $this->userWithTwoModulePerms(
            'patients', [true, true, false],
            'finance',  [true, false, false]
        );
        $this->actingAs($withFinance)
            ->get(route('patients.tab', [$patient, 'billing']))->assertOk();
        $this->actingAs($withFinance)
            ->get(route('patients.tab', [$patient, 'wallet']))->assertOk();
    }

    public function test_timeline_permission_strings_reference_real_module_slugs(): void
    {
        // Guard against dead permission slugs: 'billing.view' / 'consent.view'
        // referenced modules that never existed in the catalogue, silently
        // hiding those events from every non-admin. Every "module.action"
        // string in the clinical timeline adapters must resolve to a real
        // module slug (fixed 2026-08-03).
        $this->seedAccessRoles();

        $source = file_get_contents(
            app_path('Services/Relationship/UnifiedTimelineService.php')
        );
        preg_match_all("/'permission'\s*=>\s*'([a-z_]+)\./", $source, $m);
        $slugs = array_unique($m[1]);
        $this->assertNotEmpty($slugs);

        foreach ($slugs as $slug) {
            $this->assertNotNull(
                \App\Models\Module::where('slug', $slug)->first(),
                "Timeline permission references nonexistent module slug [$slug] — those events would be hidden from every non-admin."
            );
        }
    }

    // ── 6-7. Validation: gender enum + future DOB ────────────────────────────

    public function test_prefer_not_to_say_gender_is_rejected_by_validation_not_the_database(): void
    {
        $editor = $this->userWithModulePerm('patients', true, true, false);

        $this->actingAs($editor)->post(route('patients.store'), [
            'first_name' => 'Enum', 'last_name' => 'Check',
            'phone' => '9812345001', 'age_years' => 30,
            'gender' => 'prefer_not_to_say',
        ])->assertSessionHasErrors('gender');
    }

    public function test_garbage_phone_is_rejected_on_create(): void
    {
        $editor = $this->userWithModulePerm('patients', true, true, false);

        $this->actingAs($editor)->post(route('patients.store'), [
            'first_name' => 'Bad', 'last_name' => 'Phone',
            'phone' => 'abc', 'age_years' => 30,
        ])->assertSessionHasErrors('phone');

        // Formatted real numbers still pass the phone rule.
        $this->actingAs($editor)->post(route('patients.store'), [
            'first_name' => 'Good', 'last_name' => 'Phone',
            'phone' => '+91 98123-45004', 'age_years' => 30,
        ])->assertSessionDoesntHaveErrors('phone');
    }

    public function test_future_date_of_birth_is_rejected(): void
    {
        $editor = $this->userWithModulePerm('patients', true, true, false);

        $this->actingAs($editor)->post(route('patients.store'), [
            'first_name' => 'Future', 'last_name' => 'Dob',
            'phone' => '9812345002',
            'date_of_birth' => now()->addYear()->toDateString(),
        ])->assertSessionHasErrors('date_of_birth');
    }

    // ── 8. Phone cannot be blanked via partial update ────────────────────────

    public function test_update_rejects_empty_phone_but_allows_omitting_it(): void
    {
        $editor  = $this->userWithModulePerm('patients', true, true, false);
        $patient = $this->patient(['phone' => '9812345003']);

        $this->actingAs($editor)
            ->patch(route('patients.update', $patient), ['phone' => ''])
            ->assertSessionHasErrors('phone');

        $this->assertSame('9812345003', $patient->fresh()->phone);

        // Omitting phone entirely is a legal partial update.
        $this->actingAs($editor)
            ->patch(route('patients.update', $patient), ['city' => 'Nagpur'])
            ->assertSessionDoesntHaveErrors('phone');
        $this->assertSame('9812345003', $patient->fresh()->phone);
    }

    // ── 9. PM-006: normalized duplicate-phone detection ──────────────────────

    public function test_duplicate_phone_detection_survives_formatting_and_country_code(): void
    {
        $this->patient(['name' => 'Formatted Phone', 'phone' => '+91 98765 43210']);
        $service = app(PatientService::class);

        // All of these are the same subscriber and must now be detected.
        foreach (['9876543210', '+919876543210', '098765-43210', '98765 43210'] as $variant) {
            $this->assertCount(1, $service->findDuplicatesByPhone($variant, 1),
                "Variant [$variant] failed to match stored '+91 98765 43210'");
        }

        // Exact match still works (old behavior is a strict subset).
        $this->patient(['name' => 'Plain Phone', 'phone' => '9822011122']);
        $this->assertCount(1, $service->findDuplicatesByPhone('9822011122', 1));

        // A different number does not false-positive.
        $this->assertCount(0, $service->findDuplicatesByPhone('9000000000', 1));
    }

    // ── 10. Print view: array casts render, merged records don't print ───────

    public function test_print_renders_for_patient_with_array_medical_data(): void
    {
        $viewer  = $this->userWithModulePerm('patients', true, false, false);
        $patient = $this->patient([
            'allergies'          => ['Penicillin', 'Latex'],
            'medical_conditions' => ['Diabetes'],
        ]);

        $this->actingAs($viewer)
            ->get(route('patients.print', $patient))
            ->assertOk()
            ->assertSee('Penicillin');
    }

    public function test_print_is_blocked_for_merged_patients(): void
    {
        $viewer   = $this->userWithModulePerm('patients', true, false, false);
        $survivor = $this->patient(['name' => 'Survivor']);
        $merged   = $this->patient(['name' => 'Merged Away']);
        Patient::whereKey($merged->id)->update(['merged_into_id' => $survivor->id]);

        $this->actingAs($viewer)
            ->get(route('patients.print', $merged))
            ->assertNotFound();
    }
}
