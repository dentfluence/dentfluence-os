<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dental Drug Master — Indian Brands Edition
 *
 * Adds 120+ branded drugs from top Indian pharma companies:
 * ICPA, Mankind, Dr. Reddy's, IPCA, Abbott, Warren, GSK, Cipla, Alembic,
 * Torrent, Sun Pharma, Zydus, Intas, Elder, Micro Labs, Wockhardt
 *
 * Also includes: dental hygiene products (toothpastes, mouthwashes,
 * brushes, Oracura), desensitizers, fluoride agents, topical gels.
 *
 * Run AFTER RxMasterSeeder (depends on categories, generics, routes, food_instructions).
 */
class RxDentalBrandsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Additional Drug Categories ────────────────────────────────────────
        $newCategories = [
            ['name' => 'Enzyme / Anti-oedema',  'description' => 'Proteolytic enzymes for swelling, oedema resolution'],
            ['name' => 'Desensitizer',           'description' => 'Topical agents for dentinal hypersensitivity'],
            ['name' => 'Fluoride Agent',         'description' => 'Fluoride varnishes, gels and rinses for caries prevention'],
            ['name' => 'Dental Hygiene Product', 'description' => 'Toothpastes, mouthwashes, brushes, irrigators'],
            ['name' => 'Whitening Agent',        'description' => 'Tooth whitening / bleaching agents'],
            ['name' => 'Local Anaesthetic',      'description' => 'Injectable and topical local anaesthetics'],
            ['name' => 'Bone Support',           'description' => 'Bone regeneration, calcium and vitamin supplements'],
            ['name' => 'Multivitamin',           'description' => 'Multivitamin and B-complex supplements'],
            ['name' => 'Antiallergic',           'description' => 'Antihistamines and anti-allergic agents'],
        ];
        DB::table('rx_drug_categories')->insertOrIgnore(
            array_map(fn($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $newCategories)
        );

        // ── Additional Generics ───────────────────────────────────────────────
        $newGenerics = [
            // Enzymes
            ['name' => 'Serratiopeptidase',                    'drug_class' => 'Proteolytic Enzyme'],
            ['name' => 'Trypsin-Chymotrypsin',                 'drug_class' => 'Proteolytic Enzyme'],
            ['name' => 'Bromelain',                            'drug_class' => 'Proteolytic Enzyme'],
            // NSAIDs / Combinations
            ['name' => 'Aceclofenac + Paracetamol',           'drug_class' => 'NSAID Combination'],
            ['name' => 'Aceclofenac + Paracetamol + Serratiopeptidase', 'drug_class' => 'NSAID Combination'],
            ['name' => 'Diclofenac + Serratiopeptidase',       'drug_class' => 'NSAID Combination'],
            ['name' => 'Ibuprofen + Paracetamol',              'drug_class' => 'NSAID Combination'],
            ['name' => 'Diclofenac + Paracetamol',             'drug_class' => 'NSAID Combination'],
            ['name' => 'Nimesulide + Paracetamol',             'drug_class' => 'NSAID Combination'],
            ['name' => 'Aceclofenac',                          'drug_class' => 'NSAID'],
            ['name' => 'Etoricoxib',                           'drug_class' => 'COX-2 Inhibitor'],
            ['name' => 'Aspirin',                              'drug_class' => 'NSAID'],
            ['name' => 'Metamizole',                           'drug_class' => 'Analgesic'],
            // Antibiotics
            ['name' => 'Levofloxacin',                         'drug_class' => 'Fluoroquinolone'],
            ['name' => 'Cefpodoxime',                          'drug_class' => 'Cephalosporin'],
            ['name' => 'Cefuroxime',                           'drug_class' => 'Cephalosporin'],
            ['name' => 'Cefixime',                             'drug_class' => 'Cephalosporin'],
            ['name' => 'Clarithromycin',                       'drug_class' => 'Macrolide'],
            ['name' => 'Amoxicillin + Clavulanate + Serratiopeptidase', 'drug_class' => 'Penicillin Combination'],
            // Local Anaesthetics
            ['name' => 'Lignocaine + Adrenaline',              'drug_class' => 'Local Anaesthetic'],
            ['name' => 'Articaine + Adrenaline',               'drug_class' => 'Local Anaesthetic'],
            ['name' => 'Mepivacaine',                          'drug_class' => 'Local Anaesthetic'],
            ['name' => 'Benzocaine',                           'drug_class' => 'Local Anaesthetic'],
            // Desensitizers
            ['name' => 'Potassium Nitrate',                    'drug_class' => 'Desensitizer'],
            ['name' => 'Stannous Fluoride',                    'drug_class' => 'Desensitizer / Fluoride'],
            ['name' => 'Sodium Fluoride',                      'drug_class' => 'Fluoride Agent'],
            ['name' => 'Casein Phosphopeptide-ACP',            'drug_class' => 'Remineralizer'],
            ['name' => 'Potassium Oxalate',                    'drug_class' => 'Desensitizer'],
            ['name' => 'Glutaraldehyde',                       'drug_class' => 'Desensitizer'],
            // Antiseptics / Oral hygiene
            ['name' => 'Cetylpyridinium Chloride',             'drug_class' => 'Antiseptic'],
            ['name' => 'Essential Oils (Eucalyptol/Thymol)',   'drug_class' => 'Antiseptic'],
            ['name' => 'Hydrogen Peroxide',                    'drug_class' => 'Antiseptic / Whitening'],
            ['name' => 'Carbamide Peroxide',                   'drug_class' => 'Whitening Agent'],
            ['name' => 'Sodium Bicarbonate',                   'drug_class' => 'Oral Hygiene'],
            ['name' => 'Xylitol',                              'drug_class' => 'Caries Prevention'],
            ['name' => 'Triclosan + Copolymer',                'drug_class' => 'Antiseptic'],
            // Vitamins / Minerals / Combos
            ['name' => 'Calcium + Vitamin D3',                 'drug_class' => 'Supplement'],
            ['name' => 'Calcium + Vitamin D3 + Zinc',          'drug_class' => 'Supplement'],
            ['name' => 'Vitamin B-Complex',                    'drug_class' => 'Vitamin'],
            ['name' => 'Zinc',                                 'drug_class' => 'Mineral'],
            ['name' => 'Multivitamin + Multimineral',          'drug_class' => 'Supplement'],
            // Antiallergic
            ['name' => 'Levocetizine',                         'drug_class' => 'Antihistamine'],
            ['name' => 'Fexofenadine',                         'drug_class' => 'Antihistamine'],
            ['name' => 'Chlorpheniramine Maleate',             'drug_class' => 'Antihistamine'],
            // Topical steroids
            ['name' => 'Triamcinolone Acetonide',              'drug_class' => 'Topical Corticosteroid'],
            ['name' => 'Clobetasol',                           'drug_class' => 'Topical Corticosteroid'],
            ['name' => 'Betamethasone',                        'drug_class' => 'Corticosteroid'],
            // Antiviral
            ['name' => 'Acyclovir',                            'drug_class' => 'Antiviral'],
            // Proton pump inhibitors
            ['name' => 'Rabeprazole',                          'drug_class' => 'PPI'],
            ['name' => 'Esomeprazole',                         'drug_class' => 'PPI'],
            // Haemostatic
            ['name' => 'Thrombin',                             'drug_class' => 'Haemostatic'],
            ['name' => 'Gelatin Sponge',                       'drug_class' => 'Haemostatic'],
            ['name' => 'Absorbable Collagen',                  'drug_class' => 'Haemostatic'],
            // Oral mucosa
            ['name' => 'Aloe Vera',                            'drug_class' => 'Emollient / Healing'],
            ['name' => 'Hyaluronic Acid',                      'drug_class' => 'Wound Healing'],
            ['name' => 'Lycopene',                             'drug_class' => 'Antioxidant'],
        ];
        DB::table('rx_generics')->insertOrIgnore(
            array_map(fn($r) => array_merge($r, ['notes' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]), $newGenerics)
        );

        // ── Fetch IDs ─────────────────────────────────────────────────────────
        $catId     = fn($name) => DB::table('rx_drug_categories')->where('name', $name)->value('id');
        $genId     = fn($name) => DB::table('rx_generics')->where('name', $name)->value('id');
        $routeOral = DB::table('rx_routes_of_admin')->where('abbreviation', 'PO')->value('id');
        $routeTop  = DB::table('rx_routes_of_admin')->where('abbreviation', 'TOP')->value('id');
        $routeMW   = DB::table('rx_routes_of_admin')->where('abbreviation', 'MW')->value('id');
        $routeSub  = DB::table('rx_routes_of_admin')->where('abbreviation', 'SUB')->value('id');
        $routeIM   = DB::table('rx_routes_of_admin')->where('abbreviation', 'IM')->value('id');
        $fiAfter   = DB::table('rx_food_instructions')->where('code', 'AFTER_FOOD')->value('id');
        $fiBefore  = DB::table('rx_food_instructions')->where('code', 'BEFORE_FOOD')->value('id');
        $fiAny     = DB::table('rx_food_instructions')->where('code', 'ANY_TIME')->value('id');

        // ── 120+ Indian Brand Drugs ───────────────────────────────────────────
        // Format: brand_name, generic_id, category_id, strength, dosage_form,
        //         route_id, default_duration, default_food_instruction_id,
        //         composition (if combo), duplicate_molecule_group,
        //         antibiotic_class, pregnancy_category, contraindications,
        //         common_dental_uses, notes (brand/company info)

        $drugs = [

            // ═══════════════════════════════════════════════════════════════════
            // ANALGESICS & NSAIDs
            // ═══════════════════════════════════════════════════════════════════

            // --- Ibuprofen brands ---
            ['brand_name' => 'Brufen 600',         'generic_id' => $genId('Ibuprofen'),  'category_id' => $catId('Anti-inflammatory'), 'strength' => '600mg',      'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'ibuprofen',    'pregnancy_category' => 'C', 'common_dental_uses' => 'Moderate-severe dental pain, post-op', 'notes' => 'Abbott India'],
            ['brand_name' => 'Ibugesic Plus',       'generic_id' => $genId('Ibuprofen + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '400+325mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'ibuprofen,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain with fever', 'notes' => 'Cipla'],
            ['brand_name' => 'Flexon',              'generic_id' => $genId('Ibuprofen + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '400+325mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'ibuprofen,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain', 'notes' => 'Aristo Pharmaceuticals'],
            ['brand_name' => 'Ibugesic 200',        'generic_id' => $genId('Ibuprofen'),  'category_id' => $catId('Anti-inflammatory'), 'strength' => '200mg',      'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'ibuprofen',    'pregnancy_category' => 'C', 'common_dental_uses' => 'Mild dental pain, paediatric', 'notes' => 'Cipla'],

            // --- Diclofenac brands ---
            ['brand_name' => 'Voveran SR 100',      'generic_id' => $genId('Diclofenac'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100mg SR',   'dosage_form' => 'Tablet',   'route_id' => $routeOral, 'default_duration' => 5,  'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'diclofenac',   'pregnancy_category' => 'C', 'common_dental_uses' => 'Prolonged pain relief post-surgery', 'notes' => 'Novartis / Sun Pharma'],
            ['brand_name' => 'Diclomol',            'generic_id' => $genId('Diclofenac + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '50+500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'diclofenac,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain and fever', 'notes' => 'Cipla'],
            ['brand_name' => 'Reactin Plus',        'generic_id' => $genId('Diclofenac + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '50+500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'diclofenac,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-op dental pain', 'notes' => 'Mankind Pharma'],
            ['brand_name' => 'Diclofenac Gel (Voveran)', 'generic_id' => $genId('Diclofenac'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '1%', 'dosage_form' => 'Topical Gel', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'diclofenac', 'pregnancy_category' => 'C', 'common_dental_uses' => 'TMJ pain, extraoral application', 'notes' => 'Novartis / Sun Pharma'],

            // --- Aceclofenac brands ---
            ['brand_name' => 'Zerodol P',           'generic_id' => $genId('Aceclofenac + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'aceclofenac,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain and inflammation', 'notes' => 'IPCA Laboratories'],
            ['brand_name' => 'Zerodol SP',          'generic_id' => $genId('Aceclofenac + Paracetamol + Serratiopeptidase'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+325+10mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Aceclofenac 100mg + Paracetamol 325mg + Serratiopeptidase 10mg', 'duplicate_molecule_group' => 'aceclofenac', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-op swelling and pain, pericoronitis', 'notes' => 'IPCA Laboratories — very popular dental Rx'],
            ['brand_name' => 'Hifenac SP',          'generic_id' => $genId('Aceclofenac + Paracetamol + Serratiopeptidase'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+325+10mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Aceclofenac 100mg + Paracetamol 325mg + Serratiopeptidase 10mg', 'duplicate_molecule_group' => 'aceclofenac', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Swelling and pain post-procedure', 'notes' => 'Intas Pharmaceuticals'],
            ['brand_name' => 'Acekind P',           'generic_id' => $genId('Aceclofenac + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'aceclofenac,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain', 'notes' => 'Mankind Pharma'],
            ['brand_name' => 'Acenac P',            'generic_id' => $genId('Aceclofenac + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+325mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'aceclofenac,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain', 'notes' => 'IPCA Laboratories'],
            ['brand_name' => 'Hifenac 100',         'generic_id' => $genId('Aceclofenac'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'aceclofenac', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain and inflammation', 'notes' => 'Intas Pharmaceuticals'],

            // --- Nimesulide combos ---
            ['brand_name' => 'Nicip Plus',          'generic_id' => $genId('Nimesulide + Paracetamol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100+325mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'nimesulide,paracetamol', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain with fever', 'notes' => 'Mankind Pharma'],
            ['brand_name' => 'Nimulid MD',          'generic_id' => $genId('Nimesulide'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '100mg', 'dosage_form' => 'Mouth Dissolving Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'nimesulide', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain', 'notes' => 'Panacea Biotec'],
            ['brand_name' => 'Novalgin',            'generic_id' => $genId('Metamizole'), 'category_id' => $catId('Analgesic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'metamizole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Severe toothache, post-extraction pain', 'notes' => 'Sanofi India'],

            // --- Etoricoxib ---
            ['brand_name' => 'Nucoxia 90',          'generic_id' => $genId('Etoricoxib'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '90mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'etoricoxib', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Acute dental pain, post-surgical', 'contraindications' => 'Cardiovascular disease, peptic ulcer', 'notes' => 'Sun Pharma — COX-2 selective, less GI risk'],
            ['brand_name' => 'Arcoxia 90',          'generic_id' => $genId('Etoricoxib'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '90mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'etoricoxib', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental pain, pericoronitis', 'notes' => 'MSD / Merck India'],

            // --- Serratiopeptidase standalone ---
            ['brand_name' => 'Serratace 5mg',       'generic_id' => $genId('Serratiopeptidase'), 'category_id' => $catId('Enzyme / Anti-oedema'), 'strength' => '5mg', 'dosage_form' => 'Enteric Coated Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'serratiopeptidase', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-op oedema, trismus', 'notes' => 'Ranbaxy / Sun Pharma'],
            ['brand_name' => 'Serodase 5mg',        'generic_id' => $genId('Serratiopeptidase'), 'category_id' => $catId('Enzyme / Anti-oedema'), 'strength' => '5mg', 'dosage_form' => 'Enteric Coated Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'serratiopeptidase', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Oedema post-surgery', 'notes' => 'Elder Pharmaceuticals'],
            ['brand_name' => 'Sermion 10',          'generic_id' => $genId('Serratiopeptidase'), 'category_id' => $catId('Enzyme / Anti-oedema'), 'strength' => '10mg', 'dosage_form' => 'Enteric Coated Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'serratiopeptidase', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Pericoronitis oedema, post-implant swelling', 'notes' => 'Wockhardt'],
            ['brand_name' => 'Chymoral Forte',      'generic_id' => $genId('Trypsin-Chymotrypsin'), 'category_id' => $catId('Enzyme / Anti-oedema'), 'strength' => '1,00,000 AU', 'dosage_form' => 'Enteric Coated Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'trypsin-chymotrypsin', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-surgical swelling, trauma oedema', 'notes' => 'Torrent Pharmaceuticals — widely used in dental practice'],

            // ═══════════════════════════════════════════════════════════════════
            // ANTIBIOTICS — Additional Indian Brands
            // ═══════════════════════════════════════════════════════════════════

            // --- Amoxicillin brands ---
            ['brand_name' => 'Novamox 500',         'generic_id' => $genId('Amoxicillin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental abscess, periapical infection', 'notes' => 'Cipla'],
            ['brand_name' => 'Moxikind 500',        'generic_id' => $genId('Amoxicillin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections', 'notes' => 'Mankind Pharma'],
            ['brand_name' => 'Amoxil 250 Syrup',    'generic_id' => $genId('Amoxicillin'), 'category_id' => $catId('Antibiotic'), 'strength' => '250mg/5ml', 'dosage_form' => 'Syrup', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Paediatric dental infections', 'notes' => 'GSK India — paediatric formulation'],
            ['brand_name' => 'Wymox 500',           'generic_id' => $genId('Amoxicillin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections', 'notes' => 'Wyeth / Pfizer India'],

            // --- Amoxicillin + Clavulanate brands ---
            ['brand_name' => 'Moxikind CV 625',     'generic_id' => $genId('Amoxicillin-Clavulanate'), 'category_id' => $catId('Antibiotic'), 'strength' => '625mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Severe dental infections, resistant organisms', 'notes' => 'Mankind Pharma'],
            ['brand_name' => 'Clavam 625',          'generic_id' => $genId('Amoxicillin-Clavulanate'), 'category_id' => $catId('Antibiotic'), 'strength' => '625mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental abscess, OMFS infections', 'notes' => 'Alembic Pharmaceuticals'],
            ['brand_name' => 'Augmentin 1000 mg',   'generic_id' => $genId('Amoxicillin-Clavulanate'), 'category_id' => $catId('Antibiotic'), 'strength' => '1000mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Deep space infections, severe OMFS', 'notes' => 'GSK India'],
            ['brand_name' => 'Clavam Duo 400',      'generic_id' => $genId('Amoxicillin-Clavulanate'), 'category_id' => $catId('Antibiotic'), 'strength' => '400+57mg/5ml', 'dosage_form' => 'Suspension', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'amoxicillin', 'antibiotic_class' => 'penicillin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Paediatric dental infections', 'notes' => 'Alembic — paediatric suspension'],

            // --- Metronidazole brands ---
            ['brand_name' => 'Metrogyl 400',        'generic_id' => $genId('Metronidazole'), 'category_id' => $catId('Antibiotic'), 'strength' => '400mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'metronidazole', 'antibiotic_class' => 'nitroimidazole', 'pregnancy_category' => 'B', 'common_dental_uses' => 'ANUG, periodontal disease, pericoronitis', 'notes' => 'Abbott India / Pfizer'],
            ['brand_name' => 'Aldezole 400',        'generic_id' => $genId('Metronidazole'), 'category_id' => $catId('Antibiotic'), 'strength' => '400mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'metronidazole', 'antibiotic_class' => 'nitroimidazole', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Periodontal infections', 'notes' => 'Elder Pharmaceuticals'],

            // --- Tinidazole brands ---
            ['brand_name' => 'Tinilox 500',         'generic_id' => $genId('Tinidazole'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'tinidazole', 'antibiotic_class' => 'nitroimidazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Periodontal / anaerobic infections', 'notes' => 'Elder Pharmaceuticals'],
            ['brand_name' => 'Fasigyn 500',         'generic_id' => $genId('Tinidazole'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'tinidazole', 'antibiotic_class' => 'nitroimidazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Gingival infections', 'notes' => 'Pfizer India'],

            // --- Azithromycin brands ---
            ['brand_name' => 'Azithral 500',        'generic_id' => $genId('Azithromycin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'azithromycin', 'antibiotic_class' => 'macrolide', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Penicillin-allergic patients, dental infections', 'notes' => 'Alembic Pharmaceuticals — most prescribed brand'],
            ['brand_name' => 'Zithromax 500',       'generic_id' => $genId('Azithromycin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'azithromycin', 'antibiotic_class' => 'macrolide', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections, penicillin allergy', 'notes' => 'Pfizer India'],
            ['brand_name' => 'Azifast 500',         'generic_id' => $genId('Azithromycin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'azithromycin', 'antibiotic_class' => 'macrolide', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections', 'notes' => 'Zydus Cadila'],

            // --- Ciprofloxacin brands ---
            ['brand_name' => 'Cifran 500',          'generic_id' => $genId('Ciprofloxacin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'ciprofloxacin', 'antibiotic_class' => 'fluoroquinolone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Severe OMFS infections, resistant cases', 'notes' => 'Cipla'],
            ['brand_name' => 'Ciprobid 500',        'generic_id' => $genId('Ciprofloxacin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'ciprofloxacin', 'antibiotic_class' => 'fluoroquinolone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental infections', 'notes' => 'IPCA Laboratories'],

            // --- Levofloxacin brands ---
            ['brand_name' => 'Levoflox 500',        'generic_id' => $genId('Levofloxacin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'levofloxacin', 'antibiotic_class' => 'fluoroquinolone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Orofacial infections, resistant cases', 'notes' => 'Dr. Reddy\'s Laboratories'],
            ['brand_name' => 'Levorid 500',         'generic_id' => $genId('Levofloxacin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'levofloxacin', 'antibiotic_class' => 'fluoroquinolone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental and oral infections', 'notes' => 'Mankind Pharma'],

            // --- Cephalosporin brands ---
            ['brand_name' => 'Ceftas 200',          'generic_id' => $genId('Cefixime'), 'category_id' => $catId('Antibiotic'), 'strength' => '200mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'cefixime', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections', 'notes' => 'Torrent Pharmaceuticals'],
            ['brand_name' => 'Magnex 200',          'generic_id' => $genId('Cefixime'), 'category_id' => $catId('Antibiotic'), 'strength' => '200mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'cefixime', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections', 'notes' => 'Pfizer India'],
            ['brand_name' => 'Cepodem 200',         'generic_id' => $genId('Cefpodoxime'), 'category_id' => $catId('Antibiotic'), 'strength' => '200mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'cefpodoxime', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections, OMFS', 'notes' => 'Cipla'],
            ['brand_name' => 'Cefoprox 200',        'generic_id' => $genId('Cefpodoxime'), 'category_id' => $catId('Antibiotic'), 'strength' => '200mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'cefpodoxime', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections', 'notes' => 'Micro Labs'],
            ['brand_name' => 'Cefakind 500',        'generic_id' => $genId('Cephalexin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'cephalexin', 'antibiotic_class' => 'cephalosporin', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Dental infections, prophylaxis (penicillin alternative)', 'notes' => 'Mankind Pharma'],

            // --- Clarithromycin brands ---
            ['brand_name' => 'Claribid 500',        'generic_id' => $genId('Clarithromycin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'clarithromycin', 'antibiotic_class' => 'macrolide', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental infections (macrolide alternative)', 'notes' => 'Abbott India'],
            ['brand_name' => 'Klacid 500',          'generic_id' => $genId('Clarithromycin'), 'category_id' => $catId('Antibiotic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'clarithromycin', 'antibiotic_class' => 'macrolide', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Dental infections', 'notes' => 'Abbott India'],

            // --- Doxycycline brands ---
            ['brand_name' => 'Doxt SL',             'generic_id' => $genId('Doxycycline'), 'category_id' => $catId('Antibiotic'), 'strength' => '100mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'doxycycline', 'antibiotic_class' => 'tetracycline', 'pregnancy_category' => 'D', 'common_dental_uses' => 'Periodontal disease, systemic antibiotic for scaling', 'notes' => 'Mankind Pharma — SL = sustained release'],
            ['brand_name' => 'Periostat 20',        'generic_id' => $genId('Doxycycline'), 'category_id' => $catId('Antibiotic'), 'strength' => '20mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 90, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'doxycycline', 'antibiotic_class' => 'tetracycline', 'pregnancy_category' => 'D', 'common_dental_uses' => 'Sub-antimicrobial dose for chronic periodontitis', 'notes' => 'Sub-antimicrobial doxycycline — adjunct to SRP'],

            // ═══════════════════════════════════════════════════════════════════
            // ANTISEPTICS / MOUTHWASHES — Indian Brands
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Clohex ADS',          'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.2%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-scaling, post-extraction, pericoronitis', 'notes' => 'Dr. Reddy\'s Laboratories'],
            ['brand_name' => 'Clohex Plus',         'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.2%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Gingivitis, pericoronitis', 'notes' => 'Dr. Reddy\'s — with zinc'],
            ['brand_name' => 'Hexidine Plus',       'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.2%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Plaque control, gingivitis', 'notes' => 'ICPA Health Products'],
            ['brand_name' => 'Periogard Mouthwash', 'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.12%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-SRP, implant maintenance', 'notes' => 'Colgate-Palmolive India'],
            ['brand_name' => 'Listerine Cool Mint', 'generic_id' => $genId('Essential Oils (Eucalyptol/Thymol)'), 'category_id' => $catId('Antiseptic'), 'strength' => '0.092%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 30, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Daily plaque control, gingivitis prevention', 'notes' => 'Johnson & Johnson India — OTC antiseptic mouthwash'],
            ['brand_name' => 'Colgate Plax Mouthwash', 'generic_id' => $genId('Cetylpyridinium Chloride'), 'category_id' => $catId('Dental Hygiene Product'), 'strength' => '0.05%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 30, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Daily oral hygiene, plaque control', 'notes' => 'Colgate-Palmolive India — OTC'],
            ['brand_name' => 'Oral B Mouthwash Pro-Expert', 'generic_id' => $genId('Cetylpyridinium Chloride'), 'category_id' => $catId('Dental Hygiene Product'), 'strength' => '0.1%', 'dosage_form' => 'Mouthwash', 'route_id' => $routeMW, 'default_duration' => 30, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Daily oral hygiene, remineralization', 'notes' => 'P&G India — OTC'],

            // ═══════════════════════════════════════════════════════════════════
            // TOPICAL DENTAL GELS — Indian Brands (ICPA, Dr. Reddy's, etc.)
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Hexigel',             'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '1%', 'dosage_form' => 'Oral Gel', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Periodontal gel, post-extraction socket, ulcers', 'notes' => 'ICPA Health Products — popular Indian dental gel'],
            ['brand_name' => 'Clohex Gel',          'generic_id' => $genId('Chlorhexidine'), 'category_id' => $catId('Antiseptic'), 'strength' => '1%', 'dosage_form' => 'Oral Gel', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorhexidine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Subgingival / pericoronal gel', 'notes' => 'Dr. Reddy\'s Laboratories'],
            ['brand_name' => 'Xylocaine Viscous 2%','generic_id' => $genId('Lignocaine'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '2%', 'dosage_form' => 'Viscous Solution', 'route_id' => $routeTop, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'lignocaine', 'common_dental_uses' => 'Topical LA before injection, ulcer pain, mucositis', 'notes' => 'AstraZeneca / ICPA India — standard topical LA'],
            ['brand_name' => 'Xylonor Gel',         'generic_id' => $genId('Lignocaine'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '2%', 'dosage_form' => 'Topical Gel', 'route_id' => $routeTop, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'lignocaine', 'common_dental_uses' => 'Pre-injection mucosal anaesthesia', 'notes' => 'Septodont India — dental professional product'],
            ['brand_name' => 'Topicaine Gel',       'generic_id' => $genId('Benzocaine'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '20%', 'dosage_form' => 'Topical Gel', 'route_id' => $routeTop, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'benzocaine', 'common_dental_uses' => 'Topical surface anaesthesia before scaling, extraction', 'notes' => 'Dental topical anaesthetic gel'],
            ['brand_name' => 'Kenalog in Orabase',  'generic_id' => $genId('Triamcinolone Acetonide'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.1%', 'dosage_form' => 'Oral Paste', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Aphthous ulcers, oral lichen planus, pemphigus', 'notes' => 'Bristol-Myers Squibb — topical steroid in adhesive base'],
            ['brand_name' => 'Triamcinolone Dental Paste', 'generic_id' => $genId('Triamcinolone Acetonide'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.1%', 'dosage_form' => 'Oral Paste', 'route_id' => $routeTop, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Recurrent aphthous stomatitis, oral mucosal lesions'],
            ['brand_name' => 'Clobetasol Gel',      'generic_id' => $genId('Clobetasol'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.05%', 'dosage_form' => 'Oral Gel', 'route_id' => $routeTop, 'default_duration' => 14, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Oral lichen planus, severe ulcers, OSMF', 'notes' => 'Available as multiple brands — Temovate, Dermovate'],
            ['brand_name' => 'Acyclovir Cream 5%',  'generic_id' => $genId('Acyclovir'), 'category_id' => $catId('Antiviral'), 'strength' => '5%', 'dosage_form' => 'Cream', 'route_id' => $routeTop, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'acyclovir', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Herpes labialis (cold sores), herpetic lesions', 'notes' => 'Multiple brands: Zovirax, Herpex — topical'],
            ['brand_name' => 'Acivir 200',          'generic_id' => $genId('Acyclovir'), 'category_id' => $catId('Antiviral'), 'strength' => '200mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'acyclovir', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Herpes labialis, primary herpetic gingivostomatitis', 'notes' => 'Cipla — oral antiviral'],
            ['brand_name' => 'Zovirax 400',         'generic_id' => $genId('Acyclovir'), 'category_id' => $catId('Antiviral'), 'strength' => '400mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'acyclovir', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Herpes labialis, oral herpes', 'notes' => 'GSK India'],
            ['brand_name' => 'Lycopoderm Gel',      'generic_id' => $genId('Lycopene'), 'category_id' => $catId('Vitamin / Mineral'), 'strength' => '2mg/5g', 'dosage_form' => 'Oral Gel', 'route_id' => $routeTop, 'default_duration' => 30, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'OSMF (Oral Submucous Fibrosis), leukoplakia, oral cancer prevention'],

            // ═══════════════════════════════════════════════════════════════════
            // INJECTABLE LOCAL ANAESTHETICS
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Xylocaine 2% with Adrenaline', 'generic_id' => $genId('Lignocaine + Adrenaline'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '2% + 1:80000', 'dosage_form' => 'Dental Cartridge', 'route_id' => $routeIM, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Standard dental LA for all procedures', 'notes' => 'AstraZeneca / ICPA — most widely used dental LA in India'],
            ['brand_name' => 'Lignospan Special',   'generic_id' => $genId('Lignocaine + Adrenaline'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '2% + 1:80000', 'dosage_form' => 'Dental Cartridge', 'route_id' => $routeIM, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Dental LA — all procedures', 'notes' => 'Septodont India'],
            ['brand_name' => 'Septanest 4%',        'generic_id' => $genId('Articaine + Adrenaline'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '4% + 1:100000', 'dosage_form' => 'Dental Cartridge', 'route_id' => $routeIM, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Infiltration anaesthesia, difficult mandibular blocks', 'notes' => 'Septodont India — superior bone penetration'],
            ['brand_name' => 'Xylocaine Plain 2%',  'generic_id' => $genId('Lignocaine'), 'category_id' => $catId('Local Anaesthetic'), 'strength' => '2%', 'dosage_form' => 'Dental Cartridge', 'route_id' => $routeIM, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'LA for patients where adrenaline is contraindicated', 'notes' => 'For cardiac patients, hyperthyroid'],

            // ═══════════════════════════════════════════════════════════════════
            // VITAMINS, MINERALS, BONE SUPPORT
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Shelcal CT',          'generic_id' => $genId('Calcium + Vitamin D3 + Zinc'), 'category_id' => $catId('Bone Support'), 'strength' => '500mg+250IU+4mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 90, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Calcium 500mg + Vitamin D3 250IU + Zinc 4mg', 'duplicate_molecule_group' => 'calcium', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Bone healing post-implant, post-extraction, osteoporosis support', 'notes' => 'Torrent Pharmaceuticals — popular bone supplement'],
            ['brand_name' => 'OsteoCare',           'generic_id' => $genId('Calcium + Vitamin D3'), 'category_id' => $catId('Bone Support'), 'strength' => '500mg+400IU', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 90, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Calcium 500mg + Vitamin D3 400IU + Magnesium', 'duplicate_molecule_group' => 'calcium', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Implant osseointegration support, post-surgery', 'notes' => 'Vitabiotics India'],
            ['brand_name' => 'Calcimax Forte',      'generic_id' => $genId('Calcium + Vitamin D3'), 'category_id' => $catId('Bone Support'), 'strength' => '500mg+200IU', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 60, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'calcium', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Bone density, post-implant, jaw fracture healing', 'notes' => 'IPCA Laboratories'],
            ['brand_name' => 'D-Cal 60000',         'generic_id' => $genId('Vitamin D3'), 'category_id' => $catId('Bone Support'), 'strength' => '60000 IU', 'dosage_form' => 'Softgel Capsule', 'route_id' => $routeOral, 'default_duration' => 12, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'vitamin-d3', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Vitamin D deficiency, implant support, periodontal health', 'notes' => 'Dr. Reddy\'s — once-weekly dosing'],
            ['brand_name' => 'Becosules Capsules',  'generic_id' => $genId('Vitamin B-Complex'), 'category_id' => $catId('Multivitamin'), 'strength' => 'B-Complex+C', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 30, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Vitamin B1+B2+B6+B12+Folic Acid+Niacin+Pantothenic Acid+Vitamin C', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Angular cheilitis, aphthous ulcers (B-deficiency), glossitis', 'notes' => 'Pfizer India — widely prescribed for oral mucosal issues'],
            ['brand_name' => 'Neurobion Forte',     'generic_id' => $genId('Vitamin B-Complex'), 'category_id' => $catId('Multivitamin'), 'strength' => 'B1+B6+B12', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 30, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Vitamin B1 10mg + B6 3mg + B12 15mcg', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Nerve pain post-RCT, burning mouth syndrome, neuropathy', 'notes' => 'Merck India — for nerve-related dental symptoms'],
            ['brand_name' => 'Zincovit',            'generic_id' => $genId('Multivitamin + Multimineral'), 'category_id' => $catId('Multivitamin'), 'strength' => 'Multi', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 30, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Zinc + Vitamins A, C, E + B-complex + Selenium + Grape Seed Extract', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Wound healing, immunity post-surgery, ulcer healing', 'notes' => 'Apex Laboratories — popular post-op supplement'],
            ['brand_name' => 'Revital H',           'generic_id' => $genId('Multivitamin + Multimineral'), 'category_id' => $catId('Multivitamin'), 'strength' => 'Multi', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 30, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Multivitamins + Ginseng + Minerals', 'pregnancy_category' => 'A', 'common_dental_uses' => 'Post-major surgery recovery, immunocompromised patients', 'notes' => 'Ranbaxy / Sun Pharma'],
            ['brand_name' => 'Lycopene 10mg',       'generic_id' => $genId('Lycopene'), 'category_id' => $catId('Vitamin / Mineral'), 'strength' => '10mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 90, 'default_food_instruction_id' => $fiAfter, 'common_dental_uses' => 'OSMF management, oral precancer, antioxidant', 'notes' => 'Multiple brands — Lycored, Lycopin'],

            // ═══════════════════════════════════════════════════════════════════
            // PPI / ANTACIDS — Additional Brands
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Razo 20',             'generic_id' => $genId('Rabeprazole'), 'category_id' => $catId('Antacid / PPI'), 'strength' => '20mg', 'dosage_form' => 'Enteric Coated Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'rabeprazole', 'pregnancy_category' => 'B', 'common_dental_uses' => 'GI protection with NSAIDs', 'notes' => 'Dr. Reddy\'s Laboratories'],
            ['brand_name' => 'Neksium 20',          'generic_id' => $genId('Esomeprazole'), 'category_id' => $catId('Antacid / PPI'), 'strength' => '20mg', 'dosage_form' => 'Capsule', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'esomeprazole', 'pregnancy_category' => 'B', 'common_dental_uses' => 'GI cover for NSAID prescription', 'notes' => 'AstraZeneca India'],
            ['brand_name' => 'Pantodac 40',         'generic_id' => $genId('Pantoprazole'), 'category_id' => $catId('Antacid / PPI'), 'strength' => '40mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiBefore, 'duplicate_molecule_group' => 'pantoprazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'GI protection with NSAIDs', 'notes' => 'Zydus Cadila'],

            // ═══════════════════════════════════════════════════════════════════
            // ANTIHISTAMINES
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Levocet 5',           'generic_id' => $genId('Levocetizine'), 'category_id' => $catId('Antiallergic'), 'strength' => '5mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'levocetirizine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-extraction allergic swelling, drug allergy reactions', 'notes' => 'UCB India / Dr. Reddy\'s'],
            ['brand_name' => 'Allegra 120',         'generic_id' => $genId('Fexofenadine'), 'category_id' => $catId('Antiallergic'), 'strength' => '120mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'fexofenadine', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Allergic reactions, non-sedating antihistamine post-op', 'notes' => 'Sanofi India'],
            ['brand_name' => 'Avil 25',             'generic_id' => $genId('Chlorpheniramine Maleate'), 'category_id' => $catId('Antiallergic'), 'strength' => '25mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'chlorpheniramine', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Acute allergic reactions, urticaria post-dental drug', 'notes' => 'Aventis / Sanofi India'],

            // ═══════════════════════════════════════════════════════════════════
            // HAEMOSTATICS
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Kapron 500',          'generic_id' => $genId('Tranexamic Acid'), 'category_id' => $catId('Haemostatic'), 'strength' => '500mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'tranexamic-acid', 'pregnancy_category' => 'B', 'common_dental_uses' => 'Post-extraction haemostasis in coagulopathy patients', 'notes' => 'Cipla'],
            ['brand_name' => 'Hemo-Pak Gelatin Sponge', 'generic_id' => $genId('Gelatin Sponge'), 'category_id' => $catId('Haemostatic'), 'strength' => null, 'dosage_form' => 'Local Haemostatic', 'route_id' => $routeTop, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Socket packing after extraction, haemostasis', 'notes' => 'Absorbable gelatin sponge placed in socket'],
            ['brand_name' => 'Surgispon',           'generic_id' => $genId('Gelatin Sponge'), 'category_id' => $catId('Haemostatic'), 'strength' => null, 'dosage_form' => 'Absorbable Sponge', 'route_id' => $routeTop, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Post-extraction socket, surgical haemostasis', 'notes' => 'Wockhardt — absorbable gelatin haemostat'],
            ['brand_name' => 'Hemcol Collagen',     'generic_id' => $genId('Absorbable Collagen'), 'category_id' => $catId('Haemostatic'), 'strength' => null, 'dosage_form' => 'Absorbable Membrane', 'route_id' => $routeTop, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Extraction socket haemostasis, implant site', 'notes' => 'Collagen haemostat — placed intra-socket'],

            // ═══════════════════════════════════════════════════════════════════
            // DENTAL HYGIENE PRODUCTS — TOOTHPASTES
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Sensodyne Original',  'generic_id' => $genId('Potassium Nitrate'), 'category_id' => $catId('Desensitizer'), 'strength' => '5%', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Dentinal hypersensitivity, hot/cold sensitivity', 'notes' => 'GSK India — no. 1 sensitivity toothpaste'],
            ['brand_name' => 'Sensodyne Repair & Protect', 'generic_id' => $genId('Stannous Fluoride'), 'category_id' => $catId('Desensitizer'), 'strength' => '1000ppm F', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Hypersensitivity, enamel repair', 'notes' => 'GSK India — NovaMin technology'],
            ['brand_name' => 'Colgate Sensitive Pro-Relief', 'generic_id' => $genId('Casein Phosphopeptide-ACP'), 'category_id' => $catId('Desensitizer'), 'strength' => '1000ppm F', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Dentinal hypersensitivity', 'notes' => 'Colgate-Palmolive India — Pro-Argin technology'],
            ['brand_name' => 'Colgate Strong Teeth', 'generic_id' => $genId('Sodium Fluoride'), 'category_id' => $catId('Fluoride Agent'), 'strength' => '1000ppm F', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Caries prevention, general daily hygiene', 'notes' => 'Colgate-Palmolive India — most popular toothpaste'],
            ['brand_name' => 'Colgate Total',       'generic_id' => $genId('Triclosan + Copolymer'), 'category_id' => $catId('Dental Hygiene Product'), 'strength' => '0.3%+1000ppm F', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Anti-plaque, anti-gingivitis, daily hygiene', 'notes' => 'Colgate-Palmolive India'],
            ['brand_name' => 'Parodontax Toothpaste', 'generic_id' => $genId('Sodium Fluoride'), 'category_id' => $catId('Dental Hygiene Product'), 'strength' => '1450ppm F', 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'composition' => 'Sodium Fluoride + Herbal extracts (Sage, Chamomile, Echinacea)', 'common_dental_uses' => 'Bleeding gums, gingivitis, periodontal maintenance', 'notes' => 'GSK India — for gum disease patients'],
            ['brand_name' => 'Himalaya HiOra-EG',   'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'composition' => 'Meswak + Neem + Tomar Seed + Pomegranate', 'common_dental_uses' => 'Gum health, bleeding gums, Ayurvedic formulation', 'notes' => 'Himalaya Drug Company'],
            ['brand_name' => 'Vicco Vajradanti',    'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Toothpaste', 'route_id' => $routeTop, 'default_duration' => 60, 'default_food_instruction_id' => $fiAny, 'composition' => 'Ayurvedic herbs including Vajradanti, Meswak', 'common_dental_uses' => 'Traditional Ayurvedic oral care, gum health', 'notes' => 'Vicco Laboratories — Indian Ayurvedic brand'],
            ['brand_name' => 'GC Tooth Mousse',     'generic_id' => $genId('Casein Phosphopeptide-ACP'), 'category_id' => $catId('Desensitizer'), 'strength' => null, 'dosage_form' => 'Cream', 'route_id' => $routeTop, 'default_duration' => 30, 'default_food_instruction_id' => $fiAny, 'composition' => 'CPP-ACP (Recaldent)', 'common_dental_uses' => 'Remineralisation, sensitivity, post-whitening care, orthodontic patients', 'notes' => 'GC India — professional remineralising cream'],
            ['brand_name' => 'Fluoride Varnish Duraphat', 'generic_id' => $genId('Sodium Fluoride'), 'category_id' => $catId('Fluoride Agent'), 'strength' => '5%', 'dosage_form' => 'Varnish', 'route_id' => $routeTop, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'composition' => 'Sodium Fluoride 22600ppm in colophony base', 'common_dental_uses' => 'Caries prevention, hypersensitivity treatment, professional fluoride application', 'notes' => 'Colgate / Voco — in-office application'],
            ['brand_name' => 'Fluor Protector',     'generic_id' => $genId('Sodium Fluoride'), 'category_id' => $catId('Fluoride Agent'), 'strength' => '0.7%', 'dosage_form' => 'Varnish', 'route_id' => $routeTop, 'default_duration' => 1, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Caries prevention (professional), sensitivity', 'notes' => 'Ivoclar Vivadent India — professional use only'],

            // ═══════════════════════════════════════════════════════════════════
            // ORACURA & ORAL IRRIGATION PRODUCTS
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Oracura SL-909',      'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Oral Irrigator / Water Flosser', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'composition' => 'Electric oral irrigator device with multiple pressure settings', 'common_dental_uses' => 'Periodontal pocket irrigation, implant care, orthodontic hygiene, gum health', 'notes' => 'Oracura (Indian brand) — recommend for all periodontal and implant patients'],
            ['brand_name' => 'Oracura OC-200',      'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Cordless Water Flosser', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'composition' => 'Cordless oral irrigator — 3 pressure modes', 'common_dental_uses' => 'Daily interdental cleaning, implant hygiene, braces cleaning', 'notes' => 'Oracura India — travel-friendly model'],
            ['brand_name' => 'Oracura Sonic Toothbrush', 'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Electric Toothbrush', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Superior plaque removal, post-surgical care, gum disease prevention', 'notes' => 'Oracura India — sonic electric toothbrush'],

            // ═══════════════════════════════════════════════════════════════════
            // ORAL BRUSHES / HYGIENE DEVICES
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Oral-B 3D White Brush', 'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Manual Toothbrush', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Daily brushing, plaque removal, whitening'],
            ['brand_name' => 'Colgate 360 Toothbrush', 'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Manual Toothbrush', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Daily brushing with tongue and cheek cleaner'],
            ['brand_name' => 'Curaprox CS 5460 Brush', 'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Manual Toothbrush', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Ultra-soft bristles for sensitive gums, post-surgery, periodontal patients', 'notes' => 'Swiss brand available in India — recommended post-periodontal surgery'],
            ['brand_name' => 'TePe Interdental Brushes', 'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Interdental Brush', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Cleaning between teeth, implant care, orthodontic hygiene, periodontal patients', 'notes' => 'TePe — available multiple sizes (0.4mm to 1.5mm)'],
            ['brand_name' => 'Dental Floss Oral-B',  'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Dental Floss', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Interdental cleaning, plaque removal from proximal surfaces'],
            ['brand_name' => 'Waterpik WP-660',     'generic_id' => null, 'category_id' => $catId('Dental Hygiene Product'), 'strength' => null, 'dosage_form' => 'Countertop Water Flosser', 'route_id' => $routeTop, 'default_duration' => null, 'default_food_instruction_id' => $fiAny, 'common_dental_uses' => 'Periodontal irrigation, implant hygiene, post-surgical care, orthodontic patients', 'notes' => 'Available in India — countertop oral irrigator'],

            // ═══════════════════════════════════════════════════════════════════
            // ANTIFUNGAL — Additional Brands
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Forcan 150',          'generic_id' => $genId('Fluconazole'), 'category_id' => $catId('Antifungal'), 'strength' => '150mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'fluconazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Oral candidiasis', 'notes' => 'Cipla'],
            ['brand_name' => 'Zocon 150',           'generic_id' => $genId('Fluconazole'), 'category_id' => $catId('Antifungal'), 'strength' => '150mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 7, 'default_food_instruction_id' => $fiAny, 'duplicate_molecule_group' => 'fluconazole', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Oral candidiasis, angular cheilitis (fungal)', 'notes' => 'FDC Limited'],
            ['brand_name' => 'Nizoral Shampoo (for angular cheilitis)', 'generic_id' => null, 'category_id' => $catId('Antifungal'), 'strength' => '2%', 'dosage_form' => 'Topical', 'route_id' => $routeTop, 'default_duration' => 14, 'default_food_instruction_id' => $fiAny, 'composition' => 'Ketoconazole 2%', 'common_dental_uses' => 'Angular cheilitis (fungal type), perioral candidiasis', 'notes' => 'Johnson & Johnson India'],

            // ═══════════════════════════════════════════════════════════════════
            // STEROIDS — Additional Brands
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Betnesol 0.5mg',      'generic_id' => $genId('Betamethasone'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.5mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 5, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'betamethasone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Allergic stomatitis, severe oral ulcers, OSMF', 'notes' => 'GSK India — potent systemic steroid'],
            ['brand_name' => 'Decdan 0.5',          'generic_id' => $genId('Dexamethasone'), 'category_id' => $catId('Anti-inflammatory'), 'strength' => '0.5mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 3, 'default_food_instruction_id' => $fiAfter, 'duplicate_molecule_group' => 'dexamethasone', 'pregnancy_category' => 'C', 'common_dental_uses' => 'Post-surgical oedema, trismus', 'notes' => 'German Remedies / Pfizer India'],

            // ═══════════════════════════════════════════════════════════════════
            // MISCELLANEOUS — Widely used in Indian Dental Practice
            // ═══════════════════════════════════════════════════════════════════

            ['brand_name' => 'Eugenol (Clove Oil Dental)', 'generic_id' => null, 'category_id' => $catId('Analgesic'), 'strength' => null, 'dosage_form' => 'Topical Liquid', 'route_id' => $routeTop, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'composition' => 'Eugenol (clove oil derivative)', 'common_dental_uses' => 'Dry socket dressing, pulp capping, ZnOE dressing', 'notes' => 'Used with zinc oxide for dry socket and lining materials'],
            ['brand_name' => 'Alvogyl Dressing',    'generic_id' => null, 'category_id' => $catId('Analgesic'), 'strength' => null, 'dosage_form' => 'Impregnated Dressing', 'route_id' => $routeTop, 'default_duration' => 3, 'default_food_instruction_id' => $fiAny, 'composition' => 'Butamben + Iodoform + Eugenol', 'common_dental_uses' => 'Alveolar osteitis (dry socket) — socket dressing', 'notes' => 'Septodont India — standard dry socket dressing'],
            ['brand_name' => 'Myfortic 360 (for pemphigus)', 'generic_id' => null, 'category_id' => $catId('Antifungal'), 'strength' => '360mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 90, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Mycophenolate sodium', 'common_dental_uses' => 'Pemphigus vulgaris (in consultation with dermatologist)', 'notes' => 'Novartis — refer to specialist, mention only'],
            ['brand_name' => 'Dapsone 50',          'generic_id' => null, 'category_id' => $catId('Antibiotic'), 'strength' => '50mg', 'dosage_form' => 'Tablet', 'route_id' => $routeOral, 'default_duration' => 90, 'default_food_instruction_id' => $fiAfter, 'composition' => 'Dapsone 50mg', 'common_dental_uses' => 'Linear IgA disease, bullous conditions of oral cavity (specialist Rx)', 'notes' => 'Bayer India — refer to specialist'],
        ];

        // ── Normalise and insert drugs ────────────────────────────────────────
        foreach ($drugs as &$d) {
            $d['drug_code']               = null;
            $d['default_dose']            = null;
            $d['max_daily_dose']          = null;
            $d['renal_dose_adjustment']   = null;
            $d['hepatic_dose_adjustment'] = null;
            $d['drug_interactions_note']  = null;
            $d['antibiotic_class']        = $d['antibiotic_class']     ?? null;
            $d['is_controlled']           = $d['is_controlled']        ?? 0;
            $d['pregnancy_category']      = $d['pregnancy_category']   ?? null;
            $d['breastfeeding_safety']    = null;
            $d['pediatric_safety']        = null;
            $d['geriatric_caution']       = null;
            $d['contraindications']       = $d['contraindications']    ?? null;
            $d['duplicate_molecule_group']= $d['duplicate_molecule_group'] ?? null;
            $d['common_dental_uses']      = $d['common_dental_uses']   ?? null;
            $d['composition']             = $d['composition']          ?? null;
            $d['notes']                   = $d['notes']                ?? null;
            $d['is_active']               = 1;
            $d['created_at']              = now();
            $d['updated_at']              = now();
            $d['deleted_at']              = null;
        }
        unset($d);

        DB::table('rx_drugs')->insertOrIgnore($drugs);

        $count = count($drugs);
        $this->command->info("✅ RxDentalBrandsSeeder: inserted up to {$count} branded dental drugs (Indian brands).");
    }
}
