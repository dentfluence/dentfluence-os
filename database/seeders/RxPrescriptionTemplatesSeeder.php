<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Prescription Templates — 20 Dental Treatment Presets
 *
 * Covers: extraction, RCT, implant, periodontal surgery, orthodontic,
 * sensitivity, abscess, paediatric, TMD, OSMF, herpes, angular cheilitis,
 * post-crown, denture stomatitis, oral lichen planus, and more.
 *
 * Each template includes rx_template_items referencing drugs by brand name.
 * Run AFTER RxMasterSeeder AND RxDentalBrandsSeeder.
 */
class RxPrescriptionTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $drugId   = fn($name) => DB::table('rx_drugs')->where('brand_name', $name)->value('id');
        $fiAfter  = DB::table('rx_food_instructions')->where('code', 'AFTER_FOOD')->value('id');
        $fiBefore = DB::table('rx_food_instructions')->where('code', 'BEFORE_FOOD')->value('id');
        $fiAny    = DB::table('rx_food_instructions')->where('code', 'ANY_TIME')->value('id');

        // ─────────────────────────────────────────────────────────────────────
        // Helper: insert template + its items
        // ─────────────────────────────────────────────────────────────────────
        $insertTemplate = function (array $template, array $items) {
            // Only insert if template doesn't already exist
            $existingId = DB::table('rx_templates')->where('name', $template['name'])->value('id');
            if ($existingId) {
                return $existingId;
            }
            $templateId = DB::table('rx_templates')->insertGetId(array_merge($template, [
                'is_active'  => 1,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            foreach ($items as $i => $item) {
                if (empty($item['drug_id'])) continue; // skip if drug not found
                DB::table('rx_template_items')->insertOrIgnore(array_merge($item, [
                    'template_id' => $templateId,
                    'sort_order'  => $i + 1,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]));
            }
            return $templateId;
        };

        // ═════════════════════════════════════════════════════════════════════
        // 1. SIMPLE EXTRACTION (Routine)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Simple Extraction',
            'category'     => 'surgical',
            'description'  => 'Post-extraction prescription for routine single tooth removal',
            'instructions' => 'Bite on gauze for 30 minutes. Avoid hot food and drinks for 24 hours. No rinsing for first 24 hours. Soft diet for 3 days.',
        ], [
            ['drug_id' => $drugId('Zerodol SP'),     'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Take after meals'],
            ['drug_id' => $drugId('Mox 500'),         'strength' => '500mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Complete full course'],
            ['drug_id' => $drugId('Flagyl 400'),      'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Avoid alcohol'],
            ['drug_id' => $drugId('Omez 20'),         'strength' => '20mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'For gastric protection'],
            ['drug_id' => $drugId('Hexidine Mouthwash'),'strength' => '0.2%',      'morning' => 0, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Rinse after meals from Day 2 onwards. Do not swallow.'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 2. SURGICAL EXTRACTION / WISDOM TOOTH REMOVAL
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Surgical Extraction / Wisdom Tooth',
            'category'     => 'surgical',
            'description'  => 'Post-surgical prescription for impacted/surgical tooth extraction including wisdom teeth',
            'instructions' => 'Apply ice pack for 20 min on/off for first 24 hours. Soft diet. Do not spit forcefully. Call if bleeding does not stop.',
        ], [
            ['drug_id' => $drugId('Zerodol SP'),     'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Take after meals for pain and swelling'],
            ['drug_id' => $drugId('Augmentin 625'),   'strength' => '625mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Complete full course'],
            ['drug_id' => $drugId('Flagyl 400'),      'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Avoid alcohol. Complete full course.'],
            ['drug_id' => $drugId('Wysolone 10'),     'strength' => '10mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'For post-surgical swelling and trismus'],
            ['drug_id' => $drugId('Chymoral Forte'),  'strength' => '1,00,000 AU',  'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Take on empty stomach for best effect'],
            ['drug_id' => $drugId('Omez 20'),         'strength' => '20mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Gastric protection'],
            ['drug_id' => $drugId('Hexidine Mouthwash'),'strength' => '0.2%',      'morning' => 0, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Gentle rinse from Day 2. Do not swallow.'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 3. RCT PAIN MANAGEMENT (Update/replace basic template)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'RCT Pain Management (Detailed)',
            'category'     => 'endodontics',
            'description'  => 'Comprehensive prescription during/after RCT — pain, infection, swelling',
            'instructions' => 'Avoid biting on treated tooth until final restoration. Return immediately if severe pain or swelling.',
        ], [
            ['drug_id' => $drugId('Zerodol SP'),    'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Pain and inflammation relief'],
            ['drug_id' => $drugId('Augmentin 625'),  'strength' => '625mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Antibiotic — complete full course'],
            ['drug_id' => $drugId('Flagyl 400'),     'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anaerobic cover — avoid alcohol'],
            ['drug_id' => $drugId('Dolo 650'),       'strength' => '650mg',        'morning' => 0, 'afternoon' => 0, 'night' => 0, 'is_sos' => 1, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'SOS — take if pain is severe between scheduled doses'],
            ['drug_id' => $drugId('Omez 20'),        'strength' => '20mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Gastric protection with NSAIDs'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 4. DENTAL ABSCESS (Acute)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Acute Dental Abscess',
            'category'     => 'endodontics',
            'description'  => 'Empirical antibiotic and analgesic for acute dentoalveolar abscess',
            'instructions' => 'Warm saline rinses 4 times daily. Attend for incision and drainage if swelling fluctuant. Drink plenty of fluids.',
        ], [
            ['drug_id' => $drugId('Augmentin 625'),  'strength' => '625mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Primary antibiotic — do not skip doses'],
            ['drug_id' => $drugId('Metrogyl 400'),   'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anaerobic cover'],
            ['drug_id' => $drugId('Hifenac SP'),     'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Pain and swelling'],
            ['drug_id' => $drugId('Pan 40'),         'strength' => '40mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Gastric protection'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 5. PERICORONITIS
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Pericoronitis',
            'category'     => 'surgical',
            'description'  => 'Pericoronal infection around partially erupted wisdom tooth',
            'instructions' => 'Warm saline irrigation under the gum flap 3–4 times daily. Return for surgical review after infection settles.',
        ], [
            ['drug_id' => $drugId('Augmentin 625'),  'strength' => '625mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => null],
            ['drug_id' => $drugId('Flagyl 400'),     'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Avoid alcohol'],
            ['drug_id' => $drugId('Zerodol P'),      'strength' => '100+500mg',    'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'For pain and inflammation'],
            ['drug_id' => $drugId('Hexidine Mouthwash'),'strength' => '0.2%',     'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Rinse around wisdom tooth area'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 6. DRY SOCKET (Alveolar Osteitis)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Dry Socket (Alveolar Osteitis)',
            'category'     => 'surgical',
            'description'  => 'Management of post-extraction dry socket — socket dressing and pain control',
            'instructions' => 'Alvogyl dressing placed in socket. Return every 2–3 days for dressing change. Do not probe the socket. Soft diet.',
        ], [
            ['drug_id' => $drugId('Ketorol DT'),     'strength' => '10mg',  'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Strong analgesic for severe pain'],
            ['drug_id' => $drugId('Flagyl 400'),     'strength' => '400mg', 'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anti-anaerobic cover'],
            ['drug_id' => $drugId('Dolo 650'),       'strength' => '650mg', 'morning' => 0, 'afternoon' => 0, 'night' => 0, 'is_sos' => 1, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'SOS for breakthrough pain'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 7. IMPLANT SURGERY POST-OP
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Implant Surgery Post-op',
            'category'     => 'surgical',
            'description'  => 'Comprehensive post-implant placement prescription for osseointegration and infection prevention',
            'instructions' => 'No rinsing for 24 hours. Soft diet for 2 weeks. No smoking — critical for implant survival. Use Oracura/water flosser from Week 2 onwards.',
        ], [
            ['drug_id' => $drugId('Augmentin 625'),  'strength' => '625mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anti-infective cover — critical for osseointegration'],
            ['drug_id' => $drugId('Flagyl 400'),     'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anaerobic cover'],
            ['drug_id' => $drugId('Hifenac SP'),     'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Post-op pain and oedema'],
            ['drug_id' => $drugId('Chymoral Forte'), 'strength' => '1,00,000 AU',  'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Oedema resolution — empty stomach'],
            ['drug_id' => $drugId('Pan 40'),         'strength' => '40mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Gastric protection'],
            ['drug_id' => $drugId('Shelcal CT'),     'strength' => '500mg+250IU',  'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 90, 'duration_unit' => 'days','food_instruction_id' => $fiAfter, 'instructions' => 'Calcium + D3 for osseointegration support — 3 months'],
            ['drug_id' => $drugId('Hexidine Mouthwash'),'strength' => '0.2%',     'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 14, 'duration_unit' => 'days','food_instruction_id' => $fiAny,   'instructions' => 'Rinse gently twice daily from Day 2'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 8. SCALING AND ROOT PLANING / POST-PERIODONTAL SURGERY
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Periodontal Surgery (Flap / SRP)',
            'category'     => 'periodontal',
            'description'  => 'Post-periodontal flap surgery or deep SRP prescription',
            'instructions' => 'Soft diet for 1 week. Do not brush surgical site directly for 2 weeks — use Hexigel gel topically. Resume gentle brushing after suture removal.',
        ], [
            ['drug_id' => $drugId('Augmentin 625'),  'strength' => '625mg',        'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Post-surgical antibiotic cover'],
            ['drug_id' => $drugId('Metrogyl 400'),   'strength' => '400mg',        'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anaerobic bacteria cover'],
            ['drug_id' => $drugId('Hifenac SP'),     'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Post-op pain and swelling'],
            ['drug_id' => $drugId('Hexidine Mouthwash'),'strength' => '0.2%',     'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 14, 'duration_unit' => 'days','food_instruction_id' => $fiAny,   'instructions' => 'Rinse twice daily — do not swallow'],
            ['drug_id' => $drugId('Hexigel'),        'strength' => '1%',           'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply with fingertip on surgical site 3 times daily'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 9. ORTHODONTIC TREATMENT (Braces Fixed)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Orthodontic Treatment (Braces)',
            'category'     => 'orthodontic',
            'description'  => 'Initial braces placement or adjustment — pain management and hygiene',
            'instructions' => 'Soreness normal for 3–5 days after adjustment. Avoid hard/sticky foods. Use interdental brushes (TePe) daily. Consider Oracura water flosser.',
        ], [
            ['drug_id' => $drugId('Dolo 650'),       'strength' => '650mg', 'morning' => 0, 'afternoon' => 0, 'night' => 0, 'is_sos' => 1, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'SOS — for soreness after adjustment/bonding'],
            ['drug_id' => $drugId('GC Tooth Mousse'),'strength' => 'CPP-ACP','morning' => 0, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 30, 'duration_unit' => 'days','food_instruction_id' => $fiAny,   'instructions' => 'Apply thin coat on teeth before bedtime — do not rinse after'],
            ['drug_id' => $drugId('Hexidine Mouthwash'),'strength' => '0.2%','morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'After bonding — rinse twice daily for first week'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 10. TOOTH SENSITIVITY
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Tooth Sensitivity Management',
            'category'     => 'restorative',
            'description'  => 'Non-carious cervical lesion / dentinal hypersensitivity — desensitising protocol',
            'instructions' => 'Use desensitising toothpaste twice daily. Avoid acidic foods and drinks. Brush gently with soft toothbrush. Return in 4 weeks for review.',
        ], [
            ['drug_id' => $drugId('Sensodyne Original'),'strength' => '5% KNO3','morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 60, 'duration_unit' => 'days','food_instruction_id' => $fiAny,   'instructions' => 'Use as daily toothpaste — do not rinse immediately after brushing'],
            ['drug_id' => $drugId('GC Tooth Mousse'), 'strength' => 'CPP-ACP',  'morning' => 0, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 30, 'duration_unit' => 'days','food_instruction_id' => $fiAny,   'instructions' => 'Apply on sensitive teeth at night — leave on, do not rinse'],
            ['drug_id' => $drugId('Curaprox CS 5460 Brush'),'strength' => null, 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => null,'duration_unit' => 'days','food_instruction_id' => $fiAny,   'instructions' => 'Ultra-soft brush — use light circular pressure only'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 11. APHTHOUS ULCERS (Recurrent)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Aphthous Ulcers (Recurrent)',
            'category'     => 'medicine',
            'description'  => 'Recurrent aphthous stomatitis — minor ulcer treatment',
            'instructions' => 'Avoid spicy, sour, and hard foods. Maintain gentle oral hygiene. Return if ulcers persist beyond 3 weeks or increase in size.',
        ], [
            ['drug_id' => $drugId('Kenalog in Orabase'),'strength' => '0.1%',  'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply a small amount on ulcer 3 times daily. Do not eat for 30 min after.'],
            ['drug_id' => $drugId('Tantum Verde'),   'strength' => '0.15%',    'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Rinse for 30 sec, 3 times daily. Do not swallow.'],
            ['drug_id' => $drugId('Becosules Capsules'),'strength' => 'B+C',   'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 30, 'duration_unit' => 'days','food_instruction_id' => $fiAfter, 'instructions' => 'Vitamin B & C deficiency — take daily'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 12. ORAL CANDIDIASIS
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Oral Candidiasis (Thrush)',
            'category'     => 'medicine',
            'description'  => 'Fungal infection of oral mucosa — fluconazole + topical antifungal',
            'instructions' => 'Rinse dentures with antifungal solution if worn. Stay well hydrated. If on inhaled steroids, rinse mouth after each use.',
        ], [
            ['drug_id' => $drugId('Forcan 150'),     'strength' => '150mg', 'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Fluconazole once daily for 7 days'],
            ['drug_id' => $drugId('Daktarin Oral'),  'strength' => '2%',    'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 14,'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply topically on white patches and under dentures 3 times daily'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 13. HERPES LABIALIS (Cold Sores)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Herpes Labialis (Cold Sores)',
            'category'     => 'medicine',
            'description'  => 'Recurrent herpes labialis — antiviral and topical management',
            'instructions' => 'Start treatment at first sign of tingling. Apply cream every 4 hours. Avoid touching lesion and then eyes. Do not share lip products.',
        ], [
            ['drug_id' => $drugId('Acivir 200'),     'strength' => '200mg',  'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Take 5 times daily (every 4–5 hours) — 5 days'],
            ['drug_id' => $drugId('Acyclovir Cream 5%'),'strength' => '5%',  'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply on lesion every 4 hours (5 times/day). Wash hands before and after.'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 14. ANGULAR CHEILITIS
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Angular Cheilitis',
            'category'     => 'medicine',
            'description'  => 'Cracking at corners of mouth — mixed bacterial/fungal aetiology',
            'instructions' => 'Keep angles of mouth dry. Petroleum jelly as barrier. Check vitamin B / iron deficiency. Check denture vertical dimension if edentulous.',
        ], [
            ['drug_id' => $drugId('Daktarin Oral'),  'strength' => '2%',    'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 14,'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply at corners of mouth 3 times daily'],
            ['drug_id' => $drugId('Becosules Capsules'),'strength' => 'B+C','morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 30,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Vitamin B-complex to address deficiency component'],
            ['drug_id' => $drugId('Zincovit'),       'strength' => 'Multi', 'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 30,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Zinc and vitamins for immune support and healing'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 15. ORAL SUBMUCOUS FIBROSIS (OSMF)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Oral Submucous Fibrosis (OSMF)',
            'category'     => 'medicine',
            'description'  => 'OSMF management — antioxidants, lycopene, intralesional steroids (physician collaboration)',
            'instructions' => 'Immediate cessation of tobacco, gutka, and areca nut is mandatory. Physiotherapy exercises 3 times daily. Avoid spicy/hot foods.',
        ], [
            ['drug_id' => $drugId('Lycopoderm Gel'), 'strength' => '2mg/5g', 'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 90,'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply topically on fibrotic bands 3 times daily'],
            ['drug_id' => $drugId('Lycopene 10mg'),  'strength' => '10mg',   'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 90,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Antioxidant — take daily for 3 months'],
            ['drug_id' => $drugId('Becosules Capsules'),'strength' => 'B+C', 'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 90,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Vitamin B and C support'],
            ['drug_id' => $drugId('Zincovit'),       'strength' => 'Multi',  'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 90,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Zinc + antioxidant support'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 16. POST CROWN / BRIDGE CEMENTATION
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Post Crown / Bridge Cementation',
            'category'     => 'restorative',
            'description'  => 'Post crown or bridge cementation — sensitivity and pulpal care',
            'instructions' => 'Do not eat on that side for 1 hour. Mild sensitivity normal for 1–2 weeks. Avoid very hot/cold foods. Return if bite feels high.',
        ], [
            ['drug_id' => $drugId('Dolo 650'),       'strength' => '650mg', 'morning' => 0, 'afternoon' => 0, 'night' => 0, 'is_sos' => 1, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'SOS only — for post-cementation sensitivity pain'],
            ['drug_id' => $drugId('Sensodyne Original'),'strength' => '5%', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 30,'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Use as toothpaste for sensitivity relief'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 17. TEMPOROMANDIBULAR DISORDER (TMD)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Temporomandibular Disorder (TMD)',
            'category'     => 'medicine',
            'description'  => 'TMJ pain and masticatory muscle pain — conservative management',
            'instructions' => 'Soft diet. Avoid wide mouth opening. Apply warm compress for 15 min 3 times daily. Night guard to be fabricated. Stress counselling if needed.',
        ], [
            ['drug_id' => $drugId('Hifenac SP'),     'strength' => '100+325+10mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Anti-inflammatory and muscle relaxation support'],
            ['drug_id' => $drugId('Diclofenac Gel (Voveran)'),'strength' => '1%',  'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply over TMJ area (preauricular) and masseter 3 times daily'],
            ['drug_id' => $drugId('Pan 40'),         'strength' => '40mg',         'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiBefore,'instructions' => 'Gastric protection with NSAID use'],
            ['drug_id' => $drugId('Neurobion Forte'),'strength' => 'B1+B6+B12',   'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 30,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Nerve support for facial pain / neuropathic component'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 18. PAEDIATRIC EXTRACTION
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Paediatric Extraction',
            'category'     => 'surgical',
            'description'  => 'Post-extraction prescription for children (6–12 years) — weight-based dosing',
            'instructions' => 'Bite on gauze for 30 minutes. No school for rest of day. Soft food — ice cream, curd, mashed potato. Do not use straw. Review in 48 hours.',
        ], [
            ['drug_id' => $drugId('Ibugesic 200'),   'strength' => '200mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 3, 'duration_unit' => 'days', 'food_instruction_id' => $fiAfter, 'instructions' => 'Paediatric ibuprofen — dose by weight (10mg/kg). After food.'],
            ['drug_id' => $drugId('Amoxil 250 Syrup'),'strength' => '250mg/5ml','morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 5, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny, 'instructions' => 'Antibiotic syrup — dose by weight (25mg/kg/day). Complete full course.'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 19. ORAL LICHEN PLANUS
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Oral Lichen Planus',
            'category'     => 'medicine',
            'description'  => 'Symptomatic oral lichen planus — topical and systemic management',
            'instructions' => 'Avoid spicy foods, alcohol, tobacco. Manage stress. Regular 6-monthly review for malignant transformation monitoring.',
        ], [
            ['drug_id' => $drugId('Clobetasol Gel'), 'strength' => '0.05%', 'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 14,'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Apply on lichen planus lesions 3 times daily — do not eat for 30 min after'],
            ['drug_id' => $drugId('Tantum Verde'),   'strength' => '0.15%', 'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0, 'duration' => 14,'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Rinse for pain relief 3 times daily'],
            ['drug_id' => $drugId('Forcan 150'),     'strength' => '150mg', 'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 7, 'duration_unit' => 'days', 'food_instruction_id' => $fiAny,   'instructions' => 'Antifungal cover — corticosteroid use increases candidal risk'],
        ]);

        // ═════════════════════════════════════════════════════════════════════
        // 20. IMPLANT MAINTENANCE (Long-term)
        // ═════════════════════════════════════════════════════════════════════
        $insertTemplate([
            'name'         => 'Implant Maintenance Protocol',
            'category'     => 'surgical',
            'description'  => 'Long-term implant maintenance — hygiene and bone support',
            'instructions' => 'Recall every 3–6 months. Use Oracura water flosser daily. Use TePe interdental brushes. Avoid smoking permanently.',
        ], [
            ['drug_id' => $drugId('Periogard Mouthwash'),'strength' => '0.12%','morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 30,'duration_unit' => 'days', 'food_instruction_id' => $fiAny, 'instructions' => 'Daily rinse twice — anti-plaque around implants'],
            ['drug_id' => $drugId('Shelcal CT'),     'strength' => '500mg', 'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0, 'duration' => 90,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter,'instructions' => 'Calcium + D3 + Zinc — bone density maintenance'],
            ['drug_id' => $drugId('D-Cal 60000'),    'strength' => '60000 IU','morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0, 'duration' => 12,'duration_unit' => 'days', 'food_instruction_id' => $fiAfter,'instructions' => 'Vitamin D — once weekly (12 sachets = 3 months)'],
        ]);

        $this->command->info('✅ RxPrescriptionTemplatesSeeder: 20 dental treatment prescription templates created.');
    }
}
