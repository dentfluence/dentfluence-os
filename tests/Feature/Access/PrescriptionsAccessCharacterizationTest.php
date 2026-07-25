<?php

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.1 — CURRENT prescriptions access reality.
 *
 * routes/prescriptions.php is wrapped in bare auth middleware: no module
 * gate exists (and no prescriptions module row exists to gate with).
 * Will be flipped in Slice 1.3 after the CEO approves semantics.
 */
class PrescriptionsAccessCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_zero_permission_user_can_open_prescription_settings(): void
    {
        $user = $this->zeroPermUser();

        $this->actingAs($user)
            ->get(route('rx.settings.index'))
            ->assertOk(); // CURRENT: 200 with zero grants
    }

    public function test_no_prescription_route_carries_a_module_gate(): void
    {
        $rxRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'rx.'));

        $this->assertTrue($rxRoutes->isNotEmpty(), 'rx.* routes missing');

        foreach ($rxRoutes as $r) {
            $moduleGates = array_filter($r->gatherMiddleware(),
                fn ($m) => is_string($m) && str_starts_with($m, 'module:'));

            $this->assertSame([], array_values($moduleGates),
                "Route [{$r->getName()}] unexpectedly gained a module gate — update this characterization");
        }
    }
}
