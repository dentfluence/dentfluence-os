<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ─────────────────────────────────────────────────────────────────────────
 *  Treatments module — Add Treatment + auto-draft-SOP automation
 * ─────────────────────────────────────────────────────────────────────────
 *
 *  WHAT THIS CHECKS (plain language):
 *  When a new treatment is added to the clinic knowledge base, two things
 *  must happen automatically:
 *    1. the treatment is saved, and
 *    2. a blank DRAFT SOP (standard operating procedure) is auto-created so
 *       the SOP tab is ready to fill in.
 *  If step 2 silently breaks, every new treatment would be missing its SOP.
 */
class TreatmentCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_treatment_also_creates_a_draft_sop(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\CheckModulePermission::class);

        $user = User::factory()->create(['branch_id' => 1]);

        $category = TreatmentCategory::create([
            'name'          => 'Dusk Category',
            'billing_basis' => 'gross',
        ]);

        $name = 'DuskTreatment' . now()->format('His');

        $resp = $this->actingAs($user)->post(route('treatments.store'), [
            'treatment_category_id'    => $category->id,
            'name'                     => $name,
            'default_duration_minutes' => 30,
            'default_price'            => 1500,
            'gst_pct'                  => 18,
        ]);
        $resp->assertSessionHasNoErrors();

        // 1. Treatment saved
        $this->assertDatabaseHas('treatments', ['name' => $name]);

        // 2. A draft SOP was auto-created for it
        $treatment = Treatment::where('name', $name)->first();
        $this->assertDatabaseHas('treatment_sops', [
            'treatment_id' => $treatment->id,
            'version'      => 1,
            'status'       => 'draft',
        ]);
    }
}
