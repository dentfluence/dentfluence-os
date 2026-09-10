<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Clinical safety layer for the drug master.
 *
 * RxMasterSeeder lays down the masters and RxDentalBrandsSeeder the 122 Indian
 * brands. Both stop short of what the CDSS actually needs: the brands carry no
 * dosing text, no contraindications and no pregnancy / paediatric / geriatric
 * flags, and rx_drug_interaction_rules was never seeded at all — the interaction
 * check has been running against an empty table since it was written.
 *
 * Four passes:
 *   1. drugs()          — the drugs missing outright, with dosing and safety data
 *   2. enrichExisting() — backfills safety flags onto brands already in the master.
 *                         Without this the typeahead greys almost nothing, because
 *                         the existing rows are NULL in every column the grading reads.
 *   3. interactionRules() — including against the patient's recorded medication
 *   4. warningRules() / allergyRules() — conditions, plus lignocaine and iodine
 *
 * Idempotent: insertOrIgnore throughout, and enrichExisting only fills columns that
 * are still NULL, so anything hand-edited in Settings is never overwritten.
 *
 * ⚠️ CLINICAL REVIEW REQUIRED. The doses and contraindications here are standard
 * references for Indian dental practice, but they are a starting point for the
 * prescriber to verify, not an authority.
 */
class RxClinicalSafetySeeder extends Seeder
{
    public function run(): void
    {
        $this->categories();
        $this->generics();
        $this->drugs();
        $this->enrichExisting();
        $this->interactionRules();
        $this->warningRules();
        $this->allergyRules();

        $this->command->info('✅ Clinical safety layer seeded: gap drugs, safety flags, interaction / warning / allergy rules.');
    }

    private function categories(): void
    {
        $rows = [
            ['name' => 'Antiemetic',      'description' => 'Nausea and vomiting'],
            ['name' => 'Muscle Relaxant', 'description' => 'TMD, myofascial pain and bruxism'],
            ['name' => 'Preventive',      'description' => 'Fluoride, desensitisers and caries arrest'],
        ];

        DB::table('rx_drug_categories')->insertOrIgnore(array_map(
            fn ($r) => array_merge($r, ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]),
            $rows
        ));
    }

    private function generics(): void
    {
        $rows = [
            ['name' => 'Ondansetron',            'drug_class' => 'Antiemetic'],
            ['name' => 'Alprazolam',             'drug_class' => 'Benzodiazepine'],
            ['name' => 'Diazepam',               'drug_class' => 'Benzodiazepine'],
            ['name' => 'Chlorzoxazone',          'drug_class' => 'Muscle Relaxant'],
            ['name' => 'Thiocolchicoside',       'drug_class' => 'Muscle Relaxant'],
            ['name' => 'Clotrimazole',           'drug_class' => 'Azole Antifungal'],
            ['name' => 'Silver Diamine Fluoride','drug_class' => 'Caries Arrest'],
            ['name' => 'Ferrous Ascorbate',      'drug_class' => 'Haematinic'],
        ];

        DB::table('rx_generics')->insertOrIgnore(array_map(
            fn ($r) => array_merge($r, ['notes' => null, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]),
            $rows
        ));
    }

    private function drugs(): void
    {
        $catId     = fn ($n) => DB::table('rx_drug_categories')->where('name', $n)->value('id');
        $genId     = fn ($n) => DB::table('rx_generics')->where('name', $n)->value('id');
        $routeOral = DB::table('rx_routes_of_admin')->where('abbreviation', 'PO')->value('id');

        $drugs = [
            // ── Paracetamol. The master carried it only inside combinations, so the
            //    one analgesic safe in pregnancy, asthma, gastritis and anticoagulation
            //    could not be prescribed on its own.
            ['brand' => 'Dolo 650', 'gen' => 'Paracetamol', 'cat' => 'Analgesic', 'str' => '650mg', 'form' => 'Tablet',
             'comp' => 'Paracetamol 650mg', 'mol' => 'paracetamol', 'dur' => 3,
             'adult' => '1 tablet three times a day', 'paed' => 'Use the paediatric syrup instead',
             'max' => '4g/day (3g if hepatic impairment or elderly)',
             'contra' => 'Severe hepatic impairment. Never combine with another paracetamol-containing product.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'caution',
             'uses' => 'First-line dental pain; the analgesic of choice in pregnancy, asthma, gastritis and anticoagulated patients',
             'notes' => 'Micro Labs'],

            ['brand' => 'Crocin 650', 'gen' => 'Paracetamol', 'cat' => 'Analgesic', 'str' => '650mg', 'form' => 'Tablet',
             'comp' => 'Paracetamol 650mg', 'mol' => 'paracetamol', 'dur' => 3,
             'adult' => '1 tablet three times a day', 'paed' => 'Use the paediatric syrup instead',
             'max' => '4g/day (3g if hepatic impairment or elderly)',
             'contra' => 'Severe hepatic impairment. Never combine with another paracetamol-containing product.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'caution',
             'uses' => 'First-line dental pain', 'notes' => 'GSK'],

            ['brand' => 'Calpol 250 Syrup', 'gen' => 'Paracetamol', 'cat' => 'Analgesic', 'str' => '250mg/5ml', 'form' => 'Syrup',
             'comp' => 'Paracetamol 250mg/5ml', 'mol' => 'paracetamol', 'dur' => 3,
             'adult' => '10-15ml as required', 'paed' => '15mg/kg per dose, every 6 hours, maximum 4 doses a day',
             'max' => '60mg/kg/day', 'contra' => 'Hepatic impairment. Check other syrups for paracetamol content first.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'normal',
             'uses' => 'Paediatric dental pain and fever', 'notes' => 'GSK'],

            ['brand' => 'Calpol 120 Syrup', 'gen' => 'Paracetamol', 'cat' => 'Analgesic', 'str' => '120mg/5ml', 'form' => 'Syrup',
             'comp' => 'Paracetamol 120mg/5ml', 'mol' => 'paracetamol', 'dur' => 3,
             'adult' => 'Not appropriate — use the 650mg tablet', 'paed' => '15mg/kg per dose, every 6 hours',
             'max' => '60mg/kg/day', 'contra' => 'Hepatic impairment.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'normal',
             'uses' => 'Infants and younger children', 'notes' => 'GSK'],

            // ── The combination molecule itself. duplicate_molecule_group carries BOTH
            //    names, so pairing this with Dolo 650 now raises a paracetamol duplicate.
            ['brand' => 'Combiflam', 'gen' => 'Ibuprofen', 'cat' => 'Analgesic', 'str' => '400mg + 325mg', 'form' => 'Tablet',
             'comp' => 'Ibuprofen 400mg + Paracetamol 325mg', 'mol' => 'ibuprofen,paracetamol', 'dur' => 3,
             'adult' => '1 tablet twice or three times a day, after food', 'paed' => 'Not for under-12s',
             'max' => '3 tablets/day',
             'contra' => 'Peptic ulcer, GI bleed, renal impairment, third-trimester pregnancy, aspirin-sensitive asthma, anticoagulant therapy.',
             'preg' => 'D', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'caution',
             'uses' => 'Moderate dental pain with inflammation',
             'notes' => 'Sanofi. Contains 325mg paracetamol — never add a separate paracetamol.'],

            // ── Clindamycin: the penicillin-allergy fallback, previously a generic row
            //    with no brand behind it.
            ['brand' => 'Dalacin C 300', 'gen' => 'Clindamycin', 'cat' => 'Antibiotic', 'str' => '300mg', 'form' => 'Capsule',
             'comp' => 'Clindamycin 300mg', 'mol' => 'clindamycin', 'abx' => 'lincosamide', 'dur' => 5,
             'adult' => '1 capsule three times a day for 5 days', 'paed' => '8-16mg/kg/day in 3 divided doses',
             'max' => '1.8g/day',
             'contra' => 'Previous antibiotic-associated colitis. Stop at once if diarrhoea develops — C. difficile risk.',
             'preg' => 'B', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'caution',
             'uses' => 'Odontogenic infection in penicillin-allergic patients; endocarditis prophylaxis alternative at 600mg stat',
             'notes' => 'Pfizer'],

            ['brand' => 'Sporidex 500', 'gen' => 'Cephalexin', 'cat' => 'Antibiotic', 'str' => '500mg', 'form' => 'Capsule',
             'comp' => 'Cephalexin 500mg', 'mol' => 'cephalexin', 'abx' => 'cephalosporin', 'dur' => 5,
             'adult' => '1 capsule three times a day for 5 days', 'paed' => '25-50mg/kg/day in 3 divided doses',
             'max' => '4g/day',
             'contra' => 'Cephalosporin allergy. Caution where the penicillin allergy was anaphylactic — cross-reactivity.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'caution',
             'uses' => 'Odontogenic infection where amoxicillin is unsuitable', 'notes' => 'Sun Pharma'],

            ['brand' => 'Ketorol DT 10', 'gen' => 'Ketorolac', 'cat' => 'Analgesic', 'str' => '10mg', 'form' => 'Tablet',
             'comp' => 'Ketorolac Tromethamine 10mg', 'mol' => 'ketorolac', 'dur' => 2,
             'adult' => '1 tablet twice a day after food, maximum 5 days', 'paed' => 'Not for under-16s',
             'max' => '40mg/day',
             'contra' => 'Peptic ulcer, renal impairment, bleeding disorder, anticoagulants, pregnancy, breastfeeding. Never beyond 5 days.',
             'preg' => 'D', 'bf' => 'avoid', 'ped' => 'avoid', 'ger' => 'avoid',
             'uses' => 'Severe acute pulpitis; rescue analgesia where paracetamol and ibuprofen are not enough',
             'notes' => "Dr Reddy's. Short courses only — high GI and renal risk."],

            ['brand' => 'Ultracet', 'gen' => 'Tramadol', 'cat' => 'Analgesic', 'str' => '37.5mg + 325mg', 'form' => 'Tablet',
             'comp' => 'Tramadol 37.5mg + Paracetamol 325mg', 'mol' => 'tramadol,paracetamol', 'dur' => 3,
             'adult' => '1 tablet twice a day after food', 'paed' => 'Not for under-18s', 'max' => '8 tablets/day',
             'contra' => 'Epilepsy, concurrent SSRI/SNRI or MAOI (serotonin syndrome), respiratory depression, benzodiazepine co-use.',
             'preg' => 'C', 'bf' => 'avoid', 'ped' => 'avoid', 'ger' => 'caution', 'ctrl' => 1,
             'uses' => 'Severe post-surgical pain — third molar removal, implant placement',
             'notes' => 'Janssen. Contains paracetamol — never add a separate paracetamol.'],

            // ── Topical and antiseptic
            ['brand' => 'Betadine Gargle 2%', 'gen' => 'Povidone Iodine', 'cat' => 'Antiseptic', 'str' => '2%', 'form' => 'Mouthwash',
             'comp' => 'Povidone Iodine 2% w/v', 'mol' => 'povidone iodine', 'dur' => 5,
             'adult' => 'Dilute 10ml in equal water, gargle 30 seconds, twice or three times a day',
             'paed' => 'Not for under-6s', 'max' => null,
             'contra' => 'Iodine allergy, thyroid disease, pregnancy, breastfeeding. Not for prolonged use.',
             'preg' => 'D', 'bf' => 'avoid', 'ped' => 'caution', 'ger' => 'normal',
             'uses' => 'Pre-procedural rinse to cut aerosol bacterial load; pericoronitis', 'notes' => 'Win-Medicare'],

            ['brand' => 'Tantum Oral Rinse', 'gen' => 'Benzydamine', 'cat' => 'Antiseptic', 'str' => '0.15%', 'form' => 'Mouthwash',
             'comp' => 'Benzydamine Hydrochloride 0.15%', 'mol' => 'benzydamine', 'dur' => 7,
             'adult' => '15ml gargle three times a day, undiluted, do not swallow', 'paed' => 'Not for under-12s',
             'max' => null, 'contra' => 'Hypersensitivity. Avoid swallowing.',
             'preg' => 'B', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'normal',
             'uses' => 'Aphthous ulcers, mucositis, post-radiation soreness, post-extraction discomfort', 'notes' => 'Angelini'],

            ['brand' => 'Daktarin Oral Gel', 'gen' => 'Miconazole', 'cat' => 'Antifungal', 'str' => '2%', 'form' => 'Gel',
             'comp' => 'Miconazole 2%', 'mol' => 'miconazole', 'dur' => 14,
             'adult' => 'Apply four times a day after food; continue 7 days after the lesions clear',
             'paed' => 'Quarter measure four times a day', 'max' => null,
             'contra' => 'Hepatic impairment. Raises INR sharply in patients on warfarin even when applied topically.',
             'preg' => 'C', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'normal',
             'uses' => 'Angular cheilitis, denture stomatitis, oral candidiasis', 'notes' => 'Janssen'],

            ['brand' => 'Candid Mouth Paint', 'gen' => 'Clotrimazole', 'cat' => 'Antifungal', 'str' => '1%', 'form' => 'Gel',
             'comp' => 'Clotrimazole 1% w/v', 'mol' => 'clotrimazole', 'dur' => 14,
             'adult' => 'Apply to the lesions three times a day after food', 'paed' => 'Apply twice a day',
             'max' => null, 'contra' => 'Hypersensitivity to azole antifungals.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'normal',
             'uses' => 'Oral candidiasis, denture stomatitis — the common Indian first choice', 'notes' => 'Glenmark'],

            ['brand' => 'Omnacortil 10', 'gen' => 'Prednisolone', 'cat' => 'Anti-inflammatory', 'str' => '10mg', 'form' => 'Tablet',
             'comp' => 'Prednisolone 10mg', 'mol' => 'prednisolone', 'dur' => 5,
             'adult' => 'As directed, typically 20-40mg daily then tapered', 'paed' => '0.5-2mg/kg/day under specialist guidance',
             'max' => '60mg/day on a short course',
             'contra' => 'Systemic fungal infection, uncontrolled diabetes, active peptic ulcer, uncontrolled hypertension. Never stop abruptly after a long course.',
             'preg' => 'C', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'caution',
             'uses' => 'Third-molar surgical oedema, oral lichen planus, severe aphthous ulceration', 'notes' => 'Macleods'],

            // ── Antiemetic — the category did not exist
            ['brand' => 'Emeset 4', 'gen' => 'Ondansetron', 'cat' => 'Antiemetic', 'str' => '4mg', 'form' => 'Tablet',
             'comp' => 'Ondansetron 4mg', 'mol' => 'ondansetron', 'dur' => 2,
             'adult' => '1 tablet twice a day as required', 'paed' => '0.1mg/kg per dose', 'max' => '16mg/day',
             'contra' => 'Congenital long QT syndrome. Caution alongside other QT-prolonging drugs.',
             'preg' => 'B', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'normal',
             'uses' => 'Post-sedation nausea, strong gag reflex, post-operative vomiting', 'notes' => 'Cipla'],

            ['brand' => 'Vomikind MD 4', 'gen' => 'Ondansetron', 'cat' => 'Antiemetic', 'str' => '4mg', 'form' => 'Tablet',
             'comp' => 'Ondansetron 4mg, mouth dissolving', 'mol' => 'ondansetron', 'dur' => 2,
             'adult' => '1 tablet twice a day as required', 'paed' => '0.1mg/kg per dose', 'max' => '16mg/day',
             'contra' => 'Congenital long QT syndrome.',
             'preg' => 'B', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'normal',
             'uses' => 'Mouth-dissolving — for patients who cannot swallow comfortably', 'notes' => 'Mankind'],

            // ── Anxiolytic — the category existed with nothing in it
            ['brand' => 'Alprax 0.25', 'gen' => 'Alprazolam', 'cat' => 'Anxiolytic', 'str' => '0.25mg', 'form' => 'Tablet',
             'comp' => 'Alprazolam 0.25mg', 'mol' => 'alprazolam', 'dur' => 1,
             'adult' => '1 tablet the night before, or 1 hour before the appointment', 'paed' => 'Not for under-18s',
             'max' => '0.5mg for dental premedication',
             'contra' => 'Respiratory depression, sleep apnoea, myasthenia gravis, pregnancy, opioid co-use, alcohol. The patient must not drive.',
             'preg' => 'D', 'bf' => 'avoid', 'ped' => 'avoid', 'ger' => 'avoid', 'ctrl' => 1,
             'uses' => 'Oral premedication for the anxious patient before implant or surgical appointments',
             'notes' => 'Torrent. Schedule H1 — the patient must be escorted home.'],

            ['brand' => 'Calmpose 5', 'gen' => 'Diazepam', 'cat' => 'Anxiolytic', 'str' => '5mg', 'form' => 'Tablet',
             'comp' => 'Diazepam 5mg', 'mol' => 'diazepam', 'dur' => 1,
             'adult' => '5mg the night before and 5mg an hour before the appointment',
             'paed' => 'Not for routine paediatric dental use', 'max' => '10mg for dental premedication',
             'contra' => 'Respiratory depression, sleep apnoea, myasthenia gravis, pregnancy, opioid co-use, alcohol. The patient must not drive.',
             'preg' => 'D', 'bf' => 'avoid', 'ped' => 'avoid', 'ger' => 'avoid', 'ctrl' => 1,
             'uses' => 'Anxiolysis before long or surgical appointments', 'notes' => 'Ranbaxy. Schedule H1.'],

            // ── Muscle relaxant — the category did not exist. TMD and bruxism.
            ['brand' => 'Zerodol MR', 'gen' => 'Aceclofenac', 'cat' => 'Muscle Relaxant', 'str' => '100mg + 325mg + 250mg', 'form' => 'Tablet',
             'comp' => 'Aceclofenac 100mg + Paracetamol 325mg + Chlorzoxazone 250mg',
             'mol' => 'aceclofenac,paracetamol,chlorzoxazone', 'dur' => 5,
             'adult' => '1 tablet twice a day after food', 'paed' => 'Not for under-18s', 'max' => '2 tablets/day',
             'contra' => 'Peptic ulcer, renal or hepatic impairment, pregnancy, anticoagulant therapy. Causes drowsiness.',
             'preg' => 'D', 'bf' => 'avoid', 'ped' => 'avoid', 'ger' => 'caution',
             'uses' => 'Myofascial pain, TMD, trismus, bruxism-related muscle spasm',
             'notes' => 'Ipca. Contains paracetamol — do not add a separate one.'],

            ['brand' => 'Myoril 4', 'gen' => 'Thiocolchicoside', 'cat' => 'Muscle Relaxant', 'str' => '4mg', 'form' => 'Capsule',
             'comp' => 'Thiocolchicoside 4mg', 'mol' => 'thiocolchicoside', 'dur' => 5,
             'adult' => '1 capsule twice a day', 'paed' => 'Not for under-16s', 'max' => '8mg/day, maximum 7 days',
             'contra' => 'Pregnancy, breastfeeding, women of childbearing potential without contraception. Never beyond 7 days.',
             'preg' => 'D', 'bf' => 'avoid', 'ped' => 'avoid', 'ger' => 'caution',
             'uses' => 'Acute trismus and masticatory muscle spasm', 'notes' => 'Sanofi'],

            // ── Paediatric liquids. Two syrups was thin for a practice with a kids vertical.
            ['brand' => 'Mox 250 Syrup', 'gen' => 'Amoxicillin', 'cat' => 'Antibiotic', 'str' => '250mg/5ml', 'form' => 'Syrup',
             'comp' => 'Amoxicillin 250mg/5ml', 'mol' => 'amoxicillin', 'abx' => 'penicillin', 'dur' => 5,
             'adult' => 'Use capsules', 'paed' => '25-50mg/kg/day in 3 divided doses', 'max' => '100mg/kg/day',
             'contra' => 'Penicillin allergy. Caution in infectious mononucleosis.',
             'preg' => 'B', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'normal',
             'uses' => 'Paediatric odontogenic infection and abscess', 'notes' => 'Sun Pharma'],

            ['brand' => 'Metrogyl 200 Suspension', 'gen' => 'Metronidazole', 'cat' => 'Antibiotic', 'str' => '200mg/5ml', 'form' => 'Suspension',
             'comp' => 'Metronidazole Benzoate 200mg/5ml', 'mol' => 'metronidazole', 'abx' => 'nitroimidazole', 'dur' => 5,
             'adult' => 'Use tablets', 'paed' => '20-30mg/kg/day in 3 divided doses', 'max' => '40mg/kg/day',
             'contra' => 'First-trimester pregnancy. Absolute alcohol avoidance during treatment and for 48 hours after — disulfiram reaction.',
             'preg' => 'B', 'bf' => 'caution', 'ped' => 'safe', 'ger' => 'caution',
             'uses' => 'Anaerobic odontogenic infection, ANUG, pericoronitis in children', 'notes' => 'JB Chemicals'],

            ['brand' => 'Ibugesic 100 Syrup', 'gen' => 'Ibuprofen', 'cat' => 'Analgesic', 'str' => '100mg/5ml', 'form' => 'Syrup',
             'comp' => 'Ibuprofen 100mg/5ml', 'mol' => 'ibuprofen', 'dur' => 3,
             'adult' => 'Use tablets', 'paed' => '5-10mg/kg per dose, every 6-8 hours, after food', 'max' => '40mg/kg/day',
             'contra' => 'Dehydration, renal impairment, asthma, chickenpox, gastritis. Always after food.',
             'preg' => 'D', 'bf' => 'caution', 'ped' => 'caution', 'ger' => 'caution',
             'uses' => 'Paediatric dental pain with inflammation', 'notes' => 'Cipla'],

            // ── Preventive and supportive
            ['brand' => 'Wet Mouth Spray', 'gen' => null, 'cat' => 'Preventive', 'str' => null, 'form' => 'Spray',
             'comp' => 'Saliva substitute — sodium carboxymethylcellulose with xylitol', 'mol' => null, 'dur' => 30,
             'adult' => 'Spray into the mouth as often as needed', 'paed' => 'As needed', 'max' => null,
             'contra' => 'None significant.',
             'preg' => 'N', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'normal',
             'uses' => 'Xerostomia — geriatric patients, post-radiation, polypharmacy, denture wearers', 'notes' => 'ICPA'],

            ['brand' => 'e-SDF Solution', 'gen' => 'Silver Diamine Fluoride', 'cat' => 'Preventive', 'str' => '38%', 'form' => 'Other',
             'comp' => 'Silver Diamine Fluoride 38%', 'mol' => 'silver diamine fluoride', 'dur' => 1,
             'adult' => 'In-office application', 'paed' => 'In-office: isolate and apply to the lesion', 'max' => null,
             'contra' => 'Silver allergy, ulcerated mucosa, pulpal involvement. Stains the arrested lesion black — consent required.',
             'preg' => 'N', 'bf' => 'safe', 'ped' => 'safe', 'ger' => 'normal',
             'uses' => 'Arresting caries in young or uncooperative children without operative treatment', 'notes' => 'Kids-e-Dental'],

            ['brand' => 'Autrin', 'gen' => 'Ferrous Ascorbate', 'cat' => 'Vitamin / Mineral', 'str' => null, 'form' => 'Capsule',
             'comp' => 'Ferrous fumarate with folic acid and vitamin B12', 'mol' => 'iron', 'dur' => 30,
             'adult' => '1 capsule daily after food', 'paed' => 'Under paediatrician guidance', 'max' => '1 capsule/day',
             'contra' => 'Haemochromatosis or iron overload. Separate from doxycycline and ciprofloxacin by 2 hours — chelation.',
             'preg' => 'A', 'bf' => 'safe', 'ped' => 'caution', 'ger' => 'normal',
             'uses' => 'Recurrent aphthous ulceration, atrophic glossitis and angular cheilitis with anaemia', 'notes' => 'Pfizer'],
        ];

        $rows = [];
        foreach ($drugs as $d) {
            $rows[] = [
                'drug_code'                   => null,
                'brand_name'                  => $d['brand'],
                'generic_id'                  => $d['gen'] ? $genId($d['gen']) : null,
                'category_id'                 => $catId($d['cat']),
                'strength'                    => $d['str'],
                'dosage_form'                 => $d['form'],
                'composition'                 => $d['comp'],
                'route_id'                    => $routeOral,
                'default_dose'                => $d['adult'],
                'adult_dose'                  => $d['adult'],
                'pediatric_dose'              => $d['paed'],
                'default_duration'            => $d['dur'],
                'default_duration_unit'       => 'days',
                'default_food_instruction_id' => null,
                'default_instructions'        => null,
                'max_daily_dose'              => $d['max'],
                'duplicate_molecule_group'    => $d['mol'],
                'antibiotic_class'            => $d['abx']  ?? null,
                'is_controlled'               => $d['ctrl'] ?? 0,
                'pregnancy_category'          => $d['preg'],
                'breastfeeding_safety'        => $d['bf'],
                'pediatric_safety'            => $d['ped'],
                'geriatric_caution'           => $d['ger'],
                'renal_dose_adjustment'       => null,
                'hepatic_dose_adjustment'     => null,
                'contraindications'           => $d['contra'],
                'drug_interactions_note'      => null,
                'common_dental_uses'          => $d['uses'],
                'notes'                       => $d['notes'],
                'is_active'                   => 1,
                'created_at'                  => now(),
                'updated_at'                  => now(),
                'deleted_at'                  => null,
            ];
        }

        DB::table('rx_drugs')->insertOrIgnore($rows);
    }

    /**
     * Backfill safety flags onto brands already in the master.
     *
     * The 122 rows from RxDentalBrandsSeeder have NULL in pregnancy_category,
     * breastfeeding_safety, pediatric_safety and geriatric_caution. The point-of-
     * selection grading reads exactly those columns, so without this pass the
     * typeahead would grey almost nothing and look broken.
     *
     * Matched by molecule so it catches every brand of the same drug, and only
     * NULL columns are written — anything edited by hand in Settings survives.
     */
    private function enrichExisting(): void
    {
        // molecule => [pregnancy, breastfeeding, paediatric, geriatric, contraindications]
        $byMolecule = [
            'ibuprofen'      => ['D', 'caution', 'caution', 'caution', 'Peptic ulcer, GI bleed, renal impairment, third-trimester pregnancy, aspirin-sensitive asthma, anticoagulant therapy.'],
            'diclofenac'     => ['D', 'caution', 'avoid',   'caution', 'Peptic ulcer, ischaemic heart disease, renal impairment, pregnancy. Highest cardiovascular risk of the common NSAIDs.'],
            'aceclofenac'    => ['D', 'caution', 'avoid',   'caution', 'Peptic ulcer, renal or hepatic impairment, pregnancy, anticoagulant therapy.'],
            'nimesulide'     => ['D', 'avoid',   'avoid',   'caution', 'Hepatotoxicity risk. Banned for under-12s in India. Never exceed 10 days.'],
            'etoricoxib'     => ['D', 'avoid',   'avoid',   'caution', 'Uncontrolled hypertension, ischaemic heart disease, stroke, severe hepatic impairment.'],
            'ketorolac'      => ['D', 'avoid',   'avoid',   'avoid',   'Peptic ulcer, renal impairment, bleeding disorder, anticoagulants, pregnancy. Never beyond 5 days.'],
            'paracetamol'    => ['B', 'safe',    'safe',    'caution', 'Severe hepatic impairment. Never combine two paracetamol-containing products.'],
            'tramadol'       => ['C', 'avoid',   'avoid',   'caution', 'Epilepsy, concurrent SSRI/SNRI or MAOI, respiratory depression, benzodiazepine co-use.'],
            'amoxicillin'    => ['B', 'safe',    'safe',    'normal',  'Penicillin allergy. Caution in infectious mononucleosis.'],
            'clavulanate'    => ['B', 'safe',    'safe',    'caution', 'Penicillin allergy. History of clavulanate-associated jaundice.'],
            'metronidazole'  => ['B', 'caution', 'safe',    'caution', 'First-trimester pregnancy. Absolute alcohol avoidance during and for 48 hours after — disulfiram reaction.'],
            'tinidazole'     => ['C', 'avoid',   'caution', 'caution', 'First-trimester pregnancy. Absolute alcohol avoidance.'],
            'azithromycin'   => ['B', 'safe',    'safe',    'caution', 'QT prolongation, hepatic impairment, myasthenia gravis.'],
            'clarithromycin' => ['C', 'caution', 'caution', 'caution', 'QT prolongation. Serious interaction with statins.'],
            'doxycycline'    => ['D', 'avoid',   'avoid',   'normal',  'Pregnancy and children under 8 — permanent tooth discolouration. Photosensitivity. Take upright with water.'],
            'ciprofloxacin'  => ['C', 'caution', 'avoid',   'caution', 'Tendon rupture risk, QT prolongation, epilepsy, myasthenia gravis. Avoid in growing children.'],
            'levofloxacin'   => ['C', 'caution', 'avoid',   'caution', 'Tendon rupture risk, QT prolongation, epilepsy, myasthenia gravis.'],
            'cefixime'       => ['B', 'safe',    'safe',    'normal',  'Cephalosporin allergy; caution where penicillin allergy was anaphylactic.'],
            'cefpodoxime'    => ['B', 'safe',    'safe',    'normal',  'Cephalosporin allergy; caution where penicillin allergy was anaphylactic.'],
            'cephalexin'     => ['B', 'safe',    'safe',    'caution', 'Cephalosporin allergy; caution where penicillin allergy was anaphylactic.'],
            'fluconazole'    => ['C', 'caution', 'caution', 'caution', 'Pregnancy at high dose. QT prolongation. Raises INR on warfarin.'],
            'acyclovir'      => ['B', 'safe',    'safe',    'caution', 'Renal impairment — dose adjustment needed. Maintain hydration.'],
            'chlorhexidine'  => ['B', 'safe',    'caution', 'normal',  'Hypersensitivity — rare but genuine anaphylaxis. Stains teeth on prolonged use. Not within 30 minutes of toothpaste.'],
            'lignocaine'     => ['B', 'safe',    'safe',    'caution', 'Amide local anaesthetic allergy. Caution in severe hepatic impairment and heart block.'],
            'prednisolone'   => ['C', 'caution', 'caution', 'caution', 'Systemic fungal infection, uncontrolled diabetes, active peptic ulcer. Never stop abruptly after a long course.'],
            'dexamethasone'  => ['C', 'caution', 'caution', 'caution', 'Systemic fungal infection, uncontrolled diabetes, active peptic ulcer.'],
            'betamethasone'  => ['C', 'caution', 'caution', 'caution', 'Systemic fungal infection, uncontrolled diabetes, active peptic ulcer.'],
            'tranexamic acid'=> ['B', 'caution', 'caution', 'caution', 'Active thromboembolic disease, history of DVT or stroke, colour vision defects.'],
            'serratiopeptidase' => ['C', 'caution', 'caution', 'caution', 'Bleeding disorders, anticoagulant therapy. Evidence of benefit is weak.'],
            'omeprazole'     => ['C', 'caution', 'caution', 'normal',  'Long-term use lowers magnesium and B12; raises fracture risk.'],
            'pantoprazole'   => ['B', 'caution', 'caution', 'normal',  'Long-term use lowers magnesium and B12.'],
            'cetirizine'     => ['B', 'caution', 'safe',    'caution', 'Causes drowsiness — the patient must not drive.'],
        ];

        foreach ($byMolecule as $molecule => [$preg, $bf, $ped, $ger, $contra]) {
            $ids = DB::table('rx_drugs')
                ->where(function ($q) use ($molecule) {
                    $q->where('duplicate_molecule_group', 'like', "%{$molecule}%")
                      ->orWhere('composition', 'like', "%{$molecule}%");
                })
                ->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            // Only fill what is still NULL — never overwrite a clinician's edit.
            foreach ([
                'pregnancy_category'   => $preg,
                'breastfeeding_safety' => $bf,
                'pediatric_safety'     => $ped,
                'geriatric_caution'    => $ger,
                'contraindications'    => $contra,
            ] as $column => $value) {
                DB::table('rx_drugs')
                    ->whereIn('id', $ids)
                    ->whereNull($column)
                    ->update([$column => $value, 'updated_at' => now()]);
            }
        }
    }

    /**
     * Drug-drug interaction rules. This table was never seeded, so the interaction
     * check has always run against nothing.
     *
     * Rules are matched in both directions. Side A is generally what the patient
     * already takes (read from current_medications) and side B what is being
     * prescribed — but PrescriptionRiskService tries the pair both ways round, so
     * either column may hold either.
     *
     * Molecule names are spelled the way patients and receptionists actually write
     * them. Acenocoumarol appears as "acitrom" because in India that is what ends
     * up in the field.
     */
    private function interactionRules(): void
    {
        $rules = [
            // ── Anticoagulants. The bleeding interactions that actually reach hospital.
            ['a_mol' => 'warfarin', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'critical',
             'msg' => 'Patient is on warfarin. NSAIDs sharply raise bleeding risk and displace warfarin from protein binding. Use paracetamol instead.'],
            ['a_mol' => 'acitrom', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'critical',
             'msg' => 'Patient is on acenocoumarol (Acitrom). NSAIDs sharply raise bleeding risk. Use paracetamol instead.'],
            ['a_mol' => 'warfarin', 'b_mol' => 'metronidazole', 'sev' => 'critical',
             'msg' => 'Metronidazole potentiates warfarin and can push the INR into a bleeding range within days. Avoid, or arrange INR monitoring.'],
            ['a_mol' => 'warfarin', 'b_mol' => 'fluconazole', 'sev' => 'critical',
             'msg' => 'Fluconazole markedly raises the INR on warfarin. Avoid, or monitor INR closely.'],
            ['a_mol' => 'warfarin', 'b_mol' => 'miconazole', 'sev' => 'critical',
             'msg' => 'Even topical miconazole oral gel raises the INR on warfarin. Use a non-azole antifungal.'],
            ['a_mol' => 'warfarin', 'b_mol' => 'azithromycin', 'sev' => 'warning',
             'msg' => 'Macrolides can raise the INR on warfarin. Monitor if a course is unavoidable.'],
            ['a_mol' => 'clopidogrel', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'warning',
             'msg' => 'Patient is on clopidogrel. NSAIDs add GI bleeding risk on top of the antiplatelet effect.'],
            ['a_mol' => 'ecosprin', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'warning',
             'msg' => 'Patient is on aspirin. Adding an NSAID compounds GI bleeding risk and ibuprofen blunts aspirin cardioprotection.'],

            // ── Narrow therapeutic index
            ['a_mol' => 'methotrexate', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'critical',
             'msg' => 'NSAIDs reduce methotrexate clearance and can cause serious toxicity. Use paracetamol.'],
            ['a_mol' => 'lithium', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'critical',
             'msg' => 'NSAIDs raise serum lithium and can cause lithium toxicity. Use paracetamol.'],
            ['a_mol' => 'digoxin', 'b_mol' => 'clarithromycin', 'sev' => 'critical',
             'msg' => 'Clarithromycin raises digoxin levels. Avoid or monitor.'],

            // ── Serotonergic and CNS
            ['a_mol' => 'sertraline', 'b_mol' => 'tramadol', 'sev' => 'critical',
             'msg' => 'Tramadol with an SSRI risks serotonin syndrome and lowers the seizure threshold. Choose a different analgesic.'],
            ['a_mol' => 'fluoxetine', 'b_mol' => 'tramadol', 'sev' => 'critical',
             'msg' => 'Tramadol with an SSRI risks serotonin syndrome. Choose a different analgesic.'],
            ['a_mol' => 'escitalopram', 'b_mol' => 'tramadol', 'sev' => 'critical',
             'msg' => 'Tramadol with an SSRI risks serotonin syndrome. Choose a different analgesic.'],
            ['a_class' => 'SSRI', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'warning',
             'msg' => 'SSRIs with NSAIDs raise upper GI bleeding risk. Consider paracetamol or add gastric cover.'],
            ['a_class' => 'opioid', 'b_class' => 'Benzodiazepine', 'sev' => 'critical',
             'msg' => 'Benzodiazepine with an opioid risks respiratory depression. Do not co-prescribe for dental premedication.'],
            ['a_mol' => 'alcohol', 'b_mol' => 'metronidazole', 'sev' => 'critical',
             'msg' => 'Metronidazole with alcohol causes a disulfiram reaction — flushing, vomiting, collapse. Abstain during and for 48 hours after.'],
        ];

        $more = [
            // ── Cardiovascular and renal
            ['a_mol' => 'amlodipine', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'warning',
             'msg' => 'NSAIDs blunt antihypertensive control and can raise blood pressure. Monitor if a course is needed.'],
            ['a_mol' => 'telmisartan', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'warning',
             'msg' => 'ARB with an NSAID risks acute kidney injury, especially alongside a diuretic. Prefer paracetamol.'],
            ['a_mol' => 'ramipril', 'b_mol' => null, 'b_class' => 'NSAID', 'sev' => 'warning',
             'msg' => 'ACE inhibitor with an NSAID risks acute kidney injury. Prefer paracetamol.'],
            ['a_mol' => 'atorvastatin', 'b_mol' => 'clarithromycin', 'sev' => 'critical',
             'msg' => 'Clarithromycin with a statin risks rhabdomyolysis. Use azithromycin instead.'],
            ['a_mol' => 'atorvastatin', 'b_mol' => 'fluconazole', 'sev' => 'warning',
             'msg' => 'Azole antifungals raise statin levels and myopathy risk.'],
            ['a_mol' => 'propranolol', 'b_mol' => 'adrenaline', 'sev' => 'warning',
             'msg' => 'Non-selective beta blocker with adrenaline in local anaesthetic can cause a hypertensive response with reflex bradycardia. Limit the cartridge count and aspirate.'],
            ['a_mol' => 'amitriptyline', 'b_mol' => 'adrenaline', 'sev' => 'warning',
             'msg' => 'Tricyclic antidepressants potentiate the pressor effect of adrenaline. Limit the cartridge count and aspirate carefully.'],

            // ── Absorption and chelation
            ['a_mol' => 'iron', 'b_mol' => 'doxycycline', 'sev' => 'warning',
             'msg' => 'Iron chelates doxycycline and blocks absorption. Separate the doses by at least 2 hours.'],
            ['a_mol' => 'calcium', 'b_mol' => 'doxycycline', 'sev' => 'warning',
             'msg' => 'Calcium chelates doxycycline and blocks absorption. Separate the doses by at least 2 hours.'],
            ['a_mol' => 'calcium', 'b_mol' => 'ciprofloxacin', 'sev' => 'warning',
             'msg' => 'Calcium chelates fluoroquinolones and blocks absorption. Separate the doses by at least 2 hours.'],
            ['a_mol' => 'iron', 'b_mol' => 'levofloxacin', 'sev' => 'warning',
             'msg' => 'Iron chelates fluoroquinolones and blocks absorption. Separate the doses by at least 2 hours.'],

            // ── Endocrine
            ['a_mol' => 'metformin', 'b_class' => 'Corticosteroid', 'sev' => 'warning',
             'msg' => 'Corticosteroids raise blood glucose and can destabilise diabetic control. Warn the patient to monitor.'],
            ['a_mol' => 'insulin', 'b_class' => 'Corticosteroid', 'sev' => 'warning',
             'msg' => 'Corticosteroids raise blood glucose. Insulin requirements may rise during the course.'],
            ['a_mol' => 'alendronate', 'b_class' => null, 'b_mol' => 'dental extraction', 'sev' => 'info',
             'msg' => 'Patient is on a bisphosphonate. Assess MRONJ risk before any extraction or implant placement.'],
        ];

        $rows = array_map(function ($r) {
            return [
                'drug_a_molecule' => $r['a_mol']   ?? null,
                'drug_a_class'    => $r['a_class'] ?? null,
                'drug_b_molecule' => $r['b_mol']   ?? null,
                'drug_b_class'    => $r['b_class'] ?? null,
                'severity'        => $r['sev'],
                'alert_message'   => $r['msg'],
                'is_active'       => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }, array_merge($rules, $more));

        DB::table('rx_drug_interaction_rules')->insertOrIgnore($rows);
    }

    /** Condition-driven contraindications, on top of the nine in RxMasterSeeder. */
    private function warningRules(): void
    {
        $rules = [
            ['cond' => 'bleeding disorder', 'class' => 'NSAID', 'sev' => 'critical',
             'msg' => 'Patient has a bleeding disorder. NSAIDs impair platelet function and raise post-extraction bleeding.', 'sug' => 'Paracetamol', 'block' => 1],
            ['cond' => 'anticoagulant', 'class' => 'NSAID', 'sev' => 'critical',
             'msg' => 'Patient is anticoagulated. NSAIDs add a serious bleeding risk.', 'sug' => 'Paracetamol', 'block' => 1],
            ['cond' => 'epilepsy', 'mol' => 'tramadol', 'sev' => 'critical',
             'msg' => 'Tramadol lowers the seizure threshold and is contraindicated in epilepsy.', 'sug' => 'Paracetamol or an NSAID', 'block' => 1],
            ['cond' => 'pregnancy', 'mol' => 'doxycycline', 'sev' => 'critical',
             'msg' => 'Doxycycline in pregnancy causes permanent discolouration of the developing teeth.', 'sug' => 'Amoxicillin', 'block' => 1],
            ['cond' => 'pregnancy', 'mol' => 'ketorolac', 'sev' => 'critical',
             'msg' => 'Ketorolac is contraindicated in pregnancy.', 'sug' => 'Paracetamol', 'block' => 1],
            ['cond' => 'pregnancy', 'class' => 'Benzodiazepine', 'sev' => 'critical',
             'msg' => 'Benzodiazepines are contraindicated in pregnancy.', 'sug' => 'Non-pharmacological anxiety management', 'block' => 1],
            ['cond' => 'pregnancy', 'mol' => 'povidone iodine', 'sev' => 'warning',
             'msg' => 'Povidone iodine is absorbed and can affect the foetal thyroid. Use chlorhexidine instead.', 'sug' => 'Chlorhexidine', 'block' => 0],
            ['cond' => 'asthma', 'mol' => 'ketorolac', 'sev' => 'critical',
             'msg' => 'Ketorolac can precipitate severe bronchospasm in aspirin-sensitive asthma.', 'sug' => 'Paracetamol', 'block' => 1],
            ['cond' => 'liver', 'mol' => 'nimesulide', 'sev' => 'critical',
             'msg' => 'Nimesulide is hepatotoxic and contraindicated in liver disease.', 'sug' => 'Paracetamol at reduced dose', 'block' => 1],
            ['cond' => 'liver', 'mol' => 'metronidazole', 'sev' => 'warning',
             'msg' => 'Metronidazole clearance is reduced in hepatic impairment. Reduce the dose.', 'sug' => null, 'block' => 0],
            ['cond' => 'renal', 'mol' => 'ketorolac', 'sev' => 'critical',
             'msg' => 'Ketorolac is contraindicated in renal impairment — acute kidney injury risk.', 'sug' => 'Paracetamol', 'block' => 1],
            ['cond' => 'alcohol', 'mol' => 'metronidazole', 'sev' => 'critical',
             'msg' => 'Metronidazole with alcohol causes a disulfiram reaction. Confirm the patient will abstain during and for 48 hours after.', 'sug' => 'Amoxicillin', 'block' => 0],
            ['cond' => 'alcohol', 'mol' => 'paracetamol', 'sev' => 'warning',
             'msg' => 'Chronic alcohol use raises paracetamol hepatotoxicity risk. Cap the daily dose at 2g.', 'sug' => null, 'block' => 0],
            ['cond' => 'myasthenia', 'mol' => 'clindamycin', 'sev' => 'critical',
             'msg' => 'Clindamycin has neuromuscular blocking activity and can worsen myasthenia gravis.', 'sug' => 'Azithromycin', 'block' => 1],
            ['cond' => 'thyroid', 'mol' => 'povidone iodine', 'sev' => 'warning',
             'msg' => 'Iodine absorption can disturb thyroid function. Use chlorhexidine instead.', 'sug' => 'Chlorhexidine', 'block' => 0],
            ['cond' => 'glaucoma', 'class' => 'Benzodiazepine', 'sev' => 'warning',
             'msg' => 'Benzodiazepines can raise intraocular pressure in narrow-angle glaucoma.', 'sug' => null, 'block' => 0],
            ['cond' => 'sleep apnoea', 'class' => 'Benzodiazepine', 'sev' => 'critical',
             'msg' => 'Benzodiazepines depress respiration and are contraindicated in obstructive sleep apnoea.', 'sug' => 'Non-pharmacological anxiety management', 'block' => 1],
            ['cond' => 'bisphosphonate', 'mol' => null, 'class' => null, 'sev' => 'info',
             'msg' => 'Patient is on a bisphosphonate. Assess MRONJ risk before extraction or implant placement.', 'sug' => null, 'block' => 0],
            ['cond' => 'heart', 'mol' => 'etoricoxib', 'sev' => 'critical',
             'msg' => 'Etoricoxib is contraindicated in ischaemic heart disease and uncontrolled hypertension.', 'sug' => 'Paracetamol', 'block' => 1],
        ];

        DB::table('rx_warning_rules')->insertOrIgnore(array_map(fn ($r) => [
            'condition_keyword' => $r['cond'],
            'drug_id'           => null,
            'molecule_group'    => $r['mol']   ?? null,
            'drug_class'        => $r['class'] ?? null,
            'severity'          => $r['sev'],
            'alert_message'     => $r['msg'],
            'suggestion'        => $r['sug'],
            'blockable'         => $r['block'],
            'is_active'         => 1,
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $rules));
    }

    /**
     * Allergy rules on top of the six in RxMasterSeeder. The two that matter most
     * in a dental chair are the two that were missing: lignocaine, because an
     * amide allergy changes the whole appointment, and iodine, because Betadine
     * is handed out as a rinse without a second thought.
     */
    private function allergyRules(): void
    {
        $rules = [
            ['key' => 'lignocaine',    'mol' => 'lignocaine',      'class' => 'local anaesthetic', 'sev' => 'critical',
             'msg' => 'Recorded lignocaine allergy. Amide local anaesthetics are contraindicated — confirm the reaction history before any injection.'],
            ['key' => 'lidocaine',     'mol' => 'lignocaine',      'class' => 'local anaesthetic', 'sev' => 'critical',
             'msg' => 'Recorded lidocaine allergy. Amide local anaesthetics are contraindicated — confirm the reaction history before any injection.'],
            ['key' => 'local anaesthetic', 'mol' => 'lignocaine',  'class' => 'local anaesthetic', 'sev' => 'critical',
             'msg' => 'Recorded local anaesthetic allergy. Verify which agent and reaction before injecting anything.'],
            ['key' => 'iodine',        'mol' => 'povidone iodine', 'class' => null,               'sev' => 'critical',
             'msg' => 'Recorded iodine allergy. Povidone iodine rinse and iodine-containing antiseptics are contraindicated.'],
            ['key' => 'cephalosporin', 'mol' => null,              'class' => 'cephalosporin',    'sev' => 'critical',
             'msg' => 'Recorded cephalosporin allergy. This drug is a cephalosporin — risk of severe allergic reaction.'],
            ['key' => 'erythromycin',  'mol' => null,              'class' => 'macrolide',        'sev' => 'critical',
             'msg' => 'Recorded macrolide allergy. Azithromycin and clarithromycin may cross-react.'],
            ['key' => 'macrolide',     'mol' => null,              'class' => 'macrolide',        'sev' => 'critical',
             'msg' => 'Recorded macrolide allergy. This drug is a macrolide.'],
            ['key' => 'clindamycin',   'mol' => 'clindamycin',     'class' => 'lincosamide',      'sev' => 'critical',
             'msg' => 'Recorded clindamycin allergy. This drug is contraindicated.'],
            ['key' => 'tetracycline',  'mol' => 'doxycycline',     'class' => 'tetracycline',     'sev' => 'critical',
             'msg' => 'Recorded tetracycline allergy. Doxycycline is in the same class.'],
            ['key' => 'ciprofloxacin', 'mol' => null,              'class' => 'fluoroquinolone',  'sev' => 'critical',
             'msg' => 'Recorded fluoroquinolone allergy. This drug is in the same class.'],
            ['key' => 'chlorhexidine', 'mol' => 'chlorhexidine',   'class' => null,               'sev' => 'critical',
             'msg' => 'Recorded chlorhexidine allergy. Anaphylaxis to chlorhexidine mouthwash is rare but genuine.'],
            ['key' => 'paracetamol',   'mol' => 'paracetamol',     'class' => null,               'sev' => 'critical',
             'msg' => 'Recorded paracetamol allergy. Check combination products — many contain paracetamol without saying so on the front.'],
            ['key' => 'ibuprofen',     'mol' => 'ibuprofen',       'class' => 'NSAID',            'sev' => 'critical',
             'msg' => 'Recorded ibuprofen allergy. Other NSAIDs may cross-react.'],
            ['key' => 'latex',         'mol' => null,              'class' => null,               'sev' => 'critical',
             'msg' => 'Recorded latex allergy. Use nitrile gloves and a latex-free dam — this affects the appointment, not only the prescription.'],
        ];

        DB::table('rx_allergy_rules')->insertOrIgnore(array_map(fn ($r) => [
            'allergy_keyword' => $r['key'],
            'blocks_molecule' => $r['mol'],
            'blocks_class'    => $r['class'],
            'severity'        => $r['sev'],
            'alert_message'   => $r['msg'],
            'is_active'       => 1,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $rules));
    }
}
