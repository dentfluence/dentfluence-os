<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — Workflow Engine, Slice 5: second template.
 *
 * Seeds `implant_staging`, mirroring `treatments.stages` for the "Single
 * Dental Implant" record (code IMP-01) in DentalTreatmentsMasterSeeder.php:
 * planning -> implant_surgery -> healing -> abutment -> crown -> review.
 *
 * Same caveat as `rct_staging` (see 2026_07_03_900001_...): the
 * `min_gap_days_from_previous` values are placeholder clinical estimates,
 * not measured from real visit-interval data. The healing->abutment gap
 * (90 days) is grounded in the Treatment record's own description text
 * ("osseointegration 3-6 months") using the low end. These are advisory
 * only — nothing in Slice 5 enforces them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workflow_templates')->insert([
            'key'        => 'implant_staging',
            'name'       => 'Implant Staging',
            'version'    => 1,
            'steps'      => json_encode([
                ['key' => 'planning',        'label' => 'CBCT Planning',                   'min_gap_days_from_previous' => 0],
                ['key' => 'implant_surgery', 'label' => 'Implant Placement',                'min_gap_days_from_previous' => 0],
                ['key' => 'healing',         'label' => 'Healing & Osseointegration',       'min_gap_days_from_previous' => 0],
                ['key' => 'abutment',        'label' => 'Abutment Placement',                'min_gap_days_from_previous' => 90],
                ['key' => 'crown',           'label' => 'Implant Crown',                     'min_gap_days_from_previous' => 14],
                ['key' => 'review',          'label' => 'Annual Review',                     'min_gap_days_from_previous' => 90],
            ]),
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('workflow_templates')->where('key', 'implant_staging')->delete();
    }
};
