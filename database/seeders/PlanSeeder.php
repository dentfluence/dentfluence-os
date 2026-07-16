<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

// Prices from 01_Strategy/product-pricing-tiers-2026.html (10 Jul 2026).
// Illustrative until validated — update here before quoting real clinics.
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // OS Core
            ['unlocks' => ['os'], 'code' => 'os-core-solo',        'name' => 'OS Core — Solo',        'kind' => 'os_core', 'monthly_price' => 1999, 'annual_price' => 19999, 'description' => 'Single-dentist / small clinic. Full clinical + billing loop.'],
            ['unlocks' => ['os'], 'code' => 'os-core-established', 'name' => 'OS Core — Established', 'kind' => 'os_core', 'monthly_price' => 3999, 'annual_price' => 39999, 'description' => 'Multi-chair / higher volume.'],

            // Standalone modules
            ['unlocks' => ['pre'], 'code' => 'pre',                'name' => 'Patient Recall & Retention Engine', 'kind' => 'module', 'monthly_price' => 999,  'annual_price' => 9999,  'description' => 'Recall reminders, dormant-patient reactivation, WhatsApp follow-up.'],
            ['unlocks' => ['library'], 'code' => 'clinical-library',   'name' => 'Clinical Library',                  'kind' => 'module', 'monthly_price' => 599,  'annual_price' => 5999,  'description' => 'Protocols, drug reference, patient-education material.'],
            ['unlocks' => ['marketing'], 'code' => 'marketing-engine',   'name' => 'Marketing Engine',                  'kind' => 'module', 'monthly_price' => 1499, 'annual_price' => 14999, 'description' => 'Campaigns, offers, birthday/festival messages, review requests.'],
            ['unlocks' => ['presentation'], 'code' => 'smart-presentation', 'name' => 'Smart Presentation',                'kind' => 'module', 'monthly_price' => 1999, 'annual_price' => 19999, 'description' => 'AI-generated visual treatment-plan presentations.'],

            // Bundles
            ['unlocks' => ['pre','marketing'], 'code' => 'bundle-pre-marketing',          'name' => 'PRE + Marketing Engine',            'kind' => 'bundle', 'monthly_price' => 1999, 'annual_price' => 19999, 'description' => '2-module bundle, ~20% off standalone sum.'],
            ['unlocks' => ['library','presentation'], 'code' => 'bundle-library-smartpres',      'name' => 'Clinical Library + Smart Presentation', 'kind' => 'bundle', 'monthly_price' => 2199, 'annual_price' => 21999, 'description' => '2-module bundle, ~15% off standalone sum.'],
            ['unlocks' => ['pre','marketing','library'], 'code' => 'bundle-pre-marketing-library',  'name' => 'PRE + Marketing + Library',          'kind' => 'bundle', 'monthly_price' => 2499, 'annual_price' => 24999, 'description' => '3-module bundle, ~19% off standalone sum.'],
            ['unlocks' => ['pre','marketing','presentation'], 'code' => 'bundle-pre-marketing-smartpres','name' => 'PRE + Marketing + Smart Presentation', 'kind' => 'bundle', 'monthly_price' => 3599, 'annual_price' => 35999, 'description' => '3-module bundle, ~20% off standalone sum.'],

            // Suite / OS Full / Growth / Complete tiers exist in the pricing doc —
            // add them here once their numbers are validated. The all-inclusive tier
            // (OS Full / "pro pass") gets 'unlocks' => ['*'] — one code, every door.
            // Store codes in use: os, pre, marketing, presentation, library.
            // Future stores: chairside, proconsult — new plans, same pattern.
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
