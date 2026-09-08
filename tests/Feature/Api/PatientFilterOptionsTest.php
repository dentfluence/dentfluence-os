<?php

namespace Tests\Feature\Api;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-4 (Android V1.1) — the phone's patient filter sheet reads its option
 * values from the API instead of hard-coding a copy of the web dropdowns.
 * Areas are the branch's real distinct values, same as the web index.
 */
class PatientFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_options_carry_the_branch_areas_and_the_web_dropdown_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
        foreach (['Dombivli East', 'Dombivli West', 'Dombivli East', ''] as $i => $area) {
            Patient::create(['name' => "P{$i}", 'phone' => '98' . str_pad((string) $i, 8, '0'), 'branch_id' => 1, 'area' => $area ?: null]);
        }
        Patient::create(['name' => 'Other branch', 'phone' => '9700000000', 'branch_id' => 2, 'area' => 'Kalyan']);

        Sanctum::actingAs($admin, ['*']);
        $data = $this->getJson('/api/v1/patients/filter-options')->assertOk()->json('data');

        $this->assertSame(['Dombivli East', 'Dombivli West'], $data['areas']);
        $this->assertContains('Walk-In', $data['sources']);
        $this->assertSame(['active', 'expired', 'not_enrolled'], $data['membership']);
        $this->assertSame(['newest', 'oldest', 'name', 'name_desc'], $data['sort']);
    }
}
