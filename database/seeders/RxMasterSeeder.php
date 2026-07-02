<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds prescription masters: food instructions, dose/duration templates,
 * routes of admin, drug categories, generics, and 50 common dental drugs.
 */
class RxMasterSeeder extends Seeder
{
    public function run(): void
    {
        // ── Food Instructions ─────────────────────────────────────────────────
        $foodInstructions = [
            ['code' => 'BEFORE_FOOD',   'label' => 'Before Food',       'label_mr' => 'जेवणापूर्वी',      'label_hi' => 'खाने से पहले'],
            ['code' => 'AFTER_FOOD',    'label' => 'After Food',        'label_mr' => 'जेवणानंतर',        'label_hi' => 'खाने के बाद'],
            ['code' => 'WITH_FOOD',     'label' => 'With Food',         'label_mr' => 'जेवणासोबत',        'label_hi' => 'खाने के साथ'],
            ['code' => 'EMPTY_STOMACH', 'label' => 'Empty Stomach',     'label_mr' => 'रिकाम्या पोटी',   'label_hi' => 'खाली पेट'],
            ['code' => 'ANY_TIME',      'label' => 'Any Time',          'label_mr' => 'कधीही',             'label_hi' => 'कभी भी'],
            ['code' => 'AT_BEDTIME',    'label' => 'At Bedtime',        'label_mr' => 'झोपण्यापूर्वी',   'label_hi' => 'सोने से पहले'],
        ];
        DB::table('rx_food_instructions')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $foodInstructions));

        // ── Dose Templates ────────────────────────────────────────────────────
        $doseTemplates = [
            ['name' => 'Once Daily',        'abbreviation' => 'OD',  'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0],
            ['name' => 'Twice Daily',       'abbreviation' => 'BD',  'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0],
            ['name' => 'Thrice Daily',      'abbreviation' => 'TDS', 'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0],
            ['name' => 'Morning Only',      'abbreviation' => 'AM',  'morning' => 1, 'afternoon' => 0, 'night' => 0, 'is_sos' => 0],
            ['name' => 'Night Only',        'abbreviation' => 'HS',  'morning' => 0, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0],
            ['name' => 'SOS / As Needed',   'abbreviation' => 'SOS', 'morning' => 0, 'afternoon' => 0, 'night' => 0, 'is_sos' => 1],
            ['name' => 'Morning & Night',   'abbreviation' => 'MN',  'morning' => 1, 'afternoon' => 0, 'night' => 1, 'is_sos' => 0],
            ['name' => 'Four Times Daily',  'abbreviation' => 'QID', 'morning' => 1, 'afternoon' => 1, 'night' => 1, 'is_sos' => 0],
        ];
        DB::table('rx_dose_templates')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['description' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $doseTemplates));

        // ── Duration Templates ────────────────────────────────────────────────
        $durations = [
            ['label' => '1 Day',    'value' => 1,  'unit' => 'days'],
            ['label' => '3 Days',   'value' => 3,  'unit' => 'days'],
            ['label' => '5 Days',   'value' => 5,  'unit' => 'days'],
            ['label' => '7 Days',   'value' => 7,  'unit' => 'days'],
            ['label' => '10 Days',  'value' => 10, 'unit' => 'days'],
            ['label' => '14 Days',  'value' => 14, 'unit' => 'days'],
            ['label' => '1 Month',  'value' => 1,  'unit' => 'months'],
            ['label' => '3 Months', 'value' => 3,  'unit' => 'months'],
        ];
        DB::table('rx_duration_templates')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $durations));

        // ── Routes ────────────────────────────────────────────────────────────
        $routes = [
            ['name' => 'Oral',           'abbreviation' => 'PO'],
            ['name' => 'Topical',        'abbreviation' => 'TOP'],
            ['name' => 'Subgingival',    'abbreviation' => 'SUB'],
            ['name' => 'Intramuscular',  'abbreviation' => 'IM'],
            ['name' => 'Intravenous',    'abbreviation' => 'IV'],
            ['name' => 'Sublingual',     'abbreviation' => 'SL'],
            ['name' => 'Inhalation',     'abbreviation' => 'INH'],
            ['name' => 'Mouthwash',      'abbreviation' => 'MW'],
        ];
        DB::table('rx_routes_of_admin')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $routes));

        // ── Drug Categories ───────────────────────────────────────────────────
        $categories = [
            ['name' => 'Analgesic',         'description' => 'Pain relievers'],
            ['name' => 'Anti-inflammatory', 'description' => 'NSAIDs and steroids'],
            ['name' => 'Antibiotic',        'description' => 'Antibacterial agents'],
            ['name' => 'Antifungal',        'description' => 'Fungal infection treatment'],
            ['name' => 'Antiviral',         'description' => 'Viral infection treatment'],
            ['name' => 'Antiseptic',        'description' => 'Topical antiseptics & mouthwashes'],
            ['name' => 'Antacid / PPI',     'description' => 'Stomach protection'],
            ['name' => 'Vitamin / Mineral', 'description' => 'Supplements'],
            ['name' => 'Anxiolytic',        'description' => 'Anxiety & sedation'],
            ['name' => 'Haemostatic',       'description' => 'Bleeding control'],
        ];
        DB::table('rx_drug_categories')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $categories));

        // ── Generics ──────────────────────────────────────────────────────────
        $generics = [
            ['name' => 'Paracetamol',               'drug_class' => 'Analgesic'],
            ['name' => 'Ibuprofen',                 'drug_class' => 'NSAID'],
            ['name' => 'Diclofenac',                'drug_class' => 'NSAID'],
            ['name' => 'Aceclofenac',               'drug_class' => 'NSAID'],
            ['name' => 'Nimesulide',                'drug_class' => 'NSAID'],
            ['name' => 'Ketorolac',                 'drug_class' => 'NSAID'],
            ['name' => 'Tramadol',                  'drug_class' => 'Opioid Analgesic'],
            ['name' => 'Amoxicillin',               'drug_class' => 'Penicillin'],
            ['name' => 'Amoxicillin-Clavulanate',   'drug_class' => 'Penicillin'],
            ['name' => 'Metronidazole',             'drug_class' => 'Nitroimidazole'],
            ['name' => 'Tinidazole',                'drug_class' => 'Nitroimidazole'],
            ['name' => 'Clindamycin',               'drug_class' => 'Lincosamide'],
            ['name' => 'Azithromycin',              'drug_class' => 'Macrolide'],
            ['name' => 'Doxycycline',               'drug_class' => 'Tetracycline'],
            ['name' => 'Ciprofloxacin',             'drug_class' => 'Fluoroquinolone'],
            ['name' => 'Cephalexin',                'drug_class' => 'Cephalosporin'],
            ['name' => 'Fluconazole',               'drug_class' => 'Azole Antifungal'],
            ['name' => 'Miconazole',                'drug_class' => 'Azole Antifungal'],
            ['name' => 'Nystatin',                  'drug_class' => 'Polyene Antifungal'],
            ['name' => 'Chlorhexidine',             'drug_class' => 'Antiseptic'],
            ['name' => 'Povidone Iodine',           'drug_class' => 'Antiseptic'],
            ['name' => 'Benzydamine',               'drug_class' => 'Antiseptic NSAID'],
            ['name' => 'Prednisolone',              'drug_class' => 'Corticosteroid'],
            ['name' => 'Dexamethasone',             'drug_class' => 'Corticosteroid'],
            ['name' => 'Omeprazole',                'drug_class' => 'PPI'],
            ['name' => 'Pantoprazole',              'drug_class' => 'PPI'],
            ['name' => 'Cetirizine',                'drug_class' => 'Antihistamine'],
            ['name' => 'Vitamin C',                 'drug_class' => 'Vitamin'],
            ['name' => 'Vitamin D3',                'drug_class' => 'Vitamin'],
            ['name' => 'Calcium',                   'drug_class' => 'Mineral'],
            ['name' => 'Triclosan',                 'drug_class' => 'Antiseptic'],
            ['name' => 'Tranexamic Acid',           'drug_class' => 'Haemostatic'],
            ['name' => 'Lignocaine',                'drug_class' => 'Local Anaesthetic'],
        ];
        DB::table('rx_generics')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['notes' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $generics));

        // ── Fetch IDs for linking ─────────────────────────────────────────────
        $catId = fn($name) => DB::table('rx_drug_categories')->where('name', $name)->value('id');
        $genId = fn($name) => DB::table('rx_generics')->where('name', $name)->value('id');
        $routeOral = DB::table('rx_routes_of_admin')->where('abbreviation', 'PO')->value('id');
        $routeTop  = DB::table('rx_routes_of_admin')->where('abbreviation', 'TOP')->value('id');
        $routeMW   = DB::table('rx_routes_of_admin')->where('abbreviation', 'MW')->value('id');
        $routeSub  = DB::table('rx_routes_of_admin')->where('abbreviation', 'SUB')->value('id');
        $fiAfter   = DB::table('rx_food_instructions')->where('code', 'AFTER_FOOD')->value('id');
        $fiBefore  = DB::table('rx_food_instructions')->where('code', 'BEFORE_FOOD')->value('id');
        $fiAny     = DB::table('rx_food_instructions')->where('code', 'ANY_TIME')->value('id');

        // ── 50 Dental Drugs ───────────────────────────────────────────────────
        $drugs = [
            // ── Analgesics ────────────────────────────────────────────────────
            ['brand_name' => 'Dolo 650',         'generic_id' => $genId('Paracetamol'),             'category_id' => $catId('Analgesic'),         'strength' => '650mg',   'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'paracetamol',        'pregnancy_category' => 'B', 'common_dental_uses' => 'Pain relief, fever'],
            ['brand_name' => 'Calpol 500',        'generic_id' => $genId('Paracetamol'),             'category_id' => $catId('Analgesic'),         'strength' => '500mg',   'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'paracetamol',        'pregnancy_category' => 'B', 'common_dental_uses' => 'Mild pain, fever'],
            ['brand_name' => 'Brufen 400',        'generic_id' => $genId('Ibuprofen'),               'category_id' => $catId('Anti-inflammatory'), 'strength' => '400mg',   'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'ibuprofen',          'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain, swelling', 'contraindications' => 'Peptic ulcer, renal failure, pregnancy 3rd trimester'],
            ['brand_name' => 'Combiflam',         'generic_id' => $genId('Ibuprofen'),               'category_id' => $catId('Anti-inflammatory'), 'strength' => '400+325mg','dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'ibuprofen,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Moderate dental pain'],
            ['brand_name' => 'Voveran 50',        'generic_id' => $genId('Diclofenac'),              'category_id' => $catId('Anti-inflammatory'), 'strength' => '50mg',    'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'diclofenac',         'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-op pain, pulpitis'],
            ['brand_name' => 'Hifenac P',         'generic_id' => $genId('Aceclofenac'),             'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+325mg','dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'aceclofenac',        'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain, pericoronitis'],
            ['brand_name' => 'Nise 100',          'generic_id' => $genId('Nimesulide'),              'category_id' => $catId('Anti-inflammatory'), 'strength' => '100mg',   'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'nimesulide',         'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain, fever'],
            ['brand_name' => 'Ketorol DT',        'generic_id' => $genId('Ketorolac'),               'category_id' => $catId('Analgesic'),         'strength' => '10mg',    'dosage_form' => 'Dispersible Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'ketorolac', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Severe post-op dental pain'],
            ['brand_name' => 'Ultracet',          'generic_id' => $genId('Tramadol'),                'category_id' => $catId('Analgesic'),         'strength' => '37.5+325mg','dosage_form' => 'Tablet',  'route_id' => $routeOral, 'default_duration' => 3,  'default_food_instruction_id' => $fiAfter,  'duplicate_molecule_group' => 'tramadol',           'pregnancy_category' => 'C', 'is_controlled' => 1, 'common_dental_uses' => 'Severe dental pain (short-term)'],

            // ── Antibiotics ───────────────────────────────────────────────────
            ['brand_name' => 'Mox 500',           'generic_id' => $genId('Amoxicillin'),             'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Capsule',  'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny,   'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental abscess, pericoronitis', 'contraindications' => 'Penicillin allergy'],
            ['brand_name' => 'Augmentin 625',     'generic_id' => $genId('Amoxicillin-Clavulanate'), 'category_id' => $catId('Antibiotic'), 'strength' => '625mg', 'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Severe dental infections', 'contraindications' => 'Penicillin allergy, hepatic dysfunction'],
            ['brand_name' => 'Flagyl 400',        'generic_id' => $genId('Metronidazole'),           'category_id' => $catId('Antibiotic'), 'strength' => '400mg', 'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'metronidazole', 'antibiotic_class' => 'nitroimidazole', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Periodontal infections, ANUG, dry socket'],
            ['brand_name' => 'Tiniba 500',        'generic_id' => $genId('Tinidazole'),              'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'tinidazole', 'antibiotic_class' => 'nitroimidazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Periodontal, amoebic infections'],
            ['brand_name' => 'Clindac 300',       'generic_id' => $genId('Clindamycin'),             'category_id' => $catId('Antibiotic'), 'strength' => '300mg', 'dosage_form' => 'Capsule',  'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny,   'duplicate_molecule_group' => 'clindamycin', 'antibiotic_class' => 'lincosamide', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Penicillin allergy alternative, orofacial infections'],
            ['brand_name' => 'Azee 500',          'generic_id' => $genId('Azithromycin'),            'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiBefore,'duplicate_molecule_group' => 'azithromycin', 'antibiotic_class' => 'macrolide', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections (penicillin allergy)'],
            ['brand_name' => 'Doxrid 100',        'generic_id' => $genId('Doxycycline'),             'category_id' => $catId('Antibiotic'), 'strength' => '100mg', 'dosage_form' => 'Capsule',  'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'doxycycline', 'antibiotic_class' => 'tetracycline', 'pregnancy_category' => 'D', 'common_dental_uses' => 'Periodontal disease, subgingival placement'],
            ['brand_name' => 'Ciplox 500',        'generic_id' => $genId('Ciprofloxacin'),           'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny,   'duplicate_molecule_group' => 'ciprofloxacin', 'antibiotic_class' => 'fluoroquinolone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Severe orofacial infections'],
            ['brand_name' => 'Sporidex 500',      'generic_id' => $genId('Cephalexin'),              'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Capsule',  'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'cephalexin', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections, prophylaxis'],

            // ── Antifungals ───────────────────────────────────────────────────
            ['brand_name' => 'Flucos 150',        'generic_id' => $genId('Fluconazole'),   'category_id' => $catId('Antifungal'), 'strength' => '150mg', 'dosage_form' => 'Tablet',       'route_id' => $routeOral, 'default_duration' => 7,  'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'fluconazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Oral candidiasis'],
            ['brand_name' => 'Daktarin Oral',     'generic_id' => $genId('Miconazole'),    'category_id' => $catId('Antifungal'), 'strength' => '2%',   'dosage_form' => 'Oral Gel',      'route_id' => $routeTop,  'default_duration' => 14, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'miconazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Oral candidiasis, angular cheilitis'],
            ['brand_name' => 'Nystatin Oral',     'generic_id' => $genId('Nystatin'),      'category_id' => $catId('Antifungal'), 'strength' => '100000 IU/ml', 'dosage_form' => 'Suspension', 'route_id' => $routeTop, 'default_duration' => 14, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'nystatin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Oral thrush'],

            // ── Antiseptics / Mouthwash ───────────────────────────────────────
            ['brand_name' => 'Hexidine Mouthwash','generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.2%', 'dosage_form' => 'Mouthwash',     'route_id' => $routeMW,   'default_duration' => 7,  'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-scaling, post-extraction, gingivitis'],
            ['brand_name' => 'Peridex 0.12%',     'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.12%','dosage_form' => 'Mouthwash',     'route_id' => $routeMW,   'default_duration' => 7,  'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Plaque control, periodontal'],
            ['brand_name' => 'Betadine Gargle',   'generic_id' => $genId('Povidone Iodine'),'category_id' => $catId('Antiseptic'), 'strength' => '1%', 'dosage_form' => 'Gargle',         'route_id' => $routeMW,   'default_duration' => 5,  'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'povidone-iodine', 'pregnancy_category' => 'D', 'common_dental_uses' => 'Pericoronitis, ANUG, ulcers'],
            ['brand_name' => 'Tantum Verde',      'generic_id' => $genId('Benzydamine'),   'category_id' => $catId('Antiseptic'), 'strength' => '0.15%','dosage_form' => 'Mouthwash',     'route_id' => $routeMW,   'default_duration' => 5,  'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'benzydamine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-surgical mouth rinse, ulcer pain'],

            // ── Topical ───────────────────────────────────────────────────────
            ['brand_name' => 'Elyzol Gel',        'generic_id' => $genId('Metronidazole'), 'category_id' => $catId('Antibiotic'), 'strength' => '25%', 'dosage_form' => 'Dental Gel',   'route_id' => $routeSub,  'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'metronidazole', 'antibiotic_class' => 'nitroimidazole', 'common_dental_uses' => 'Subgingival placement periodontal treatment'],
            ['brand_name' => 'Atridox',           'generic_id' => $genId('Doxycycline'),   'category_id' => $catId('Antibiotic'), 'strength' => '8.5%','dosage_form' => 'Subgingival Gel','route_id' => $routeSub, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'doxycycline', 'common_dental_uses' => 'Subgingival delivery periodontal pockets'],

            // ── Steroids ──────────────────────────────────────────────────────
            ['brand_name' => 'Wysolone 10',       'generic_id' => $genId('Prednisolone'),  'category_id' => $catId('Anti-inflammatory'), 'strength' => '10mg','dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'prednisolone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Severe swelling, allergic reactions, oral ulcers'],
            ['brand_name' => 'Dexona 0.5mg',      'generic_id' => $genId('Dexamethasone'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.5mg','dosage_form' => 'Tablet','route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'dexamethasone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-surgical swelling, trismus'],

            // ── PPI / Antacids ────────────────────────────────────────────────
            ['brand_name' => 'Omez 20',           'generic_id' => $genId('Omeprazole'),   'category_id' => $catId('Antacid / PPI'), 'strength' => '20mg','dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'omeprazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'GI protection with NSAIDs'],
            ['brand_name' => 'Pan 40',            'generic_id' => $genId('Pantoprazole'),  'category_id' => $catId('Antacid / PPI'), 'strength' => '40mg','dosage_form' => 'Tablet',  'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'pantoprazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'GI protection with NSAIDs'],

            // ── Antihistamines ────────────────────────────────────────────────
            ['brand_name' => 'Cetzine 10',        'generic_id' => $genId('Cetirizine'),   'category_id' => $catId('Analgesic'), 'strength' => '10mg','dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'cetirizine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Allergic reactions, swelling'],

            // ── Vitamins / Supplements ────────────────────────────────────────
            ['brand_name' => 'Limcee 500',        'generic_id' => $genId('Vitamin C'),   'category_id' => $catId('Vitamin / Mineral'), 'strength' => '500mg','dosage_form' => 'Chewable Tablet','route_id' => $routeOral,'default_duration' => 30, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'vitamin-c', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Gingival health, wound healing'],
            ['brand_name' => 'Calcirol 60000',    'generic_id' => $genId('Vitamin D3'),  'category_id' => $catId('Vitamin / Mineral'), 'strength' => '60000 IU','dosage_form' => 'Sachet','route_id' => $routeOral,'default_duration' => 12, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'vitamin-d3', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Bone health, implant support'],
            ['brand_name' => 'Shelcal 500',       'generic_id' => $genId('Calcium'),     'category_id' => $catId('Vitamin / Mineral'), 'strength' => '500mg','dosage_form' => 'Tablet',  'route_id' => $routeOral, 'default_duration' => 30, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'calcium', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Bone health, implant support'],

            // ── Haemostatic ───────────────────────────────────────────────────
            ['brand_name' => 'Tranexa 500',       'generic_id' => $genId('Tranexamic Acid'), 'category_id' => $catId('Haemostatic'), 'strength' => '500mg','dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'tranexamic-acid', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-extraction bleeding control'],

            // ── Combination products ──────────────────────────────────────────
            ['brand_name' => 'Taxim-O 200',       'generic_id' => null, 'category_id' => $catId('Antibiotic'), 'strength' => '200mg','dosage_form' => 'Tablet', 'composition' => 'Cefixime 200mg', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'cefixime', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections'],
            ['brand_name' => 'Metrogyl DG',       'generic_id' => null, 'category_id' => $catId('Antiseptic'), 'strength' => '1.5%+2%','dosage_form' => 'Dental Gel', 'composition' => 'Metronidazole 1% + Chlorhexidine 0.25%', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => null, 'common_dental_uses' => 'Periodontal, ulcers, pericoronitis'],
            ['brand_name' => 'Kenalog Orabase',   'generic_id' => null, 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.1%','dosage_form' => 'Oral Paste', 'composition' => 'Triamcinolone 0.1%', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Aphthous ulcers, oral lichen planus'],
            ['brand_name' => 'OrthoHex Gel',      'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '1%', 'dosage_form' => 'Gel', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'common_dental_uses' => 'Periodontal, implant site care'],
            ['brand_name' => 'Dentogel',          'generic_id' => $genId('Lignocaine'),   'category_id' => $catId('Analgesic'), 'strength' => '2%', 'dosage_form' => 'Topical Gel', 'route_id' => $routeTop, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'lignocaine', 'common_dental_uses' => 'Mucosal anaesthesia, ulcer pain relief'],
            ['brand_name' => 'Zytee Gel',         'generic_id' => $genId('Lignocaine'),   'category_id' => $catId('Analgesic'), 'strength' => '2%', 'dosage_form' => 'Topical Gel', 'route_id' => $routeTop, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'lignocaine', 'common_dental_uses' => 'Topical local anaesthesia'],
            ['brand_name' => 'Aloclair Gel',      'generic_id' => null, 'category_id' => $catId('Antiseptic'), 'strength' => null, 'dosage_form' => 'Gel', 'composition' => 'Hyaluronic acid, aloe vera', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Aphthous ulcers, mucosal lesions'],
            ['brand_name' => 'Healgel',           'generic_id' => null, 'category_id' => $catId('Antiseptic'), 'strength' => null, 'dosage_form' => 'Gel', 'composition' => 'Sodium hyaluronate', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Post-extraction socket, wound healing'],
            ['brand_name' => 'Disprin 350',       'generic_id' => null, 'category_id' => $catId('Analgesic'), 'strength' => '350mg', 'dosage_form' => 'Dispersible Tablet', 'composition' => 'Aspirin 350mg', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'aspirin', 'pregnancy_category' => 'D', 'common_dental_uses' => 'Mild pain (not post-extraction — bleeding risk)'],
            ['brand_name' => 'Sensodent-K',       'generic_id' => $genId('Triclosan'),   'category_id' => $catId('Antiseptic'), 'strength' => '0.3%', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 30, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Dentinal hypersensitivity'],
            ['brand_name' => 'Mucositis Rinse',   'generic_id' => $genId('Benzydamine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.15%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Chemotherapy/radiation mucositis'],
        ];

        foreach ($drugs as &$d) {
            $d['drug_code']               = null;
            $d['default_dose']            = null;
            $d['max_daily_dose']          = null;
            $d['renal_dose_adjustment']   = null;
            $d['hepatic_dose_adjustment'] = null;
            $d['drug_interactions_note']  = null;
            $d['notes']                   = null;
            $d['is_active']               = 1;
            $d['created_at']              = now();
            $d['updated_at']              = now();
            $d['deleted_at']              = null;
            // fill optional safety fields if missing
            $d['antibiotic_class']        = $d['antibiotic_class']    ?? null;
            $d['is_controlled']           = $d['is_controlled']       ?? 0;
            $d['pregnancy_category']      = $d['pregnancy_category']  ?? null;
            $d['breastfeeding_safety']    = null;
            $d['pediatric_safety']        = null;
            $d['geriatric_caution']       = null;
            $d['contraindications']       = $d['contraindications']   ?? null;
            $d['duplicate_molecule_group']= $d['duplicate_molecule_group'] ?? null;
            $d['common_dental_uses']      = $d['common_dental_uses']  ?? null;
            $d['composition']             = $d['composition']         ?? null;
        }

        DB::table('rx_drugs')->insertOrIgnore($drugs);

        // ── Basic Warning Rules ───────────────────────────────────────────────
        $warnings = [
            ['condition_keyword' => 'gastric ulcer',  'drug_id' => null, 'molecule_group' => 'ibuprofen',    'drug_class' => 'NSAID', 'severity' => 'warning',  'alert_message' => 'Patient has Gastric Ulcer. NSAIDs may increase gastric bleeding risk. Consider Paracetamol.',   'suggestion' => 'Paracetamol', 'blockable' => 0],
            ['condition_keyword' => 'gastric ulcer',  'drug_id' => null, 'molecule_group' => 'diclofenac',   'drug_class' => 'NSAID', 'severity' => 'warning',  'alert_message' => 'Patient has Gastric Ulcer. NSAIDs may worsen symptoms. Add PPI cover or switch analgesic.',    'suggestion' => 'Add Omeprazole', 'blockable' => 0],
            ['condition_keyword' => 'renal failure',  'drug_id' => null, 'molecule_group' => null,           'drug_class' => 'NSAID', 'severity' => 'critical', 'alert_message' => 'Patient has Renal Failure. NSAIDs are contraindicated — risk of acute kidney injury.',          'suggestion' => 'Paracetamol', 'blockable' => 1],
            ['condition_keyword' => 'pregnancy',      'drug_id' => null, 'molecule_group' => 'ibuprofen',    'drug_class' => null,    'severity' => 'critical', 'alert_message' => 'Patient is pregnant. Ibuprofen is contraindicated in pregnancy (3rd trimester).',               'suggestion' => 'Paracetamol', 'blockable' => 1],
            ['condition_keyword' => 'pregnancy',      'drug_id' => null, 'molecule_group' => 'nimesulide',   'drug_class' => null,    'severity' => 'critical', 'alert_message' => 'Patient is pregnant. Nimesulide is contraindicated in pregnancy.',                            'suggestion' => 'Paracetamol', 'blockable' => 1],
            ['condition_keyword' => 'diabetes',       'drug_id' => null, 'molecule_group' => null,           'drug_class' => 'Corticosteroid', 'severity' => 'warning', 'alert_message' => 'Patient has Diabetes. Corticosteroids may raise blood glucose levels. Monitor closely.', 'suggestion' => null, 'blockable' => 0],
            ['condition_keyword' => 'hypertension',   'drug_id' => null, 'molecule_group' => null,           'drug_class' => 'NSAID', 'severity' => 'warning',  'alert_message' => 'Patient has Hypertension. NSAIDs may raise blood pressure. Use with caution.',               'suggestion' => 'Paracetamol', 'blockable' => 0],
            ['condition_keyword' => 'liver disease',  'drug_id' => null, 'molecule_group' => 'paracetamol',  'drug_class' => null,    'severity' => 'warning',  'alert_message' => 'Patient has Liver Disease. High-dose Paracetamol may cause hepatotoxicity. Limit dose.',     'suggestion' => null, 'blockable' => 0],
            ['condition_keyword' => 'asthma',         'drug_id' => null, 'molecule_group' => null,           'drug_class' => 'NSAID', 'severity' => 'warning',  'alert_message' => 'Patient has Asthma. NSAIDs may trigger bronchospasm in aspirin-sensitive asthma.',           'suggestion' => 'Paracetamol', 'blockable' => 0],
        ];
        DB::table('rx_warning_rules')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $warnings));

        // ── Allergy Rules ─────────────────────────────────────────────────────
        $allergyRules = [
            ['allergy_keyword' => 'penicillin',   'blocks_molecule' => 'amoxicillin',  'blocks_class' => 'penicillin',     'severity' => 'critical', 'alert_message' => 'Recorded Penicillin Allergy. This drug is a Penicillin — risk of severe allergic reaction (anaphylaxis).'],
            ['allergy_keyword' => 'amoxicillin',  'blocks_molecule' => 'amoxicillin',  'blocks_class' => 'penicillin',     'severity' => 'critical', 'alert_message' => 'Recorded Amoxicillin Allergy. This drug contains Amoxicillin — risk of allergic reaction.'],
            ['allergy_keyword' => 'aspirin',      'blocks_molecule' => 'aspirin',      'blocks_class' => 'NSAID',          'severity' => 'warning',  'alert_message' => 'Recorded Aspirin Allergy. NSAIDs may cross-react. Use with caution.'],
            ['allergy_keyword' => 'sulfa',        'blocks_molecule' => null,           'blocks_class' => 'sulfonamide',    'severity' => 'critical', 'alert_message' => 'Recorded Sulfa Allergy. Sulfonamide class drugs are contraindicated.'],
            ['allergy_keyword' => 'metronidazole','blocks_molecule' => 'metronidazole','blocks_class' => 'nitroimidazole', 'severity' => 'critical', 'alert_message' => 'Recorded Metronidazole Allergy. This drug is contraindicated.'],
            ['allergy_keyword' => 'nsaid',        'blocks_molecule' => null,           'blocks_class' => 'NSAID',          'severity' => 'critical', 'alert_message' => 'Recorded NSAID Allergy. NSAIDs are contraindicated.'],
        ];
        DB::table('rx_allergy_rules')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $allergyRules));

        // ── Prescription Templates ────────────────────────────────────────────
        $templates = [
            ['name' => 'RCT Pain Management',      'category' => 'endodontics',   'description' => 'Standard protocol for RCT pain and inflammation'],
            ['name' => 'Post-Extraction',          'category' => 'surgical',      'description' => 'Post tooth extraction care'],
            ['name' => 'Pericoronitis',            'category' => 'surgical',      'description' => 'Pericoronal infection management'],
            ['name' => 'Post-Scaling & Polishing', 'category' => 'periodontal',   'description' => 'Post SRP prescription'],
            ['name' => 'Dry Socket (Alveolar Osteitis)', 'category' => 'surgical','description' => 'Management of dry socket'],
            ['name' => 'Oral Ulcers',             'category' => 'medicine',       'description' => 'Aphthous / traumatic ulcer protocol'],
            ['name' => 'Oral Candidiasis',        'category' => 'medicine',       'description' => 'Fungal infection of oral mucosa'],
            ['name' => 'Periodontal Therapy',     'category' => 'periodontal',    'description' => 'Adjunctive antibiotic therapy for periodontitis'],
        ];
        DB::table('rx_templates')->insertOrIgnore(array_map(fn($r) => array_merge($r, ['instructions' => null, 'is_active' => 1, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()]), $templates));

        $this->command->info('✅ Prescription masters seeded: food instructions, dose/duration templates, routes, categories, generics, 50 drugs, warning rules, allergy rules, templates.');
    }
}
