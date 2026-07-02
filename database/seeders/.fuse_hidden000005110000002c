<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentSop;
use App\Models\TreatmentRule;

/**
 * DentalTreatmentsMasterSeeder
 *
 * Seeds 14 core dental treatments across 8 categories.
 * Pricing: Tier 1 Indian cities (Mumbai, Delhi, Bengaluru, Pune, Chennai, Hyderabad).
 *
 * Run: php artisan db:seed --class=DentalTreatmentsMasterSeeder
 * Safe to re-run: uses updateOrCreate on treatment code.
 * All data is editable from the Treatments module in the admin panel.
 */
class DentalTreatmentsMasterSeeder extends Seeder
{
    public function run(): void
    {
        // ── Category colours ──────────────────────────────────────────────────
        $categories = [
            'Endodontics'       => '#dc2626',
            'Restorative'       => '#16a34a',
            'Crown & Bridge'    => '#d97706',
            'Oral Surgery'      => '#7c3aed',
            'Implantology'      => '#0891b2',
            'Periodontics'      => '#2563eb',
            'Orthodontics'      => '#be185d',
            'Prosthodontics'    => '#6b7280',
            'Cosmetic Dentistry'=> '#ca8a04',
        ];

        $catModels = [];
        $order = 1;
        foreach ($categories as $name => $color) {
            $catModels[$name] = TreatmentCategory::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
            $order++;
        }

        // ── Treatment definitions ─────────────────────────────────────────────
        // Format per entry:
        //   category, name, code, description, duration (min), default/min/max price,
        //   color, specialty_tag, stages, trigger_keywords, suggested_questions,
        //   suggested_investigations, possible_diagnoses, patient_instructions,
        //   sop [doctor_steps, assistant_steps, pre, post, consent],
        //   rules [type => note]
        // ─────────────────────────────────────────────────────────────────────
        $treatments = [

            // ── 1. ROOT CANAL TREATMENT ───────────────────────────────────────
            [
                'category'    => 'Endodontics',
                'name'        => 'Root Canal Treatment',
                'code'        => 'RCT-01',
                'description' => 'Removal of infected or inflamed pulp, biomechanical preparation, and hermetic obturation to preserve the natural tooth.',
                'duration'    => 60,
                'price'       => ['default' => 8000, 'min' => 5000, 'max' => 15000],
                'color'       => '#dc2626',
                'specialty'   => 'endodontics',
                'sort_order'  => 1,
                'stages'      => [
                    ['key' => 'diagnosis',     'label' => 'Diagnosis & X-ray'],
                    ['key' => 'access',        'label' => 'Access Opening'],
                    ['key' => 'instrumentation','label' => 'Canal Preparation'],
                    ['key' => 'obturation',    'label' => 'Obturation'],
                    ['key' => 'review',        'label' => 'Post-op Review'],
                    ['key' => 'crown',         'label' => 'Crown Placement'],
                ],
                'keywords'    => ['toothache', 'pain', 'rct', 'root canal', 'nerve treatment', 'cold sensitivity', 'hot sensitivity', 'swelling', 'abscess', 'pus', 'throbbing', 'night pain', 'cavity', 'infection', 'tooth pain'],
                'questions'   => ['How long have you had the pain?', 'Is pain spontaneous or triggered?', 'Does cold/hot make it worse — for how long?', 'Any swelling or pus near the tooth?', 'Is the pain waking you at night?', 'Have you taken antibiotics for this?', 'Has this tooth been treated before?'],
                'investigations' => ['IOPA / RVG', 'Cold test', 'Heat test', 'Percussion test', 'Palpation test', 'Mobility test', 'CBCT (if complex anatomy)'],
                'diagnoses'   => ['Symptomatic irreversible pulpitis', 'Necrotic pulp with periapical abscess', 'Acute apical abscess', 'Chronic apical periodontitis', 'Previously treated — failing RCT'],
                'patient_instructions' => "Avoid chewing on the treated tooth until the crown is placed.\nTake prescribed painkillers as needed for 1–2 days.\nRinse with warm salt water twice daily for 3 days.\nContact us immediately if swelling increases or fever develops.",
                'sop' => [
                    'doctor_steps'    => ['Review medical history & X-ray', 'Confirm diagnosis with pulp vitality tests', 'Obtain written consent', 'Administer local anaesthesia', 'Place rubber dam', 'Create access cavity, identify all canal orifices', 'Determine working length (apex locator + X-ray)', 'Biomechanical preparation with rotary files + NaOCl irrigation', 'Final irrigation: NaOCl → EDTA → NaOCl → saline', 'Obturate with gutta-percha + sealer OR dress with calcium hydroxide', 'Take post-op X-ray', 'Place coronal seal / temp restoration', 'Advise patient — crown is mandatory'],
                    'assistant_steps' => ['Set up endo tray: files, rubber dam, irrigation syringes, apex locator', 'Load LA syringe before doctor enters', 'Label NaOCl and EDTA syringes clearly', 'Assist with suction and instrument passing', 'Take intraoral X-rays on instruction', 'Document file sizes and materials used'],
                    'pre'  => "Eat a light meal before the appointment.\nTake any prescribed antibiotics as directed.\nInform us of all medications, allergies, and medical conditions.",
                    'post' => "Mild soreness for 24–72 hours is normal — take Ibuprofen 400mg or Paracetamol 500mg as advised.\nDo not chew hard food on the treated side.\nAvoid sticky or hard food if a temporary restoration is present.\nA crown is ESSENTIAL after RCT — book your crown appointment.",
                    'consent' => "The tooth requires RCT because the nerve is infected or dead. The procedure involves cleaning the canals and sealing them. Risks include post-operative pain, rare instrument separation, and the need for a crown. The alternative is extraction. Without a crown, the tooth may fracture.",
                ],
                'rules' => [
                    'xray_required'       => 'Pre-op and post-obturation IOPA/RVG mandatory',
                    'consent_required'    => 'Written consent before procedure',
                    'anesthesia_required' => 'Local anaesthesia — Lignocaine 2% 1:80,000',
                    'min_visits'          => ['count' => 2],
                    'max_visits'          => ['count' => 4],
                    'follow_up_days'      => ['days' => 14],
                    'medical_clearance'   => 'Required for uncontrolled diabetes, bisphosphonate use, anticoagulants',
                ],
            ],

            // ── 2. COMPOSITE FILLING ──────────────────────────────────────────
            [
                'category'    => 'Restorative',
                'name'        => 'Composite Filling',
                'code'        => 'REST-01',
                'description' => 'Tooth-coloured direct resin restoration for caries removal and aesthetic reconstruction of tooth structure.',
                'duration'    => 30,
                'price'       => ['default' => 2500, 'min' => 1500, 'max' => 4500],
                'color'       => '#16a34a',
                'specialty'   => 'restorative',
                'sort_order'  => 2,
                'stages'      => [
                    ['key' => 'diagnosis',   'label' => 'Diagnosis'],
                    ['key' => 'preparation', 'label' => 'Caries Removal'],
                    ['key' => 'restoration', 'label' => 'Composite Placement'],
                    ['key' => 'finishing',   'label' => 'Finishing & Polish'],
                ],
                'keywords'    => ['cavity', 'hole', 'decay', 'filling', 'broken tooth', 'chipped', 'sensitivity', 'sweet pain', 'cold pain', 'discoloured tooth', 'white filling', 'tooth coloured'],
                'questions'   => ['How long has the cavity been there?', 'Any sensitivity to cold, sweet, or hot?', 'Is the pain spontaneous or only triggered?', 'Has a filling fallen out?', 'Any previous treatment on this tooth?'],
                'investigations' => ['IOPA (to assess caries depth)', 'Bite-wing X-ray (proximal caries)', 'Cold test (if pulp involvement suspected)', 'Transillumination'],
                'diagnoses'   => ['Enamel caries', 'Dentinal caries', 'Fractured restoration', 'Chipped tooth', 'Erosion / abrasion defect'],
                'patient_instructions' => "Avoid eating for 1 hour after the procedure.\nAvoid very hot or cold food for 24 hours.\nSome sensitivity for a few days is normal — contact us if it persists beyond a week.",
                'sop' => [
                    'doctor_steps'    => ['Review X-ray, assess caries depth', 'Administer LA if needed', 'Remove caries with slow-speed handpiece', 'Apply liner / base if near pulp', 'Etch, bond, and place composite incrementally', 'Light cure each layer (20 seconds)', 'Check occlusion with articulating paper', 'Finish and polish with composite discs / burs'],
                    'assistant_steps' => ['Set up restoration tray: burs, matrix, wedges, composite kit, light cure unit', 'Mix bonding agent on instruction', 'Load composite shade as directed', 'Ensure light cure unit battery is charged'],
                    'pre'  => "No special preparation needed. Inform the doctor if you are allergic to dental materials.",
                    'post' => "Avoid hard or sticky food for 24 hours.\nSome mild sensitivity for a few days is normal.\nContact us if pain increases or the filling feels high on biting.",
                    'consent' => "Caries will be removed and the space filled with a tooth-coloured composite material. The filling may need replacement over time. Deep caries may require RCT if the nerve is affected.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA to confirm caries depth before treatment',
                    'consent_required' => 'Verbal consent acceptable; written if near pulp',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 2],
                ],
            ],

            // ── 3. GIC FILLING ────────────────────────────────────────────────
            [
                'category'    => 'Restorative',
                'name'        => 'GIC Filling',
                'code'        => 'REST-02',
                'description' => 'Glass ionomer cement restoration — releases fluoride, bonds chemically to tooth. Used for cervical lesions, milk teeth, and as a liner.',
                'duration'    => 20,
                'price'       => ['default' => 1200, 'min' => 800, 'max' => 2500],
                'color'       => '#15803d',
                'specialty'   => 'restorative',
                'sort_order'  => 3,
                'stages'      => [
                    ['key' => 'preparation',  'label' => 'Caries Removal'],
                    ['key' => 'restoration',  'label' => 'GIC Placement'],
                    ['key' => 'finishing',    'label' => 'Finishing'],
                ],
                'keywords'    => ['cavity', 'gic', 'glass ionomer', 'milk tooth filling', 'cervical cavity', 'neck of tooth', 'fluoride filling', 'temporary filling'],
                'questions'   => ['Is this a milk tooth or permanent?', 'Any sensitivity to cold or sweet?', 'Is this a cervical (gum line) cavity?'],
                'investigations' => ['IOPA (if depth unclear)', 'Cold test'],
                'diagnoses'   => ['Cervical caries (abrasion / erosion)', 'Class V cavity', 'Primary tooth caries'],
                'patient_instructions' => "Avoid eating for 30 minutes.\nDo not bite hard foods on the restored area for 24 hours.",
                'sop' => [
                    'doctor_steps'    => ['Remove caries', 'Condition cavity with polyacrylic acid', 'Mix or dispense GIC', 'Place and adapt to cavity', 'Protect surface with varnish or light cure', 'Finish after 24 hours ideally'],
                    'assistant_steps' => ['Prepare GIC capsule or mix proportions correctly', 'Have conditioner ready', 'Ensure varnish is available'],
                    'pre'  => "No special preparation.",
                    'post' => "Avoid hard food for 24 hours. Mild sensitivity is normal for a day or two.",
                    'consent' => "A glass ionomer filling will be placed. It releases fluoride to protect the tooth. It is not as strong as composite and may need replacement for high-stress areas.",
                ],
                'rules' => [
                    'min_visits' => ['count' => 1],
                    'max_visits' => ['count' => 1],
                ],
            ],

            // ── 4. ZIRCONIA CROWN ─────────────────────────────────────────────
            [
                'category'    => 'Crown & Bridge',
                'name'        => 'Zirconia Crown',
                'code'        => 'CB-01',
                'description' => 'Full-contour monolithic zirconia crown — high strength, excellent aesthetics, biocompatible, no metal. Recommended after RCT or for heavily broken-down teeth.',
                'duration'    => 60,
                'price'       => ['default' => 15000, 'min' => 12000, 'max' => 22000],
                'color'       => '#d97706',
                'specialty'   => 'prosthodontics',
                'sort_order'  => 4,
                'stages'      => [
                    ['key' => 'prep',       'label' => 'Tooth Preparation'],
                    ['key' => 'impression', 'label' => 'Impression / Scan'],
                    ['key' => 'temp_crown', 'label' => 'Temporary Crown'],
                    ['key' => 'try_in',     'label' => 'Trial Fitting'],
                    ['key' => 'cementation','label' => 'Cementation'],
                    ['key' => 'review',     'label' => 'Review'],
                ],
                'keywords'    => ['crown', 'cap', 'zirconia', 'broken tooth', 'rct crown', 'tooth cap', 'white crown', 'cap on tooth', 'tooth after rct', 'cracked tooth'],
                'questions'   => ['Is this after an RCT or for a broken tooth?', 'Any sensitivity currently?', 'Any allergies to dental metals?', 'Preference: all-white or no preference?'],
                'investigations' => ['IOPA (check post-RCT status)', 'Clinical photographs for shade', 'Periapical check if previous RCT'],
                'diagnoses'   => ['Post-RCT tooth requiring crown', 'Heavily restored tooth', 'Fractured cusp', 'Cracked tooth syndrome'],
                'patient_instructions' => "Temporary crown in place — avoid sticky or hard food.\nFinal crown: avoid biting on very hard objects.\nMaintain good oral hygiene around the crown margins.",
                'sop' => [
                    'doctor_steps'    => ['Assess tooth restorability, confirm post-RCT status on X-ray', 'Prepare tooth — 1.5mm occlusal, 1mm circumferential reduction', 'Take digital scan or polyvinyl siloxane impression', 'Record shade (VITA scale)', 'Place temporary crown with Temp Bond', 'Send to lab with prescription (shade, type: monolithic/layered)', 'Try-in: check fit, occlusion, shade, contacts', 'Cement with resin cement / GIC cement', 'Check occlusion, clean excess cement', 'Post-cementation IOPA'],
                    'assistant_steps' => ['Set up crown prep tray: crown prep burs, retraction cord, impression trays', 'Prepare temporary crown material (Protemp)', 'Send impression with completed lab form', 'Book try-in appointment for 7–10 days'],
                    'pre'  => "No special preparation. If anxious, let us know.",
                    'post' => "Temporary crown: avoid hard and sticky food, do not floss aggressively.\nPermanent crown: maintain normal hygiene. Use floss and an interdental brush.\nReturn immediately if the crown comes off or causes pain.",
                    'consent' => "The tooth will be shaped and a custom-made zirconia crown will be placed. Risks include sensitivity after preparation, rare crown failure, and need for re-cementation. Good home care is essential for crown longevity.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA to confirm root status before crown preparation',
                    'consent_required' => 'Written consent required',
                    'lab_required'     => 'Crown fabricated by dental lab — 7–10 day turnaround',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 3],
                    'follow_up_days'   => ['days' => 30],
                ],
            ],

            // ── 5. PFM CROWN ──────────────────────────────────────────────────
            [
                'category'    => 'Crown & Bridge',
                'name'        => 'PFM Crown',
                'code'        => 'CB-02',
                'description' => 'Porcelain-fused-to-metal crown — metal substructure with porcelain veneer. Strong, cost-effective, slight greyish line at gum margin.',
                'duration'    => 60,
                'price'       => ['default' => 9000, 'min' => 7000, 'max' => 13000],
                'color'       => '#b45309',
                'specialty'   => 'prosthodontics',
                'sort_order'  => 5,
                'stages'      => [
                    ['key' => 'prep',        'label' => 'Tooth Preparation'],
                    ['key' => 'impression',  'label' => 'Impression'],
                    ['key' => 'temp_crown',  'label' => 'Temporary Crown'],
                    ['key' => 'try_in',      'label' => 'Trial Fitting'],
                    ['key' => 'cementation', 'label' => 'Cementation'],
                ],
                'keywords'    => ['pfm crown', 'porcelain crown', 'metal crown', 'cap', 'broken tooth', 'crown after rct', 'back tooth crown'],
                'questions'   => ['Is this for front or back tooth?', 'Any metal allergies?', 'Budget preference?', 'Is this after an RCT?'],
                'investigations' => ['IOPA', 'Shade guide photos'],
                'diagnoses'   => ['Post-RCT tooth', 'Heavily restored molar/premolar', 'Fractured tooth'],
                'patient_instructions' => "Same as zirconia crown instructions. Temporary crown: avoid sticky food.",
                'sop' => [
                    'doctor_steps'    => ['Assess restorability and IOPA', 'Prepare tooth (same as zirconia but 1.2mm shoulder)', 'Impression, shade, temporary crown', 'Lab prescription: PFM, shade, tooth number', 'Try-in: check metal fit before porcelain', 'Final cementation with zinc phosphate or GIC cement'],
                    'assistant_steps' => ['Same as zirconia crown setup', 'Note: confirm lab sends metal try-in before porcelain bake'],
                    'pre'  => "No special preparation.",
                    'post' => "Avoid biting very hard objects. If dark line at gum bothers you in the future, can be replaced with zirconia.",
                    'consent' => "A metal-ceramic crown will be placed. A thin metal line may be visible at the gum over time. Risks include fracture of porcelain, sensitivity, need for recementation.",
                ],
                'rules' => [
                    'xray_required'  => 'Pre-op IOPA required',
                    'consent_required'=> 'Written consent',
                    'lab_required'   => 'Lab fabrication — 7–10 days',
                    'min_visits'     => ['count' => 2],
                    'max_visits'     => ['count' => 3],
                ],
            ],

            // ── 6. DENTAL BRIDGE (3-UNIT) ─────────────────────────────────────
            [
                'category'    => 'Crown & Bridge',
                'name'        => 'Dental Bridge (3-Unit)',
                'code'        => 'CB-03',
                'description' => '3-unit fixed bridge replacing a single missing tooth using adjacent teeth as abutments. Zirconia or PFM. Per unit pricing applies.',
                'duration'    => 60,
                'price'       => ['default' => 28000, 'min' => 18000, 'max' => 45000],
                'color'       => '#92400e',
                'specialty'   => 'prosthodontics',
                'sort_order'  => 6,
                'stages'      => [
                    ['key' => 'assessment',  'label' => 'Abutment Assessment'],
                    ['key' => 'prep',        'label' => 'Abutment Preparation'],
                    ['key' => 'impression',  'label' => 'Impression'],
                    ['key' => 'try_in',      'label' => 'Framework Try-in'],
                    ['key' => 'cementation', 'label' => 'Cementation'],
                    ['key' => 'review',      'label' => 'Review'],
                ],
                'keywords'    => ['missing tooth', 'gap', 'bridge', 'replacement', 'fixed replacement', 'permanent replacement', 'extracted tooth', 'cap on both sides'],
                'questions'   => ['How long has the tooth been missing?', 'Are the adjacent teeth healthy?', 'Have you had any difficulty chewing?', 'Preference: implant or bridge?', 'Any previous RCT on adjacent teeth?'],
                'investigations' => ['OPG or IOPA of abutment teeth', 'Check vitality of abutments', 'Bone level assessment', 'Study models / digital scan'],
                'diagnoses'   => ['Single missing tooth — bridge candidate', 'Short-span edentulous space', 'Patient declining implant'],
                'patient_instructions' => "Use a floss threader or water flosser to clean under the bridge daily.\nAvoid biting hard objects with the bridge.\nRegular 6-month check-ups to monitor bridge margins.",
                'sop' => [
                    'doctor_steps'    => ['Assess abutment teeth (vitality, bone support, restorability)', 'Plan pontic position, discuss material options', 'Prepare abutments: shoulder preparation', 'Impression of both arches + bite registration', 'Place temporary bridge', 'Try-in: check margins, contacts, occlusion, shade', 'Cement with resin cement; check occlusion carefully'],
                    'assistant_steps' => ['Full bridge prep tray, retraction cord', 'Temporary bridge material ready', 'Lab form: specify abutment teeth, pontic, material, shade'],
                    'pre'  => "Ensure abutment teeth are RCT-treated if required before bridge appointment.",
                    'post' => "Use floss threader under the bridge every day. Avoid hard chewing foods. Return if bridge feels loose.",
                    'consent' => "Adjacent teeth must be filed to serve as pillars. These teeth are irreversibly altered. An implant is a more conservative alternative. Bridge lifespan is 10–15 years with good care.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA / OPG for abutment and bone assessment',
                    'consent_required' => 'Written consent — irreversible abutment preparation',
                    'lab_required'     => 'Lab fabrication — 10–14 days',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 3],
                ],
            ],

            // ── 7. EXTRACTION ─────────────────────────────────────────────────
            [
                'category'    => 'Oral Surgery',
                'name'        => 'Extraction',
                'code'        => 'OS-01',
                'description' => 'Routine forceps extraction of a non-surgical, fully erupted tooth. Includes local anaesthesia and post-op care.',
                'duration'    => 30,
                'price'       => ['default' => 1500, 'min' => 800, 'max' => 3000],
                'color'       => '#7c3aed',
                'specialty'   => 'oral_surgery',
                'sort_order'  => 7,
                'stages'      => [
                    ['key' => 'assessment', 'label' => 'Clinical Assessment & X-ray'],
                    ['key' => 'extraction', 'label' => 'Extraction'],
                    ['key' => 'review',     'label' => 'Review (if needed)'],
                ],
                'keywords'    => ['remove tooth', 'pull out tooth', 'extract', 'extraction', 'tooth removal', 'get rid of tooth', 'bad tooth', 'unrestorable'],
                'questions'   => ['Any medical conditions or blood thinners?', 'Last extraction — any complications?', 'Is this tooth painful currently?', 'Any facial swelling or fever?'],
                'investigations' => ['IOPA (root morphology, bone level)', 'Blood pressure check', 'Blood sugar if diabetic'],
                'diagnoses'   => ['Unrestorable tooth', 'Grade III mobile tooth', 'Grossly decayed tooth', 'Orthodontic extraction'],
                'patient_instructions' => "Bite on the gauze for 30 minutes. Do not spit forcefully.\nNo hot food or drink for 6 hours.\nDo not rinse vigorously for 24 hours.\nTake prescribed painkillers. Apply ice pack for swelling.\nNo smoking or alcohol for 48 hours.",
                'sop' => [
                    'doctor_steps'    => ['Review IOPA — root morphology, bone', 'Medical history — anticoagulants, diabetes, bisphosphonates, cardiac', 'Administer LA, wait 5 minutes', 'Loosen periodontal ligament with luxators', 'Extract with appropriate forceps', 'Irrigate socket, check for bone fragments', 'Compress socket, place gauze', 'Give post-op instructions, prescribe analgesics if needed'],
                    'assistant_steps' => ['Prepare extraction tray: forceps, elevators, gauze, suture if needed', 'Have haemostatic agent (Surgicel / gelfoam) ready', 'Blood pressure cuff available'],
                    'pre'  => "Do not come on an empty stomach.\nInform the doctor of all medications and medical conditions.",
                    'post' => "Keep gauze in place for 30 min. No spitting, rinsing, or smoking for 24 hours.\nSoft diet for 2–3 days.\nSalt water rinse from Day 2.\nCall us if bleeding doesn't stop or swelling worsens.",
                    'consent' => "The tooth will be removed under local anaesthesia. Risks include pain, swelling, bleeding, dry socket, numbness (rare), and damage to adjacent teeth. Discuss tooth replacement options after healing.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA mandatory before any extraction',
                    'consent_required' => 'Written consent required',
                    'anesthesia_required' => 'Local anaesthesia standard',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 2],
                    'follow_up_days'   => ['days' => 7],
                    'medical_clearance'=> 'Required for anticoagulants, uncontrolled diabetes, bisphosphonate use',
                ],
            ],

            // ── 8. SURGICAL EXTRACTION ────────────────────────────────────────
            [
                'category'    => 'Oral Surgery',
                'name'        => 'Surgical Extraction',
                'code'        => 'OS-02',
                'description' => 'Surgical removal of impacted, ankylosed, or broken-down roots requiring mucoperiosteal flap, bone removal, or tooth sectioning.',
                'duration'    => 60,
                'price'       => ['default' => 5000, 'min' => 3000, 'max' => 9000],
                'color'       => '#6d28d9',
                'specialty'   => 'oral_surgery',
                'sort_order'  => 8,
                'stages'      => [
                    ['key' => 'assessment', 'label' => 'Clinical & Radiographic Assessment'],
                    ['key' => 'surgery',    'label' => 'Surgical Extraction'],
                    ['key' => 'suture',     'label' => 'Suturing'],
                    ['key' => 'review',     'label' => 'Suture Removal & Review'],
                ],
                'keywords'    => ['impacted', 'broken root', 'wisdom tooth pain', 'surgical removal', 'root left behind', 'buried tooth', 'ankylosed'],
                'questions'   => ['Is this a wisdom tooth?', 'Any difficulty opening mouth?', 'Previous surgery in this area?', 'Any medical conditions or blood thinners?'],
                'investigations' => ['OPG', 'IOPA', 'CBCT (if near inferior alveolar nerve)', 'Blood sugar, BP'],
                'diagnoses'   => ['Impacted wisdom tooth', 'Residual root', 'Ankylosed tooth', 'Horizontally impacted molar'],
                'patient_instructions' => "Rest for the day after surgery.\nApply ice pack for first 24 hours (20 min on, 20 min off).\nTake antibiotics and painkillers as prescribed.\nSoft diet for 5–7 days. Return for suture removal on Day 7.",
                'sop' => [
                    'doctor_steps'    => ['Review OPG/CBCT carefully — note root proximity to IAN', 'Informed consent including risk of nerve damage', 'LA + regional block', 'Raise mucoperiosteal flap', 'Remove bone with surgical bur (if needed)', 'Section tooth if required', 'Remove tooth, irrigate socket', 'Place sutures (3-0 vicryl / silk)', 'Post-op X-ray', 'Prescribe antibiotics + analgesics'],
                    'assistant_steps' => ['Surgical tray: scalpel, periosteal elevator, surgical burs, suction', 'Saline irrigation syringe ready', 'Suture material and needle holder', 'CBCT loaded on screen before procedure'],
                    'pre'  => "Rest well the night before. Light meal. Do not come alone — bring an escort.\nInform us of all medications and medical conditions.",
                    'post' => "Rest for 24–48 hours. No strenuous activity.\nDo not disturb the sutures. Return on Day 7 for removal.\nCall us for excessive bleeding, fever, or severe trismus.",
                    'consent' => "This is a surgical procedure requiring cutting of gum and sometimes removal of bone. Risks include pain, swelling, bruising, dry socket, nerve damage (temporary or rare permanent), and infection.",
                ],
                'rules' => [
                    'xray_required'    => 'OPG mandatory; CBCT if near IAN',
                    'consent_required' => 'Detailed written consent including nerve damage risk',
                    'anesthesia_required' => 'Local anaesthesia; IV sedation option available',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 3],
                    'follow_up_days'   => ['days' => 7],
                    'medical_clearance'=> 'Required for anticoagulants, diabetes, cardiac conditions',
                ],
            ],

            // ── 9. SINGLE DENTAL IMPLANT ──────────────────────────────────────
            [
                'category'    => 'Implantology',
                'name'        => 'Single Dental Implant',
                'code'        => 'IMP-01',
                'description' => 'Titanium implant fixture placement in the jaw to replace a missing tooth. Requires adequate bone volume. Crown placed after osseointegration (3–6 months).',
                'duration'    => 90,
                'price'       => ['default' => 45000, 'min' => 30000, 'max' => 70000],
                'color'       => '#0891b2',
                'specialty'   => 'implantology',
                'sort_order'  => 9,
                'stages'      => [
                    ['key' => 'planning',       'label' => 'CBCT Planning'],
                    ['key' => 'implant_surgery', 'label' => 'Implant Placement'],
                    ['key' => 'healing',        'label' => 'Healing & Osseointegration'],
                    ['key' => 'abutment',       'label' => 'Abutment Placement'],
                    ['key' => 'crown',          'label' => 'Implant Crown'],
                    ['key' => 'review',         'label' => 'Annual Review'],
                ],
                'keywords'    => ['implant', 'missing tooth', 'permanent replacement', 'screw in tooth', 'titanium tooth', 'implant crown', 'bone implant', 'artificial tooth root'],
                'questions'   => ['How long has the tooth been missing?', 'Any medical conditions — diabetes, osteoporosis, heart disease?', 'Currently smoking?', 'On bisphosphonates or blood thinners?', 'Adequate bone volume? (CBCT needed)'],
                'investigations' => ['CBCT (mandatory for implant planning)', 'OPG', 'Blood tests: CBC, blood sugar, INR', 'Diagnostic wax-up / digital planning'],
                'diagnoses'   => ['Single missing tooth — implant candidate', 'Adequate bone volume confirmed', 'Patient medically fit for implant surgery'],
                'patient_instructions' => "After implant placement: soft diet for 2 weeks, no smoking.\nTake antibiotics and painkillers as prescribed.\nNo vigorous rinsing for 24 hours. Suture removal at Day 7.\nReturn for crown placement after 3–6 months (after osseointegration).",
                'sop' => [
                    'doctor_steps'    => ['Review CBCT: bone height, width, proximity to structures', 'Plan implant size and position using implant planning software', 'Medical clearance if needed', 'Surgical guide fabrication (if needed)', 'LA + flap or flapless approach', 'Osteotomy: pilot drill → sequential drills → final diameter', 'Torque implant to 35 Ncm minimum', 'Place healing abutment or cover screw', 'Suture if required', 'Post-op IOPA/CBCT', 'Review at 1 week, 1 month, 3 months', 'Load with crown after osseointegration confirmed'],
                    'assistant_steps' => ['Surgical implant kit: drills in sequence, torque wrench, implant drivers', 'Sterile saline irrigation', 'Implant fixture: confirm size matches plan before opening', 'Record implant brand, size, batch number in chart'],
                    'pre'  => "Medical clearance for high-risk patients.\nInform us of all medications. Blood sugar should be controlled.\nNo smoking for 2 weeks before and after surgery.",
                    'post' => "Soft diet for 2 weeks. No smoking.\nComplete the full antibiotic course.\nDo not touch or disturb the implant site.\nMild swelling/bruising for 3–5 days is normal.",
                    'consent' => "An implant is a titanium screw placed in the jawbone. Risks include infection, failure to osseointegrate (~5%), nerve proximity issues, and need for bone grafting if inadequate bone. Total treatment time: 4–8 months.",
                ],
                'rules' => [
                    'xray_required'    => 'CBCT mandatory before implant placement',
                    'consent_required' => 'Detailed written consent — surgical risks + failure rate',
                    'anesthesia_required' => 'Local anaesthesia; sedation available',
                    'lab_required'     => 'Surgical guide, implant crown by lab',
                    'min_visits'       => ['count' => 4],
                    'max_visits'       => ['count' => 8],
                    'follow_up_days'   => ['days' => 7],
                    'medical_clearance'=> 'Required for diabetes, bisphosphonates, anticoagulants, immunosuppression',
                ],
            ],

            // ── 10. SCALING & POLISHING ───────────────────────────────────────
            [
                'category'    => 'Periodontics',
                'name'        => 'Scaling & Polishing',
                'code'        => 'PERIO-01',
                'description' => 'Supragingival scaling to remove calculus and plaque, followed by coronal polishing. Preventive and baseline periodontal therapy.',
                'duration'    => 45,
                'price'       => ['default' => 2000, 'min' => 1500, 'max' => 3500],
                'color'       => '#2563eb',
                'specialty'   => 'periodontics',
                'sort_order'  => 10,
                'stages'      => [
                    ['key' => 'assessment', 'label' => 'Periodontal Assessment'],
                    ['key' => 'scaling',    'label' => 'Ultrasonic Scaling'],
                    ['key' => 'polishing',  'label' => 'Coronal Polishing'],
                    ['key' => 'review',     'label' => 'Review & OHI'],
                ],
                'keywords'    => ['scaling', 'cleaning', 'tartar', 'calculus', 'yellow teeth', 'dirty teeth', 'bleeding gums', 'gum disease', 'bad breath', 'halitosis', 'teeth cleaning', 'polish'],
                'questions'   => ['How long since your last dental cleaning?', 'Do your gums bleed when brushing?', 'Any sensitivity after brushing?', 'Do you smoke?', 'Any family history of gum disease?'],
                'investigations' => ['BPE chart', 'IOPA or OPG if bone loss suspected', 'Blood sugar if diabetic symptoms'],
                'diagnoses'   => ['Plaque-induced gingivitis', 'Calculus accumulation', 'Early periodontitis (BPE 3)', 'Preventive recall cleaning'],
                'patient_instructions' => "Some sensitivity for 24–48 hours is normal.\nBrush twice daily with a soft brush and fluoride toothpaste.\nFloss daily. Use a chlorhexidine rinse for 5 days if prescribed.",
                'sop' => [
                    'doctor_steps'    => ['Full periodontal charting (BPE)', 'Ultrasonic scaler: remove supragingival calculus', 'Hand scalers for subgingival deposits in BPE 3 areas', 'Polish with prophylaxis paste and rubber cup', 'Oral hygiene instruction — brushing technique, flossing', 'Prescribe CHX rinse if gingivitis is active', 'Schedule 6-month recall'],
                    'assistant_steps' => ['Set up ultrasonic scaler with correct tip', 'Prophylaxis paste and polish cups ready', 'Suction, air-water syringe'],
                    'pre'  => "Brush and floss before your appointment.",
                    'post' => "Some sensitivity for a day or two is normal.\nBrush gently for 48 hours. Use prescribed rinse as directed.",
                    'consent' => "Tartar and stains will be removed using an ultrasonic scaler and polishing. Some sensitivity after cleaning is normal and temporary. Regular 6-monthly cleaning prevents gum disease.",
                ],
                'rules' => [
                    'min_visits'     => ['count' => 1],
                    'max_visits'     => ['count' => 2],
                    'follow_up_days' => ['days' => 180],
                ],
            ],

            // ── 11. DEEP CLEANING (SRP) ───────────────────────────────────────
            [
                'category'    => 'Periodontics',
                'name'        => 'Deep Cleaning (SRP)',
                'code'        => 'PERIO-02',
                'description' => 'Scaling and root planing (SRP) — subgingival removal of calculus and cementum with toxins from root surfaces. Non-surgical periodontitis treatment.',
                'duration'    => 60,
                'price'       => ['default' => 9000, 'min' => 5000, 'max' => 15000],
                'color'       => '#1d4ed8',
                'specialty'   => 'periodontics',
                'sort_order'  => 11,
                'stages'      => [
                    ['key' => 'assessment',   'label' => 'Full Periodontal Assessment'],
                    ['key' => 'srp_quad1',    'label' => 'SRP Quadrant 1'],
                    ['key' => 'srp_quad2',    'label' => 'SRP Quadrant 2'],
                    ['key' => 'srp_quad3',    'label' => 'SRP Quadrant 3'],
                    ['key' => 'srp_quad4',    'label' => 'SRP Quadrant 4'],
                    ['key' => 'reassessment', 'label' => 'Reassessment at 6–8 weeks'],
                ],
                'keywords'    => ['deep cleaning', 'srp', 'root planing', 'deep pockets', 'bone loss', 'loose teeth', 'gum disease treatment', 'periodontitis', 'deep scaling', 'pocket depth'],
                'questions'   => ['Do your teeth feel loose?', 'How long has gum bleeding been present?', 'Do you smoke or use tobacco?', 'Any diabetes or systemic conditions?', 'Deepest pocket depth on chart?'],
                'investigations' => ['Full mouth periapicals or OPG', 'Periodontal charting (6-point probing)', 'Blood sugar', 'CBC if systemic concern'],
                'diagnoses'   => ['Stage II Periodontitis', 'Stage III Periodontitis', 'Generalised moderate-severe chronic periodontitis'],
                'patient_instructions' => "Gum soreness and sensitivity for 3–5 days is expected.\nUse a soft toothbrush and chlorhexidine rinse as prescribed.\nDo not smoke — smoking significantly reduces treatment success.\nReturn for reassessment at 6–8 weeks.",
                'sop' => [
                    'doctor_steps'    => ['Complete periodontal chart (6 points per tooth, BPE)', 'Diagnose stage and grade of periodontitis', 'LA per quadrant', 'Ultrasonic + curettes: root planing per quadrant (one quadrant per visit)', 'Irrigation with CHX', 'OHI reinforcement each visit', 'Reassess at 6–8 weeks post-SRP', 'Decide: maintain, recall, or flap surgery'],
                    'assistant_steps' => ['Universal and Gracey curettes sharpened and sterilised', 'LA syringe ready per quadrant', 'Warm saline / CHX for irrigation'],
                    'pre'  => "Avoid smoking for at least 48 hours before the appointment.\nEat lightly. Take any prescribed antibiotics as directed.",
                    'post' => "Gum soreness for 3–5 days is normal. Use prescribed CHX rinse.\nSoft brushing twice daily. Avoid smoking.\nReturn in 6–8 weeks for reassessment.",
                    'consent' => "Subgingival cleaning involves working below the gum line under local anaesthesia. Gums may recede slightly after treatment (this is healthy). Pockets should reduce. Smoking significantly reduces success. Surgery may be needed if pockets don't respond.",
                ],
                'rules' => [
                    'xray_required'    => 'Full mouth periapicals or OPG mandatory',
                    'consent_required' => 'Written consent required',
                    'anesthesia_required' => 'Local anaesthesia per quadrant',
                    'min_visits'       => ['count' => 4],
                    'max_visits'       => ['count' => 6],
                    'follow_up_days'   => ['days' => 45],
                    'medical_clearance'=> 'Required if uncontrolled diabetes or systemic conditions',
                ],
            ],

            // ── 12. CLEAR ALIGNERS ────────────────────────────────────────────
            [
                'category'    => 'Orthodontics',
                'name'        => 'Clear Aligners',
                'code'        => 'ORTHO-01',
                'description' => 'Customised removable clear aligner therapy for correction of mild to moderate malocclusion. Includes full course with refinements.',
                'duration'    => 30,
                'price'       => ['default' => 120000, 'min' => 80000, 'max' => 200000],
                'color'       => '#be185d',
                'specialty'   => 'orthodontics',
                'sort_order'  => 12,
                'stages'      => [
                    ['key' => 'records',      'label' => 'Orthodontic Records'],
                    ['key' => 'planning',     'label' => 'Aligner Design & Approval'],
                    ['key' => 'delivery',     'label' => 'Aligner Delivery'],
                    ['key' => 'progress',     'label' => 'Progress Reviews (every 6–8 weeks)'],
                    ['key' => 'refinements',  'label' => 'Refinements (if needed)'],
                    ['key' => 'retainer',     'label' => 'Retainer Delivery'],
                    ['key' => 'completion',   'label' => 'Treatment Completion'],
                ],
                'keywords'    => ['aligners', 'clear aligners', 'invisible braces', 'crooked teeth', 'crowding', 'spacing', 'gap', 'straighten', 'braces alternative', 'teeth alignment', 'misaligned'],
                'questions'   => ['What bothers you most — crowding, spacing, or bite?', 'Have you worn braces or aligners before?', 'Estimated treatment duration acceptable?', 'Will you wear aligners 22 hours/day?', 'Any jaw clicking or pain?'],
                'investigations' => ['OPG', 'Lateral cephalogram', 'Full arch intraoral scan or PVS impression', 'Intraoral photographs', 'CBCT if skeletal discrepancy'],
                'diagnoses'   => ['Class I crowding', 'Class I spacing', 'Mild Class II div 1', 'Relapsed orthodontic case', 'Mild anterior crossbite'],
                'patient_instructions' => "Wear aligners for 22 hours per day.\nRemove only for eating, drinking, and brushing.\nBrush before reinserting aligners.\nAdvance to the next aligner as instructed (usually every 7–14 days).\nWear your retainer forever after treatment.",
                'sop' => [
                    'doctor_steps'    => ['Full orthodontic examination: crowding, overjet, overbite, midline, arch form', 'Take records: OPG, lateral ceph, photos, scan', 'Upload to aligner software (Invisalign/SureSmile/local lab)', 'Review and approve ClinCheck / treatment simulation with patient', 'Bond attachments as per plan', 'Deliver first set of aligners + cleaning kit', 'Review every 6–8 weeks: progress, compliance', 'Order refinements if needed', 'Take retainer records at end of active treatment'],
                    'assistant_steps' => ['Intraoral scanner calibrated and ready', 'Photo setup: DSLR or intraoral camera', 'Aligner delivery kit: aligner case, chewies, removal tool'],
                    'pre'  => "Ensure good periodontal health before starting aligners.\nAll cavities must be filled before treatment begins.",
                    'post' => "Wear retainers lifelong to maintain results.\nFixed retainer recommended for lower anteriors.\nReturn for annual orthodontic checks.",
                    'consent' => "Clear aligner treatment corrects teeth alignment using a series of custom trays. Total duration 12–24 months depending on complexity. Compliance is critical — must be worn 22 hours/day. Root resorption is a rare risk. A retainer must be worn after completion.",
                ],
                'rules' => [
                    'xray_required'    => 'OPG and lateral cephalogram before starting',
                    'consent_required' => 'Written consent including compliance requirement',
                    'lab_required'     => 'Aligner fabrication by specialist lab',
                    'min_visits'       => ['count' => 8],
                    'max_visits'       => ['count' => 20],
                    'follow_up_days'   => ['days' => 60],
                ],
            ],

            // ── 13. TEETH WHITENING ───────────────────────────────────────────
            [
                'category'    => 'Cosmetic Dentistry',
                'name'        => 'Teeth Whitening',
                'code'        => 'COS-01',
                'description' => 'In-office power bleaching with 35–40% hydrogen peroxide + LED/laser light activation, combined with take-home trays for optimal results.',
                'duration'    => 60,
                'price'       => ['default' => 12000, 'min' => 8000, 'max' => 18000],
                'color'       => '#ca8a04',
                'specialty'   => 'smile_design',
                'sort_order'  => 13,
                'stages'      => [
                    ['key' => 'assessment',   'label' => 'Shade Assessment & Photos'],
                    ['key' => 'in_office',    'label' => 'In-office Bleaching'],
                    ['key' => 'take_home',    'label' => 'Take-home Tray Delivery'],
                    ['key' => 'review',       'label' => 'Shade Review at 2 weeks'],
                ],
                'keywords'    => ['whitening', 'bleaching', 'yellow teeth', 'stained teeth', 'discoloured', 'white smile', 'bright teeth', 'teeth colour', 'smile improvement', 'zoom whitening'],
                'questions'   => ['Current tooth shade?', 'Target shade expectation?', 'Any crowns, veneers, or fillings on front teeth?', 'Do you have sensitive teeth?', 'Any gum disease currently?'],
                'investigations' => ['Shade assessment (VITA scale)', 'Clinical photos: smile, retracted', 'BPE — ensure gums are healthy before whitening'],
                'diagnoses'   => ['Extrinsic staining', 'Age-related discolouration', 'Tetracycline staining (reduced response)', 'Fluorosis'],
                'patient_instructions' => "Avoid tea, coffee, red wine, turmeric, and tobacco for 48 hours after treatment.\nSome sensitivity for 24–48 hours is normal — use Sensodyne toothpaste.\nWear take-home trays as directed for 7–14 nights.\nResults last 1–2 years with good maintenance.",
                'sop' => [
                    'doctor_steps'    => ['Record baseline shade, take photographs', 'Ensure healthy gums — no active caries or gingivitis', 'Apply gingival barrier', 'Apply whitening gel (35% H2O2), cover teeth', 'Activate with LED / laser light per protocol (3 × 15 min sessions)', 'Remove gel, rinse, record final shade', 'Deliver take-home trays + gel with instructions'],
                    'assistant_steps' => ['Whitening kit set up: gel, barriers, light, protective eyewear', 'Shade guide, intraoral camera ready', 'Post-whitening sensitivity gel (fluoride) available'],
                    'pre'  => "Brush teeth before the appointment.\nAvoid staining food/drink 24 hours before.",
                    'post' => "Avoid staining food and drinks for 48 hours (coffee, tea, wine, curry).\nSensitivity is normal — use Sensodyne for 48 hours.\nDo not smoke — it reverses results quickly.",
                    'consent' => "Whitening lightens natural tooth colour. Results vary by person. Pre-existing crowns and fillings will not change colour — may need replacement after whitening. Sensitivity is a known side effect. Results last 1–2 years.",
                ],
                'rules' => [
                    'consent_required' => 'Written consent — results vary, sensitivity risk',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 2],
                    'follow_up_days'   => ['days' => 14],
                ],
            ],

            // ── 14. COMPLETE DENTURE ──────────────────────────────────────────
            [
                'category'    => 'Prosthodontics',
                'name'        => 'Complete Denture',
                'code'        => 'PROS-01',
                'description' => 'Removable full denture for completely edentulous arch. Acrylic base with acrylic or porcelain teeth. Requires 5–6 visits for optimal fit.',
                'duration'    => 60,
                'price'       => ['default' => 22000, 'min' => 15000, 'max' => 35000],
                'color'       => '#6b7280',
                'specialty'   => 'prosthodontics',
                'sort_order'  => 14,
                'stages'      => [
                    ['key' => 'primary_impression',   'label' => 'Primary Impression'],
                    ['key' => 'special_tray',         'label' => 'Special Tray & Final Impression'],
                    ['key' => 'jaw_relation',         'label' => 'Jaw Relation Record'],
                    ['key' => 'try_in',               'label' => 'Wax Try-in'],
                    ['key' => 'insertion',            'label' => 'Denture Insertion'],
                    ['key' => 'review',               'label' => 'Review & Adjustments'],
                ],
                'keywords'    => ['denture', 'full denture', 'complete denture', 'all teeth removed', 'no teeth', 'edentulous', 'removable teeth', 'false teeth', 'full plate'],
                'questions'   => ['Is this for upper, lower, or both arches?', 'Previously worn a denture? Any issues?', 'How long since teeth were removed?', 'Any ridge resorption on examination?', 'Any denture phobia or gag reflex?'],
                'investigations' => ['OPG (residual roots or bone pathology)', 'Ridge assessment by palpation', 'Study casts from primary impression'],
                'diagnoses'   => ['Completely edentulous maxilla', 'Completely edentulous mandible', 'Both arches edentulous — complete upper and lower dentures needed'],
                'patient_instructions' => "Remove and clean dentures after every meal.\nSoak in a denture cleaner overnight.\nDo not sleep with dentures in initially.\nReturn for adjustments — do not adjust yourself.\nRemove and rinse your gums daily.",
                'sop' => [
                    'doctor_steps'    => ['Primary impression with stock tray + alginate', 'Fabricate special tray', 'Functional impression with zinc oxide eugenol or PVS', 'Record jaw relation: vertical dimension + centric relation + lip support', 'Select tooth shade and mould', 'Wax try-in: phonetics, aesthetics, occlusion — patient approval', 'Send to lab for processing', 'Denture insertion: deliver, check retention, occlusion, pressure spots', 'Review at Day 3, 1 week, 1 month'],
                    'assistant_steps' => ['Primary impression setup: alginate, stock trays', 'Lab form: shade, mould, type of teeth, arch', 'Pressure indicating paste for adjustment at insertion'],
                    'pre'  => "No special preparation.",
                    'post' => "Wear dentures for first 24 hours (except when sleeping later).\nExpect soreness at pressure points — return for adjustment within 2–3 days.\nDo not make adjustments yourself.\nClean with a soft brush and denture cleaner daily.",
                    'consent' => "Complete dentures are removable replacements for all teeth. They require adjustment visits. Bite and comfort improve over weeks. Bone resorption over time may require relining or new dentures every 5–7 years.",
                ],
                'rules' => [
                    'xray_required'  => 'OPG to rule out residual roots or bone pathology',
                    'consent_required'=> 'Written consent',
                    'lab_required'   => 'Lab fabrication at multiple stages',
                    'min_visits'     => ['count' => 5],
                    'max_visits'     => ['count' => 8],
                    'follow_up_days' => ['days' => 3],
                ],
            ],

        ]; // end $treatments array

        // ── Persist all treatments ────────────────────────────────────────────
        foreach ($treatments as $data) {

            $cat = $catModels[$data['category']];

            $treatment = Treatment::updateOrCreate(
                ['code' => $data['code']],
                [
                    'treatment_category_id'    => $cat->id,
                    'name'                     => $data['name'],
                    'code'                     => $data['code'],
                    'description'              => $data['description'],
                    'default_duration_minutes' => $data['duration'],
                    'default_price'            => $data['price']['default'],
                    'min_price'                => $data['price']['min'],
                    'max_price'                => $data['price']['max'],
                    'gst_pct'                  => 0.00,
                    'color'                    => $data['color'],
                    'specialty_tag'            => $data['specialty'],
                    'sort_order'               => $data['sort_order'],
                    'is_active'                => true,
                    'stages'                   => $data['stages'],
                    'trigger_keywords'         => $data['keywords'],
                    'suggested_questions'      => $data['questions'],
                    'suggested_investigations' => $data['investigations'],
                    'possible_diagnoses'       => $data['diagnoses'],
                    'patient_instructions'     => $data['patient_instructions'],
                    'consent_template'         => $data['sop']['consent'],
                ]
            );

            // SOP ──────────────────────────────────────────────────────────────
            TreatmentSop::updateOrCreate(
                ['treatment_id' => $treatment->id, 'version' => 1],
                [
                    'status'           => 'active',
                    'version'          => 1,
                    'doctor_steps'     => $data['sop']['doctor_steps'],
                    'assistant_steps'  => $data['sop']['assistant_steps'],
                    'pre_instructions' => $data['sop']['pre'],
                    'post_instructions'=> $data['sop']['post'],
                    'consent_notes'    => $data['sop']['consent'],
                ]
            );

            // Rules ────────────────────────────────────────────────────────────
            foreach ($data['rules'] as $ruleType => $ruleValue) {
                // Boolean rules have a note string; value rules have an array
                $isBoolean = is_string($ruleValue);
                TreatmentRule::updateOrCreate(
                    ['treatment_id' => $treatment->id, 'rule_type' => $ruleType],
                    [
                        'value'     => $isBoolean ? null : $ruleValue,
                        'note'      => $isBoolean ? $ruleValue : null,
                        'is_active' => true,
                    ]
                );
            }

            $this->command->info("  ✓ {$data['name']} ({$data['code']}) — ₹{$data['price']['min']}–₹{$data['price']['max']}");
        }

        $this->command->info('');
        $this->command->info('══════════════════════════════════════════════════════════');
        $this->command->info('  DentalTreatmentsMasterSeeder complete — ' . count($treatments) . ' treatments seeded');
        $this->command->info('  Pricing: Tier 1 Indian cities (Mumbai / Delhi / Bengaluru)');
        $this->command->info('  All data is editable from Treatments → Edit in the admin panel');
        $this->command->info('══════════════════════════════════════════════════════════');
    }
}
