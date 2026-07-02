<?php

namespace Database\Seeders;

use App\Models\ConsentPurpose;
use Illuminate\Database\Seeder;

/**
 * ConsentPurposeSeeder
 * --------------------
 * Seeds the default set of DPDP consent purposes a dental clinic needs.
 *
 * Uses updateOrCreate keyed on `key`, so it is SAFE TO RE-RUN: it will insert
 * any missing purposes and refresh labels/descriptions without creating
 * duplicates or wiping patient consent rows.
 *
 * Run with:  php artisan db:seed --class=ConsentPurposeSeeder
 *
 * Tweak the wording with your compliance advisor — these are sensible
 * starting points, not legal copy.
 */
class ConsentPurposeSeeder extends Seeder
{
    public function run(): void
    {
        $purposes = [
            // ── Clinical (needed to treat) ───────────────────────────────
            [
                'key' => 'treatment_care', 'name' => 'Dental treatment & care',
                'category' => 'clinical', 'is_mandatory' => true, 'requires_explicit' => true,
                'description' => 'Consent to examination, diagnosis and dental treatment, and to keeping the clinical records this requires.',
                'sort_order' => 10,
            ],
            [
                'key' => 'store_records', 'name' => 'Store health records',
                'category' => 'clinical', 'is_mandatory' => true, 'requires_explicit' => true,
                'description' => 'Consent to securely store your personal and health information for as long as the law requires.',
                'sort_order' => 20,
            ],
            [
                'key' => 'clinical_photos', 'name' => 'Clinical photos & x-rays',
                'category' => 'clinical', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to capture and store intra-oral photos, x-rays and scans as part of your treatment record.',
                'sort_order' => 30,
            ],

            // ── Data sharing ─────────────────────────────────────────────
            [
                'key' => 'abdm_share', 'name' => 'Share with ABDM / ABHA',
                'category' => 'data_sharing', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to link your records to your ABHA and share them through India\'s ABDM health network when you ask us to.',
                'sort_order' => 40,
            ],
            [
                'key' => 'share_referral', 'name' => 'Share with referrals & labs',
                'category' => 'data_sharing', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to share relevant records with specialists, dental labs or other providers involved in your care.',
                'sort_order' => 50,
            ],

            // ── Communication ────────────────────────────────────────────
            [
                'key' => 'recall_reminders', 'name' => 'Appointment & recall reminders',
                'category' => 'communication', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to receive appointment confirmations, reminders and recall messages.',
                'sort_order' => 60,
            ],
            [
                'key' => 'whatsapp_comms', 'name' => 'WhatsApp messages',
                'category' => 'communication', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to be contacted on WhatsApp for reminders, updates and replies to your queries.',
                'sort_order' => 70,
            ],
            [
                'key' => 'sms_email_comms', 'name' => 'SMS & email',
                'category' => 'communication', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to be contacted by SMS and email about your appointments and care.',
                'sort_order' => 80,
            ],
            [
                'key' => 'marketing_promotions', 'name' => 'Offers & promotions',
                'category' => 'communication', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to receive optional marketing, health tips and promotional offers. You can withdraw any time.',
                'sort_order' => 90,
            ],

            // ── Research (optional, anonymised) ──────────────────────────
            [
                'key' => 'research_anonymised', 'name' => 'Anonymised research',
                'category' => 'research', 'is_mandatory' => false, 'requires_explicit' => true,
                'description' => 'Consent to use your de-identified data for clinical research and service improvement.',
                'sort_order' => 100,
            ],
        ];

        foreach ($purposes as $p) {
            ConsentPurpose::updateOrCreate(
                ['key' => $p['key']],          // match on the slug
                array_merge($p, ['active' => true, 'version' => 1]) // insert/refresh the rest
            );
        }
    }
}
