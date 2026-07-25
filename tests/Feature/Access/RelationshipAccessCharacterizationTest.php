<?php

namespace Tests\Feature\Access;

use App\Models\Lead;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * Phase 1 · Slice 1.1 — CURRENT PRE/Relationship access reality.
 *
 * Every test here documents behavior as it IS today: routes/relationship.php
 * carries auth-only (no module gate), so a user whose owner-configured role
 * grants NOTHING can still read every PRE surface (all patient names/phones),
 * execute board mutations, and convert leads into patients.
 *
 * These assertions are the evidence for the 1.1 CEO gate and will be
 * DELIBERATELY FLIPPED in Slice 1.3 once enforcement lands. Do not "fix"
 * them before then — a green run means the characterization is accurate.
 */
class RelationshipAccessCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    public function test_zero_permission_user_can_open_every_pre_surface(): void
    {
        $user = $this->zeroPermUser();

        foreach ([
            'relationship.today',
            'relationship.reception',
            'relationship.pipeline',
            'relationship.list',
            'relationship.recalls',
            'relationship.opportunities',
        ] as $routeName) {
            $this->actingAs($user)
                ->get(route($routeName))
                ->assertOk(); // CURRENT: 200 with zero grants
        }
    }

    public function test_zero_permission_user_can_execute_a_board_mutation(): void
    {
        $user = $this->zeroPermUser();

        $this->actingAs($user)
            ->postJson(route('relationship.today.close'), [
                'category' => 'recall_calls',
                'notes'    => 'closed by zero-perm user (characterization)',
            ])
            ->assertOk(); // CURRENT: mutation allowed with zero grants
    }

    public function test_zero_permission_user_can_convert_a_lead_into_a_patient(): void
    {
        // The register-invariant hole flagged by the journey audit: conversion
        // (which mints a Patient) is reachable with NO patients permission and
        // NO PRE permission — only a login.
        $user = $this->zeroPermUser();

        $lead = Lead::create([
            'name'  => 'Convert Characterization Lead',
            'phone' => '9' . random_int(100000000, 999999999),
            'stage' => 'new_lead',
        ]);

        $before = Patient::count();

        $this->actingAs($user)
            ->postJson(route('relationship.pipeline.convert', $lead->id))
            ->assertOk(); // CURRENT: allowed

        $this->assertSame('converted', $lead->fresh()->stage);
        $this->assertGreaterThan($before, Patient::count(),
            'conversion minted a patient for a user with zero grants (current reality)');
    }
}
