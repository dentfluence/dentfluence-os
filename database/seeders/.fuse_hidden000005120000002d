<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Treatment;
use App\Models\TreatmentCategory;
use App\Models\TreatmentSop;
use App\Models\TreatmentRule;

/**
 * DentalTreatmentsMasterSeeder2
 *
 * 16 additional treatments — no overlap with DentalTreatmentsMasterSeeder.
 * New categories: Pediatric Dentistry, Emergency, Consultation.
 * Pricing: Tier 1 Indian cities (Mumbai, Delhi, Bengaluru, Pune, Chennai, Hyderabad).
 *
 * Run: php artisan db:seed --class=DentalTreatmentsMasterSeeder2
 * Safe to re-run: uses updateOrCreate on treatment code.
 */
class DentalTreatmentsMasterSeeder2 extends Seeder
{
    public function run(): void
    {
        // ── Ensure all categories exist ───────────────────────────────────────
        $cats = [];
        foreach ([
            'Endodontics'        => '#dc2626',
            'Restorative'        => '#16a34a',
            'Crown & Bridge'     => '#d97706',
            'Oral Surgery'       => '#7c3aed',
            'Implantology'       => '#0891b2',
            'Periodontics'       => '#2563eb',
            'Orthodontics'       => '#be185d',
            'Prosthodontics'     => '#6b7280',
            'Cosmetic Dentistry' => '#ca8a04',
            'Pediatric Dentistry'=> '#0d9488',
            'Emergency'          => '#ef4444',
            'Consultation'       => '#64748b',
        ] as $name => $color) {
            $cats[$name] = TreatmentCategory::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }

        $treatments = [

            // ── 1. RE-ROOT CANAL TREATMENT ────────────────────────────────────
            [
                'category'    => 'Endodontics',
                'name'        => 'Re-Root Canal Treatment',
                'code'        => 'RCT-02',
                'description' => 'Retreatment of a previously obturated tooth that has failed — persistent or new periapical pathology after primary RCT.',
                'duration'    => 90,
                'price'       => ['default' => 10000, 'min' => 8000, 'max' => 18000],
                'color'       => '#b91c1c',
                'specialty'   => 'endodontics',
                'sort_order'  => 2,
                'stages'      => [
                    ['key' => 'diagnosis',     'label' => 'Diagnosis & CBCT'],
                    ['key' => 'access',        'label' => 'Access & GP Removal'],
                    ['key' => 'reinstrumentation', 'label' => 'Re-instrumentation'],
                    ['key' => 'obturation',    'label' => 'Re-obturation'],
                    ['key' => 'review',        'label' => 'Follow-up at 6 months'],
                ],
                'keywords'    => ['rct failed', 'root canal pain again', 're-rct', 'retreatment', 'infection after rct', 'pain after root canal', 'abscess after rct', 'old rct hurting', 'root canal not working'],
                'questions'   => ['When was the original RCT done?', 'Was a crown placed after?', 'When did symptoms return?', 'Any swelling or sinus tract near the tooth?', 'Were there any complications with the original RCT?'],
                'investigations' => ['IOPA / RVG', 'CBCT (missed canals, separated instrument, fracture)', 'Percussion & palpation tests'],
                'diagnoses'   => ['Previously treated — symptomatic apical periodontitis', 'Persistent periapical lesion', 'Missed canal', 'Coronal leakage causing re-infection'],
                'patient_instructions' => "This tooth has had a root canal before. Retreatment is more complex and may require more visits.\nDo not delay the crown after retreatment.\nIf symptoms do not resolve, periapical surgery (apicoectomy) may be needed.",
                'sop' => [
                    'doctor_steps'    => ['Review old X-ray vs current — identify reason for failure', 'CBCT if missed canal or complex anatomy suspected', 'Access through old crown/restoration', 'Remove existing gutta-percha (heat, solvents, Hedstrom files, ultrasonic)', 'Locate and instrument all canals including previously missed ones', 'Copious irrigation: NaOCl + EDTA', 'Calcium hydroxide dressing × 2–4 weeks if active infection', 'Re-obturate when canals are dry and symptom-free', 'Post-obturation IOPA', 'Crown mandatory after retreatment'],
                    'assistant_steps' => ['CBCT loaded on screen', 'Retreatment burs and GP removal kit ready', 'Extra irrigation syringes: NaOCl and EDTA labelled'],
                    'pre'  => "Take prescribed antibiotics if given. Eat before the appointment.",
                    'post' => "More soreness than a primary RCT is normal for 3–5 days.\nDo not chew hard food on this tooth.\nCrown is mandatory — book the appointment before leaving.",
                    'consent' => "This is retreatment of a failed root canal. It is more complex and costly than the original. If retreatment fails, options include periapical surgery or extraction. A crown is mandatory after completion.",
                ],
                'rules' => [
                    'xray_required'       => 'CBCT strongly recommended before retreatment',
                    'consent_required'    => 'Written consent — failure risk higher than primary RCT',
                    'anesthesia_required' => 'Local anaesthesia',
                    'min_visits'          => ['count' => 2],
                    'max_visits'          => ['count' => 5],
                    'follow_up_days'      => ['days' => 180],
                    'medical_clearance'   => 'Required for high-risk systemic conditions',
                ],
            ],

            // ── 2. POST & CORE ────────────────────────────────────────────────
            [
                'category'    => 'Endodontics',
                'name'        => 'Post & Core',
                'code'        => 'RCT-03',
                'description' => 'Intracanal post (fibre or cast) with composite or cast metal core to provide retention and bulk for a crown on an extensively broken-down RCT tooth.',
                'duration'    => 45,
                'price'       => ['default' => 5500, 'min' => 4000, 'max' => 9000],
                'color'       => '#991b1b',
                'specialty'   => 'endodontics',
                'sort_order'  => 3,
                'stages'      => [
                    ['key' => 'post_space',  'label' => 'Post Space Preparation'],
                    ['key' => 'cementation', 'label' => 'Post Cementation'],
                    ['key' => 'core',        'label' => 'Core Build-up'],
                    ['key' => 'crown_prep',  'label' => 'Crown Preparation (next visit)'],
                ],
                'keywords'    => ['post and core', 'broken rct tooth', 'nothing left of tooth after rct', 'tooth needs post', 'fibre post', 'cast post', 'core buildup rct'],
                'questions'   => ['How much natural tooth structure remains?', 'Is the RCT complete and confirmed on X-ray?', 'Any pain or sinus tract currently?'],
                'investigations' => ['IOPA — confirm obturation quality and post space length', 'Assess remaining coronal tooth structure'],
                'diagnoses'   => ['Grossly broken-down tooth post-RCT requiring post for crown retention'],
                'patient_instructions' => "A post has been placed inside the root to support the crown.\nDo not chew hard food until the crown is placed.\nBook the crown appointment within 2 weeks.",
                'sop' => [
                    'doctor_steps'    => ['Confirm RCT is complete and periapical area is healed', 'Remove 2/3 of gutta-percha using Gates Glidden + Pesso reamer — leave 4–5mm apically', 'Select fibre post size, try-in', 'Etch dentine, apply adhesive, cement post with resin cement', 'Build composite core around post', 'Shape core for crown preparation'],
                    'assistant_steps' => ['Post kit: Gates Glidden, Pesso reamers, post box with try-in posts', 'Resin cement (Panavia / RelyX) loaded and ready', 'Core build-up composite shade ready'],
                    'pre'  => "No special preparation. Ensure RCT is complete and healing confirmed.",
                    'post' => "Avoid biting hard until crown is placed. Crown appointment is essential.",
                    'consent' => "A post will be placed inside the root canal to provide support for the crown. Risks include root fracture (rare) if post is too large, cement failure, and the tooth still may need extraction if insufficient structure remains.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA to confirm RCT quality before post space preparation',
                    'consent_required' => 'Written consent',
                    'lab_required'     => 'Cast post: lab required (if cast metal post chosen)',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 2],
                ],
            ],

            // ── 3. CORE BUILD-UP ──────────────────────────────────────────────
            [
                'category'    => 'Restorative',
                'name'        => 'Core Build-up',
                'code'        => 'REST-03',
                'description' => 'Composite or amalgam build-up of missing coronal tooth structure to provide adequate retention form for crown preparation on vital or endodontically treated teeth.',
                'duration'    => 30,
                'price'       => ['default' => 2500, 'min' => 1500, 'max' => 4500],
                'color'       => '#166534',
                'specialty'   => 'restorative',
                'sort_order'  => 4,
                'stages'      => [
                    ['key' => 'preparation', 'label' => 'Core Build-up'],
                    ['key' => 'crown_prep',  'label' => 'Crown Preparation (same/next visit)'],
                ],
                'keywords'    => ['core buildup', 'build up tooth', 'not enough tooth for crown', 'missing tooth wall', 'broken crown', 'half tooth left', 'foundation for crown'],
                'questions'   => ['Is the tooth vital or RCT treated?', 'How many walls are remaining?', 'Is a post needed as well?'],
                'investigations' => ['IOPA — assess remaining tooth structure and pulp status'],
                'diagnoses'   => ['Inadequate tooth structure for crown retention — core build-up required'],
                'patient_instructions' => "The core build-up is the foundation for your crown.\nAvoid chewing hard food until the crown is placed.",
                'sop' => [
                    'doctor_steps'    => ['Assess remaining tooth structure and walls', 'Place matrix band for guidance', 'Etch, bond, build composite incrementally', 'Light cure each increment', 'Shape core for crown prep — adequate retention form'],
                    'assistant_steps' => ['Matrix band and Tofflemire retainer set up', 'Core composite and bonding agent ready', 'Light cure unit charged'],
                    'pre'  => "No special preparation.",
                    'post' => "Avoid biting hard food. Crown appointment to follow shortly.",
                    'consent' => "Missing tooth structure will be replaced with a filling material to create a base for the crown. Without sufficient structure, the crown may not hold.",
                ],
                'rules' => [
                    'xray_required' => 'IOPA to assess tooth structure',
                    'min_visits'    => ['count' => 1],
                    'max_visits'    => ['count' => 1],
                ],
            ],

            // ── 4. EMAX CROWN ─────────────────────────────────────────────────
            [
                'category'    => 'Crown & Bridge',
                'name'        => 'Emax Crown',
                'code'        => 'CB-04',
                'description' => 'Lithium disilicate pressed ceramic crown — superior aesthetics, translucency mimicking natural enamel. Ideal for anterior teeth and smile zone.',
                'duration'    => 60,
                'price'       => ['default' => 18000, 'min' => 15000, 'max' => 25000],
                'color'       => '#b45309',
                'specialty'   => 'prosthodontics',
                'sort_order'  => 5,
                'stages'      => [
                    ['key' => 'prep',        'label' => 'Tooth Preparation'],
                    ['key' => 'impression',  'label' => 'Digital Scan / Impression'],
                    ['key' => 'temp_crown',  'label' => 'Temporary Crown'],
                    ['key' => 'try_in',      'label' => 'Try-in'],
                    ['key' => 'cementation', 'label' => 'Adhesive Cementation'],
                ],
                'keywords'    => ['emax', 'emax crown', 'ceramic crown', 'all ceramic', 'front tooth crown', 'aesthetic crown', 'translucent crown', 'smile crown', 'best crown'],
                'questions'   => ['Is this a front or back tooth?', 'Any parafunctional habits — bruxism or clenching?', 'Expected shade?', 'Is this post-RCT?'],
                'investigations' => ['IOPA', 'Clinical photographs for shade matching', 'Parafunctional assessment (night guard if bruxism)'],
                'diagnoses'   => ['Anterior tooth requiring crown', 'Cosmetic crown replacement', 'Post-RCT anterior tooth'],
                'patient_instructions' => "Temporary crown in place — avoid sticky and hard food.\nFinal Emax: do not bite hard objects (bottle caps, ice). Excellent aesthetics but can chip under extreme force.",
                'sop' => [
                    'doctor_steps'    => ['Confirm suitability — no bruxism ideally, no heavy posterior stress', 'Prepare tooth: 1.5mm occlusal, 1mm chamfer margin', 'Digital scan (preferred) or PVS impression', 'Shade mapping in natural and clinical light', 'Temporary crown', 'Try-in with try-in paste — check aesthetics with patient in mirror', 'Adhesive cementation: resin cement (Variolink / RelyX Veneer)', 'Remove excess cement meticulously under magnification', 'Post-cementation IOPA'],
                    'assistant_steps' => ['Crown prep tray + fine diamond burs for emax prep', 'Shade tabs for photography', 'Resin cement + light cure unit for adhesive cementation'],
                    'pre'  => "No special preparation.",
                    'post' => "Avoid hard biting forces on Emax crowns. Night guard if bruxism present. Return immediately if crown chips or debonds.",
                    'consent' => "Emax is the most aesthetic crown material. It requires adhesive cementation. It can chip under extreme biting forces. A night guard is recommended if bruxism is present. It cannot be adjusted once cemented — try-in approval is essential.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA before preparation',
                    'consent_required' => 'Written consent — irreversible prep',
                    'lab_required'     => 'Pressed ceramic — lab 10–14 days',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 3],
                    'follow_up_days'   => ['days' => 30],
                ],
            ],

            // ── 5. WISDOM TOOTH SURGERY ───────────────────────────────────────
            [
                'category'    => 'Oral Surgery',
                'name'        => 'Wisdom Tooth Surgery',
                'code'        => 'OS-03',
                'description' => 'Surgical removal of impacted or partially erupted mandibular or maxillary third molar. Includes flap, bone removal, tooth sectioning, and suturing.',
                'duration'    => 60,
                'price'       => ['default' => 7000, 'min' => 5000, 'max' => 12000],
                'color'       => '#5b21b6',
                'specialty'   => 'oral_surgery',
                'sort_order'  => 6,
                'stages'      => [
                    ['key' => 'assessment', 'label' => 'OPG & Assessment'],
                    ['key' => 'surgery',    'label' => 'Surgical Removal'],
                    ['key' => 'suture',     'label' => 'Suturing'],
                    ['key' => 'review',     'label' => 'Suture Removal (Day 7)'],
                ],
                'keywords'    => ['wisdom tooth', 'wisdom tooth pain', 'back tooth pain', '8th tooth', 'third molar', 'impacted tooth', 'wisdom tooth swelling', 'pericoronitis', 'operculum', 'last tooth hurts'],
                'questions'   => ['How long has the wisdom tooth been painful?', 'Any swelling in the cheek or jaw?', 'Difficulty opening mouth?', 'Pain in ear or throat on same side?', 'Has it happened before?'],
                'investigations' => ['OPG — angulation, depth, root morphology, IAN proximity', 'CBCT if roots close to inferior alveolar nerve', 'Blood sugar, BP'],
                'diagnoses'   => ['Pericoronitis', 'Horizontally impacted third molar', 'Mesioangular impaction', 'Partially erupted wisdom tooth causing decay in adjacent molar'],
                'patient_instructions' => "Rest at home for the remainder of the day.\nApply ice pack — 20 min on, 20 off — for first 24 hours.\nSoft diet for 5–7 days. No hot food/drinks for 24 hours.\nTake all antibiotics. Return on Day 7 for suture removal.\nNo smoking or alcohol for 48 hours.",
                'sop' => [
                    'doctor_steps'    => ['Review OPG carefully — Pell & Gregory / Winter classification', 'Counsel on IAN risk if roots are close', 'CBCT if IAN proximity confirmed', 'Inferior alveolar nerve block + long buccal block', 'Envelope or triangular flap', 'Bone guttering with surgical bur + saline irrigation', 'Section tooth as required (mesial cut, crown-root separation)', 'Elevate and remove in sections', 'Irrigate socket, smooth bony edges', 'Interrupted sutures 3-0 vicryl or silk', 'Post-op IOPA or OPG', 'Prescribe antibiotics + analgesics + mouth wash'],
                    'assistant_steps' => ['OPG loaded; CBCT ready if needed', 'Surgical tray: scalpel, periosteal elevator, bone rongeur, surgical burs, saline', 'Suture material: 3-0 vicryl or black silk'],
                    'pre'  => "Come with an escort — do not drive after sedation.\nLight meal 2 hours before. Take prescribed antibiotics if given.\nInform us of all medications and medical conditions.",
                    'post' => "Do not rinse for 24 hours. No spitting, smoking, or alcohol.\nApply ice pack for first day. Switch to warm salt water rinse from Day 2.\nSoft diet for 7 days. Return on Day 7 for suture removal.\nCall us if severe pain, increasing swelling, or fever develops.",
                    'consent' => "Wisdom tooth removal is a surgical procedure. Risks include post-operative pain, swelling, trismus, dry socket (5–10%), and temporary or permanent inferior alveolar nerve numbness (rare, risk increases with IAN proximity shown on CBCT).",
                ],
                'rules' => [
                    'xray_required'    => 'OPG mandatory; CBCT if IAN proximity',
                    'consent_required' => 'Detailed written consent — nerve damage risk',
                    'anesthesia_required' => 'IANB + long buccal block; sedation available',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 3],
                    'follow_up_days'   => ['days' => 7],
                    'medical_clearance'=> 'Required for anticoagulants, diabetes, cardiac conditions',
                ],
            ],

            // ── 6. IMPLANT CROWN ──────────────────────────────────────────────
            [
                'category'    => 'Implantology',
                'name'        => 'Implant Crown',
                'code'        => 'IMP-02',
                'description' => 'Custom prosthetic crown (zirconia or PFM) placed on a healed dental implant fixture after osseointegration is confirmed. Includes abutment and crown.',
                'duration'    => 60,
                'price'       => ['default' => 20000, 'min' => 15000, 'max' => 28000],
                'color'       => '#0e7490',
                'specialty'   => 'implantology',
                'sort_order'  => 7,
                'stages'      => [
                    ['key' => 'osseointegration_check', 'label' => 'Osseointegration Confirmation'],
                    ['key' => 'abutment',               'label' => 'Abutment Placement'],
                    ['key' => 'impression',             'label' => 'Implant Impression / Scan'],
                    ['key' => 'try_in',                 'label' => 'Crown Try-in'],
                    ['key' => 'cementation',            'label' => 'Crown Cementation / Screwing'],
                    ['key' => 'review',                 'label' => 'Annual Review'],
                ],
                'keywords'    => ['implant crown', 'crown on implant', 'implant cap', 'teeth on implant', 'implant loading', 'implant tooth', 'final implant crown'],
                'questions'   => ['When was the implant placed?', 'Any mobility of the implant?', 'Any pain or discomfort around the implant?', 'Confirmed osseointegration on last X-ray?'],
                'investigations' => ['IOPA — confirm osseointegration (no radiolucency around threads)', 'Implant stability check (resonance frequency if available)', 'Implant brand and size from chart'],
                'diagnoses'   => ['Osseointegrated implant — ready for crown loading'],
                'patient_instructions' => "Clean the implant crown with a soft brush and interdental brush daily.\nFloss or use a water flosser around the implant.\nAvoid biting extremely hard objects.\nReturn annually for an implant review and X-ray.",
                'sop' => [
                    'doctor_steps'    => ['Confirm osseointegration: IOPA, stability, no symptoms', 'Remove healing abutment', 'Place final abutment, torque to manufacturer spec (25–35 Ncm)', 'Take implant-level impression (open or closed tray) or intraoral scan', 'Record shade and occlusal clearance', 'Lab prescription: implant crown, abutment type, shade', 'Try-in: check fit, contacts, aesthetics', 'Screw-retained: torque crown screw, seal access hole with composite', 'Cement-retained: cement with low-retention cement, remove excess meticulously', 'Post-loading IOPA — confirm seating and no cement remnants'],
                    'assistant_steps' => ['Implant impression kit: pick-up copings, lab analogue, implant-specific trays', 'Torque wrench set to manufacturer spec', 'Lab form: implant brand, platform size, position, shade'],
                    'pre'  => "No special preparation. Ensure implant fixture has been in place for at least 3 months (mandible) or 6 months (maxilla).",
                    'post' => "Clean around the implant crown carefully twice daily.\nDo not use metal instruments near the implant.\nReturn in 1 month and then annually for review.",
                    'consent' => "An implant crown will be attached to the implant fixture. Risks include cement remnants causing peri-implantitis (if cement-retained), screw loosening, and crown fracture under heavy biting. Annual review is essential.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA to confirm osseointegration before loading',
                    'consent_required' => 'Written consent',
                    'lab_required'     => 'Custom abutment and crown by implant lab — 10–14 days',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 3],
                    'follow_up_days'   => ['days' => 30],
                ],
            ],

            // ── 7. FLAP SURGERY ───────────────────────────────────────────────
            [
                'category'    => 'Periodontics',
                'name'        => 'Flap Surgery',
                'code'        => 'PERIO-03',
                'description' => 'Modified Widman flap or access flap surgery for periodontal pockets ≥5mm that do not respond to SRP. Provides direct root debridement and pocket reduction.',
                'duration'    => 90,
                'price'       => ['default' => 15000, 'min' => 10000, 'max' => 22000],
                'color'       => '#1e40af',
                'specialty'   => 'periodontics',
                'sort_order'  => 8,
                'stages'      => [
                    ['key' => 'srp_baseline',  'label' => 'SRP Completed & Reassessed'],
                    ['key' => 'flap_surgery',  'label' => 'Flap Surgery (per quadrant)'],
                    ['key' => 'suture_removal','label' => 'Suture Removal (Day 7–10)'],
                    ['key' => 'reassessment',  'label' => 'Reassessment at 3 months'],
                    ['key' => 'maintenance',   'label' => 'Periodontal Maintenance'],
                ],
                'keywords'    => ['flap surgery', 'gum surgery', 'deep gum pockets', 'periodontal surgery', 'bone loss surgery', 'gum recession surgery', 'pocket reduction'],
                'questions'   => ['Has SRP been completed and reassessed?', 'Residual pockets ≥5mm post-SRP?', 'Furcation involvement?', 'Current smoking status?', 'Diabetes controlled?'],
                'investigations' => ['Full mouth periapicals or OPG', 'Periodontal chart post-SRP (6 weeks)', 'Blood sugar — must be controlled before surgery'],
                'diagnoses'   => ['Stage III/IV Periodontitis — residual pockets post-SRP', 'Furcation Class II/III involvement', 'Angular bone defects suitable for bone grafting'],
                'patient_instructions' => "Gum soreness and swelling for 5–7 days is expected.\nUse chlorhexidine rinse as prescribed for 2 weeks.\nSoft diet for 2 weeks. No smoking — critical for healing.\nReturn on Day 7–10 for suture removal.",
                'sop' => [
                    'doctor_steps'    => ['Confirm SRP done ≥6 weeks ago; residual pockets ≥5mm', 'Medical clearance if needed; blood sugar <200mg/dL', 'LA per surgical quadrant', 'Intra-sulcular incisions, crevicular flap', 'Reflect mucoperiosteal flap (full thickness)', 'Thorough root debridement with curettes under direct vision', 'Bone graft placement if angular defect present', 'Suture flap: interrupted or horizontal mattress sutures', 'Periodontal pack if needed', 'Prescribe antibiotics, CHX, analgesics'],
                    'assistant_steps' => ['Surgical perio tray: scalpel, periosteal elevator, curettes, bone graft material', 'Saline irrigation and suction throughout', 'Suture material: 3-0 / 4-0 vicryl'],
                    'pre'  => "Complete the full course of any prescribed antibiotics before surgery.\nNo smoking for at least 2 weeks before. Blood sugar must be controlled.",
                    'post' => "Rest for 24 hours. Apply ice pack on cheek for first day.\nTake all prescribed medications. CHX rinse twice daily.\nDo not brush the surgical area for 2 weeks — use CHX only.\nReturn Day 7–10 for suture removal.",
                    'consent' => "Flap surgery is performed to access and clean deep pockets that cannot be reached by scaling alone. Risks include post-operative pain, swelling, gum recession, tooth sensitivity, and infection. Smoking severely compromises healing and outcomes.",
                ],
                'rules' => [
                    'xray_required'    => 'Full mouth periapicals mandatory',
                    'consent_required' => 'Written surgical consent',
                    'anesthesia_required' => 'Local anaesthesia per quadrant',
                    'min_visits'       => ['count' => 3],
                    'max_visits'       => ['count' => 6],
                    'follow_up_days'   => ['days' => 7],
                    'medical_clearance'=> 'Required — blood sugar, anticoagulants, systemic conditions',
                ],
            ],

            // ── 8. METAL BRACES ───────────────────────────────────────────────
            [
                'category'    => 'Orthodontics',
                'name'        => 'Metal Braces',
                'code'        => 'ORTHO-02',
                'description' => 'Conventional fixed orthodontic appliance with stainless steel brackets and archwires for comprehensive correction of malocclusion. Full course with debond and retainer.',
                'duration'    => 30,
                'price'       => ['default' => 50000, 'min' => 35000, 'max' => 75000],
                'color'       => '#9d174d',
                'specialty'   => 'orthodontics',
                'sort_order'  => 9,
                'stages'      => [
                    ['key' => 'records',      'label' => 'Orthodontic Records'],
                    ['key' => 'planning',     'label' => 'Treatment Planning & Consent'],
                    ['key' => 'bonding',      'label' => 'Bracket Bonding'],
                    ['key' => 'archwire',     'label' => 'Progressive Archwire Changes (monthly)'],
                    ['key' => 'finishing',    'label' => 'Finishing & Detailing'],
                    ['key' => 'debond',       'label' => 'Debonding & Retainer'],
                    ['key' => 'retention',    'label' => 'Retention Review'],
                ],
                'keywords'    => ['braces', 'metal braces', 'crooked teeth', 'crowding', 'spacing', 'overbite', 'underbite', 'crossbite', 'gap teeth', 'misaligned teeth', 'teeth straightening', 'fixed braces'],
                'questions'   => ['Teeth alignment concern — crowding, spacing, or bite?', 'Age of patient?', 'Any extractions planned?', 'Clear aligners not preferred?', 'Any jaw pain or clicking?'],
                'investigations' => ['OPG', 'Lateral cephalogram', 'Intraoral and extraoral photos', 'Study models or digital scan', 'CBCT if skeletal discrepancy suspected'],
                'diagnoses'   => ['Class I crowding', 'Class II div 1 malocclusion', 'Class II div 2 malocclusion', 'Class III (mild — camouflage treatment)', 'Anterior open bite', 'Deep bite'],
                'patient_instructions' => "Brush teeth after every meal — food gets stuck around brackets.\nUse interdental brushes and a floss threader daily.\nAvoid hard, crunchy, and sticky foods — they break brackets.\nWear your rubber bands/elastics as instructed.\nWear your retainer lifelong after braces come off.",
                'sop' => [
                    'doctor_steps'    => ['Complete orthodontic assessment, records', 'Cephalometric analysis, treatment plan', 'Extract teeth if planned (refer or do in-house)', 'Bond brackets with adhesive — etch, prime, bond, light cure', 'Place initial NiTi archwire (0.014 / 0.016)', 'Monthly review: archwire changes, elastic forces, spring adjustments', 'Finishing archwires: stainless steel 0.019×0.025', 'IPR if needed', 'Debond: remove brackets, clean teeth, bond fixed retainer (lower anterior)', 'Take retainer impressions, deliver Hawley or Essix retainer'],
                    'assistant_steps' => ['Bonding tray: etchant, primer, bracket adhesive, bracket kit, ligatures', 'Record tooth measurements and bracket positions', 'Ortho check-up tray: cutters, bird beak pliers, archwire, ligatures'],
                    'pre'  => "Ensure oral hygiene is excellent before starting — no active caries or gum disease.\nAll cavities must be filled before bracket placement.",
                    'post' => "Soreness for 3–5 days after each archwire change is normal — soft diet.\nBrush after every meal. Use fluoride rinse nightly.\nFixed retainer bonded on back of lower front teeth after removal — keep it clean.",
                    'consent' => "Fixed braces treatment duration: 18–24 months typically. Risks: white spot lesions (decalcification) if oral hygiene is poor, root resorption (minor, usually not clinically significant), and relapse if retainer is not worn. A retainer must be worn lifelong.",
                ],
                'rules' => [
                    'xray_required'    => 'OPG + lateral ceph mandatory before start',
                    'consent_required' => 'Written consent including compliance and retainer requirements',
                    'min_visits'       => ['count' => 18],
                    'max_visits'       => ['count' => 30],
                    'follow_up_days'   => ['days' => 30],
                ],
            ],

            // ── 9. PARTIAL DENTURE ────────────────────────────────────────────
            [
                'category'    => 'Prosthodontics',
                'name'        => 'Partial Denture',
                'code'        => 'PROS-02',
                'description' => 'Removable partial denture — acrylic or cast metal (Co-Cr) framework with acrylic teeth replacing one or more missing teeth in an otherwise dentate arch.',
                'duration'    => 60,
                'price'       => ['default' => 14000, 'min' => 10000, 'max' => 22000],
                'color'       => '#475569',
                'specialty'   => 'prosthodontics',
                'sort_order'  => 10,
                'stages'      => [
                    ['key' => 'assessment',       'label' => 'Mouth Preparation & Assessment'],
                    ['key' => 'primary_impression','label' => 'Primary Impression'],
                    ['key' => 'special_tray',     'label' => 'Special Tray & Final Impression'],
                    ['key' => 'jaw_relation',     'label' => 'Jaw Relation Record'],
                    ['key' => 'try_in',           'label' => 'Framework & Teeth Try-in'],
                    ['key' => 'insertion',        'label' => 'Denture Insertion'],
                    ['key' => 'review',           'label' => 'Review & Adjustments'],
                ],
                'keywords'    => ['partial denture', 'partial plate', 'removable partial', 'missing teeth', 'few teeth missing', 'flippers', 'cast partial', 'cobalt chrome denture'],
                'questions'   => ['How many teeth are missing and where?', 'Are the remaining teeth healthy?', 'Previous partial denture experience?', 'Preference: acrylic or metal framework?', 'Implants considered and declined?'],
                'investigations' => ['OPG', 'Study models', 'IOPA of abutment teeth', 'Survey of cast if cast partial planned'],
                'diagnoses'   => ['Partially edentulous — multiple missing teeth', 'Patient declining implants or bridge'],
                'patient_instructions' => "Remove dentures after every meal and clean with a soft brush.\nDo not sleep with partial dentures (unless advised).\nSoak in denture cleanser overnight.\nReturn for adjustments — do not bend or adjust yourself.\nBring the denture to every dental appointment.",
                'sop' => [
                    'doctor_steps'    => ['Mouth preparation: extract hopeless teeth, restore abutments', 'Survey abutment teeth for undercuts', 'Primary impression → study cast → survey', 'Design framework (rest seats, clasps, connector)', 'Special tray final impression', 'Jaw relation + tooth selection', 'Metal framework try-in: check fit, stability, occlusion', 'Teeth try-in: check aesthetics and occlusion', 'Lab: process acrylic onto framework', 'Insertion: check fit, pressure points, occlusion', 'Review at 48 hours and 1 week'],
                    'assistant_steps' => ['Survey equipment if casting in-house', 'Lab form: arch, framework design, tooth shade, mould', 'Pressure indicating paste for adjustment at insertion'],
                    'pre'  => "All remaining teeth should be cleaned and any urgent treatment completed.",
                    'post' => "Wear the denture as much as comfortable initially.\nExpect soreness at pressure areas — return for adjustment within 2–3 days.\nClean with soft brush and mild soap or denture cleaner daily.",
                    'consent' => "A removable partial denture replaces missing teeth. It requires adjustments after insertion. Cast metal (Co-Cr) is stronger and better fitting than acrylic. The denture may need to be relined or remade over years as the jaw changes.",
                ],
                'rules' => [
                    'xray_required'    => 'OPG + IOPAs of abutment teeth',
                    'consent_required' => 'Written consent',
                    'lab_required'     => 'Framework and denture fabrication by lab',
                    'min_visits'       => ['count' => 5],
                    'max_visits'       => ['count' => 8],
                    'follow_up_days'   => ['days' => 3],
                ],
            ],

            // ── 10. VENEERS ───────────────────────────────────────────────────
            [
                'category'    => 'Cosmetic Dentistry',
                'name'        => 'Veneers',
                'code'        => 'COS-02',
                'description' => 'Thin porcelain or composite laminate bonded to the labial surface of anterior teeth for aesthetic improvement — shape, colour, and minor alignment.',
                'duration'    => 60,
                'price'       => ['default' => 18000, 'min' => 12000, 'max' => 28000],
                'color'       => '#a16207',
                'specialty'   => 'smile_design',
                'sort_order'  => 11,
                'stages'      => [
                    ['key' => 'smile_design', 'label' => 'Digital Smile Design'],
                    ['key' => 'mockup',       'label' => 'Mockup Try-in'],
                    ['key' => 'prep',         'label' => 'Tooth Preparation (minimal)'],
                    ['key' => 'impression',   'label' => 'Impression / Scan'],
                    ['key' => 'try_in',       'label' => 'Veneer Try-in'],
                    ['key' => 'bonding',      'label' => 'Adhesive Bonding'],
                ],
                'keywords'    => ['veneer', 'veneers', 'porcelain veneer', 'composite veneer', 'front teeth improvement', 'smile makeover', 'chipped front teeth', 'stained front teeth', 'shape teeth', 'discoloured front teeth'],
                'questions'   => ['Which teeth bother you most?', 'Concern: colour, shape, or both?', 'Any bruxism or clenching?', 'Existing crowns or fillings on these teeth?', 'Have you seen a DSD mockup?'],
                'investigations' => ['Clinical photographs (smile + retracted)', 'Digital Smile Design mockup', 'Study models', 'IOPA of affected teeth', 'Shade mapping'],
                'diagnoses'   => ['Extrinsic/intrinsic staining — veneer candidate', 'Chipped or worn anterior teeth', 'Minor spacing or malposition — cosmetic correction'],
                'patient_instructions' => "Veneers are fragile — do not bite nails, pens, or hard food with front teeth.\nWear a night guard if bruxism is present.\nMaintain oral hygiene at the veneer margins.\nReturn if a veneer chips or debonds.",
                'sop' => [
                    'doctor_steps'    => ['DSD planning, mockup fabrication and try-in with patient approval', 'Minimal prep (0.3–0.5mm labial reduction) or no-prep if applicable', 'Impression or digital scan', 'Provisional veneers placed', 'Lab: pressed or stacked Emax or feldspathic porcelain', 'Try-in with water-soluble paste — shade and shape approval', 'Etch tooth 30 sec, silane porcelain, adhesive, resin cement', 'Light cure, remove excess cement under magnification', 'Polish margins'],
                    'assistant_steps' => ['DSD software photos taken — correct focal length + lighting', 'Provisional material (Protemp or PMMA) ready', 'Resin cement: Variolink / RelyX Veneer, shade matched'],
                    'pre'  => "No special preparation. Ensure patient approves DSD mockup before committing to preparation.",
                    'post' => "Avoid biting hard objects with front teeth. Wear night guard if bruxism.\nReturn immediately if veneer chips, debonds, or gum irritation develops.",
                    'consent' => "Veneers require irreversible preparation of tooth enamel (even if minimal). They can debond or chip. Bruxism significantly reduces longevity. Results are highly technique- and lab-dependent. Veneers cannot be repaired — must be replaced if they fail.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA of each tooth to be veneered',
                    'consent_required' => 'Written consent — irreversible even for minimal prep',
                    'lab_required'     => 'Pressed porcelain — specialist lab 10–14 days',
                    'min_visits'       => ['count' => 3],
                    'max_visits'       => ['count' => 4],
                    'follow_up_days'   => ['days' => 30],
                ],
            ],

            // ── 11. SMILE MAKEOVER ────────────────────────────────────────────
            [
                'category'    => 'Cosmetic Dentistry',
                'name'        => 'Smile Makeover',
                'code'        => 'COS-03',
                'description' => 'Comprehensive aesthetic treatment plan combining multiple procedures (whitening, veneers, composite bonding, crowns, gum contouring) for a full smile transformation.',
                'duration'    => 60,
                'price'       => ['default' => 5000, 'min' => 2000, 'max' => 8000],
                'color'       => '#854d0e',
                'specialty'   => 'smile_design',
                'sort_order'  => 12,
                'stages'      => [
                    ['key' => 'photos_records', 'label' => 'Photographs & Records'],
                    ['key' => 'dsd',            'label' => 'Digital Smile Design'],
                    ['key' => 'mockup',         'label' => 'Wax Mockup / Intraoral Mockup'],
                    ['key' => 'patient_approval','label' => 'Patient Design Approval'],
                    ['key' => 'treatment',      'label' => 'Treatment Execution (multiple visits)'],
                    ['key' => 'review',         'label' => 'Final Review & Handover'],
                ],
                'keywords'    => ['smile makeover', 'full smile', 'smile transformation', 'complete smile design', 'smile design', 'cosmetic dentistry', 'improve my smile', 'complete mouth treatment', 'smile redesign'],
                'questions'   => ['What do you dislike most about your smile?', 'Is it colour, shape, position, or gums?', 'Do you have a target look or celebrity smile reference?', 'What is your budget range?', 'Timeline — any special event coming up?'],
                'investigations' => ['Full smile photos: frontal smile, retracted, lateral, lip line', 'Digital Smile Design (DSD)', 'OPG + full mouth X-rays', 'Periapical check all anterior teeth', 'Study models or digital scan', 'Facebow transfer if full rehabilitation'],
                'diagnoses'   => ['Multiple aesthetic concerns — combined treatment plan required', 'Smile design consultation'],
                'patient_instructions' => "A smile makeover involves multiple appointments over weeks to months.\nFollow individual treatment instructions for each procedure.\nMaintain excellent oral hygiene throughout.\nWear retainers and night guards as advised.",
                'sop' => [
                    'doctor_steps'    => ['Full aesthetic assessment: shade, tooth form, gingival levels, midline, lip support', 'Standardised clinical photographs', 'Digital Smile Design — present to patient digitally', 'Wax-up and intraoral mockup for approval', 'Sequence treatment plan: periodontal → restorative → prosthetic → cosmetic', 'Execute procedures per treatment plan in sequence', 'Final review with photographs — compare before and after'],
                    'assistant_steps' => ['Photo setup: DSLR with macro lens, ring flash, cheek retractors, black background', 'DSD software or PowerPoint for presentation', 'Ensure all treatment appointment bookings are pre-scheduled'],
                    'pre'  => "Resolve all active dental disease (caries, gum disease) before starting cosmetic work.",
                    'post' => "Maintenance is key — regular 6-month hygiene visits.\nWear night guard if bruxism present.\nAvoid staining foods and drinks after whitening phases.",
                    'consent' => "A smile makeover involves multiple irreversible procedures. Risks are procedure-specific (listed per treatment). Costs vary based on final plan. Treatment can take 3–6 months. Results are long-term but require maintenance.",
                ],
                'rules' => [
                    'consent_required' => 'Written consent after DSD approval',
                    'xray_required'    => 'Full mouth X-rays before planning',
                    'min_visits'       => ['count' => 6],
                    'max_visits'       => ['count' => 20],
                    'follow_up_days'   => ['days' => 180],
                ],
            ],

            // ── 12. PULPOTOMY (PEDIATRIC) ─────────────────────────────────────
            [
                'category'    => 'Pediatric Dentistry',
                'name'        => 'Pulpotomy',
                'code'        => 'PED-01',
                'description' => 'Removal of the coronal pulp of a primary molar while preserving radicular pulp vitality. Treated with Ferric Sulphate / MTA / Biodentine followed by stainless steel crown.',
                'duration'    => 45,
                'price'       => ['default' => 3500, 'min' => 2500, 'max' => 6000],
                'color'       => '#0d9488',
                'specialty'   => 'pediatric',
                'sort_order'  => 13,
                'stages'      => [
                    ['key' => 'diagnosis',   'label' => 'Diagnosis & X-ray'],
                    ['key' => 'pulpotomy',   'label' => 'Pulpotomy Procedure'],
                    ['key' => 'ssc',         'label' => 'Stainless Steel Crown'],
                    ['key' => 'review',      'label' => 'Review at 6 months'],
                ],
                'keywords'    => ['milk tooth pain', 'baby tooth cavity', 'child tooth pain', 'kids rct', 'pulpotomy', 'child cavity treatment', 'baby molar pain', 'primary tooth nerve', 'deciduous tooth infection'],
                'questions'   => ['How old is the child?', 'Is the tooth a primary (milk) or permanent tooth?', 'How long has the pain been there?', 'Any swelling or abscess?', 'Is the child cooperative or anxious?'],
                'investigations' => ['IOPA — root resorption, furcation involvement', 'Clinical assessment: caries extent, abscess, mobility'],
                'diagnoses'   => ['Carious primary molar with reversible pulpitis — pulpotomy indicated', 'Deep caries approaching pulp in primary molar'],
                'patient_instructions' => "Avoid hard food on the treated side for 24 hours.\nThe stainless steel crown protects the tooth until it naturally falls out.\nContinue regular brushing and dental visits.\nBring the child for review in 6 months.",
                'sop' => [
                    'doctor_steps'    => ['Review IOPA — no root resorption, no furcation involvement', 'LA (if cooperative) or inhalation sedation', 'Remove caries, expose pulp chamber', 'Amputate coronal pulp with slow-speed bur', 'Haemostasis: Ferric Sulphate or MTA pellet', 'Confirm haemostasis in 1 minute', 'Apply Ferric Sulphate / MTA / Biodentine over radicular pulp stumps', 'GIC base over medicament', 'Stainless steel crown: size, cement with GIC', 'Post-op IOPA', 'Review at 6 months'],
                    'assistant_steps' => ['Paediatric tray: small burs, rubber dam, stainless steel crown kit, GIC', 'Ferric Sulphate on cotton pellet ready', 'SSC sizing set available'],
                    'pre'  => "Explain the procedure to the child in simple words.\nBring the child calm and not hungry. Behaviour management starts in the waiting room.",
                    'post' => "Gum soreness for a day is normal. Avoid hard food.\nThe silver crown is temporary — it will fall out when the milk tooth falls out naturally.",
                    'consent' => "The nerve of the milk tooth will be partially removed and the tooth protected with a silver crown. This saves the tooth until the permanent tooth erupts. Risks include failure requiring extraction if infection spreads to roots.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA before and after procedure mandatory',
                    'consent_required' => 'Written parent/guardian consent',
                    'anesthesia_required' => 'Local anaesthesia + behaviour management',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 2],
                    'follow_up_days'   => ['days' => 180],
                ],
            ],

            // ── 13. STAINLESS STEEL CROWN (PEDIATRIC) ────────────────────────
            [
                'category'    => 'Pediatric Dentistry',
                'name'        => 'Stainless Steel Crown',
                'code'        => 'PED-02',
                'description' => 'Pre-formed stainless steel crown for primary molars after pulpotomy, extensive caries, or hypomineralisation. Full coronal protection until natural exfoliation.',
                'duration'    => 30,
                'price'       => ['default' => 2500, 'min' => 1800, 'max' => 4500],
                'color'       => '#0f766e',
                'specialty'   => 'pediatric',
                'sort_order'  => 14,
                'stages'      => [
                    ['key' => 'preparation', 'label' => 'Crown Preparation & Sizing'],
                    ['key' => 'cementation', 'label' => 'Crown Cementation'],
                    ['key' => 'review',      'label' => 'Review'],
                ],
                'keywords'    => ['stainless steel crown', 'ssc', 'silver crown baby tooth', 'kids silver cap', 'milk tooth cap', 'hall crown', 'pediatric crown', 'primary molar crown'],
                'questions'   => ['Is this being placed after pulpotomy or for caries alone?', 'How old is the child?', 'Any allergies to metals?'],
                'investigations' => ['IOPA — root status, bone', 'Confirm no root resorption before placing SSC'],
                'diagnoses'   => ['Post-pulpotomy primary molar requiring crown', 'Extensively carious primary molar', 'Hypomineralised primary molar (MIH)'],
                'patient_instructions' => "The silver crown is temporary and will come out when the milk tooth falls out.\nBrush it normally.\nReturn if crown comes off.",
                'sop' => [
                    'doctor_steps'    => ['Select SSC size: try-in on tooth', 'Minimal tooth reduction (Hall technique: no prep; or conventional: 1mm interproximal slices)', 'Crimp and adapt crown to fit', 'Cement with GIC luting cement', 'Remove excess cement, check occlusion', 'Post-cementation IOPA'],
                    'assistant_steps' => ['SSC sizing tray: 2–8 sizes per molar per arch', 'GIC luting cement mixed to correct consistency', 'Crimp pliers available'],
                    'pre'  => "No special preparation.",
                    'post' => "Gum around the crown may be sore for 1–2 days. This is normal.\nBrush normally. The crown is temporary and will not interfere with the permanent tooth.",
                    'consent' => "A silver crown will protect the milk tooth until it falls out naturally. It is the most durable option for primary teeth.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA before placement',
                    'consent_required' => 'Parent/guardian consent',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 1],
                ],
            ],

            // ── 14. EMERGENCY PAIN MANAGEMENT ────────────────────────────────
            [
                'category'    => 'Emergency',
                'name'        => 'Emergency Pain Management',
                'code'        => 'EMG-01',
                'description' => 'Immediate assessment and pain relief for acute dental pain — includes diagnosis, emergency pulpotomy / access opening / temporary dressing / analgesic prescription as appropriate.',
                'duration'    => 30,
                'price'       => ['default' => 1000, 'min' => 500, 'max' => 2000],
                'color'       => '#ef4444',
                'specialty'   => 'endodontics',
                'sort_order'  => 15,
                'stages'      => [
                    ['key' => 'triage',       'label' => 'Triage & Rapid Assessment'],
                    ['key' => 'emergency_tx', 'label' => 'Emergency Treatment'],
                    ['key' => 'definitive_plan', 'label' => 'Book Definitive Treatment'],
                ],
                'keywords'    => ['emergency', 'severe pain', 'can\'t bear pain', 'urgent tooth pain', 'toothache emergency', 'pain killers not working', 'extreme tooth pain', 'need help now', 'worst pain', 'crying from pain'],
                'questions'   => ['Which tooth is hurting?', 'Severity on 1–10 scale?', 'Any swelling, fever, or difficulty swallowing?', 'Medications taken already?', 'Any known medical conditions?'],
                'investigations' => ['IOPA (rapid)', 'Percussion, palpation, cold test', 'Facial swelling assessment'],
                'diagnoses'   => ['Acute irreversible pulpitis', 'Acute apical abscess', 'Acute pericoronitis', 'Dentine hypersensitivity — severe episode'],
                'patient_instructions' => "Take prescribed analgesics on time — do not wait for pain to become severe.\nTake antibiotics if prescribed — complete the full course.\nAvoid very hot or cold food.\nReturn for definitive treatment at the next appointment.",
                'sop' => [
                    'doctor_steps'    => ['Rapid IOPA, percussion, palpation', 'Identify emergency cause — pulpal or periapical', 'Administer LA', 'Access opening / pulp extirpation if irreversible pulpitis', 'Drain abscess if fluctuant', 'Place calcium hydroxide dressing, close with Cavit/IRM', 'Prescribe analgesics (Ibuprofen 400mg + Paracetamol 500mg alternate) and antibiotics if indicated', 'Book definitive appointment within 3–5 days'],
                    'assistant_steps' => ['Emergency tray ready at all times: round bur, Cavit, cotton pellet, LA', 'IOPA machine primed', 'Prescription pad ready'],
                    'pre'  => "Walk-in welcome. No prior appointment needed for emergencies.",
                    'post' => "Take medications as prescribed.\nDo not eat on the treated side.\nBook your follow-up appointment before leaving the clinic.",
                    'consent' => "Emergency treatment provides immediate pain relief. It is NOT the final treatment. Definitive treatment (full RCT, extraction, etc.) must be completed at the next appointment to prevent recurrence.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA if clinically possible — rapid assessment',
                    'anesthesia_required' => 'Local anaesthesia as needed',
                    'min_visits'       => ['count' => 1],
                    'max_visits'       => ['count' => 2],
                    'follow_up_days'   => ['days' => 3],
                ],
            ],

            // ── 15. DENTAL ABSCESS MANAGEMENT ────────────────────────────────
            [
                'category'    => 'Emergency',
                'name'        => 'Dental Abscess Management',
                'code'        => 'EMG-02',
                'description' => 'Management of acute dentoalveolar abscess — incision and drainage, antibiotics, and referral if systemic spread suspected.',
                'duration'    => 45,
                'price'       => ['default' => 2500, 'min' => 1500, 'max' => 5000],
                'color'       => '#dc2626',
                'specialty'   => 'oral_surgery',
                'sort_order'  => 16,
                'stages'      => [
                    ['key' => 'assessment',  'label' => 'Assessment & Red Flag Screen'],
                    ['key' => 'inad',        'label' => 'Incision & Drainage (if fluctuant)'],
                    ['key' => 'antibiotics', 'label' => 'Antibiotic Prescription'],
                    ['key' => 'definitive',  'label' => 'Definitive Treatment (RCT or Extraction)'],
                ],
                'keywords'    => ['abscess', 'swollen face', 'gum boil', 'pus in mouth', 'swelling in jaw', 'facial swelling', 'pus from tooth', 'tooth abscess', 'infected tooth', 'fever from tooth', 'cheek swollen tooth'],
                'questions'   => ['How long has the swelling been present?', 'Is the swelling growing rapidly?', 'Any fever, difficulty swallowing, or trismus?', 'Any difficulty breathing?', 'Diabetes, immunosuppression, or cardiac conditions?'],
                'investigations' => ['IOPA', 'Facial swelling assessment — fluctuant vs diffuse', 'Temperature, BP', 'Blood sugar', 'CBC + CRP if systemic spread suspected'],
                'diagnoses'   => ['Acute dentoalveolar abscess', 'Cellulitis', 'Fascial space infection — refer'],
                'patient_instructions' => "Complete the full antibiotic and analgesic course.\nIf swelling increases, spreads to eye or neck, or breathing becomes difficult — go to emergency hospital immediately.\nBook definitive treatment (RCT or extraction) within 3–5 days.",
                'sop' => [
                    'doctor_steps'    => ['Screen red flags: airway, trismus, Ludwig\'s angina, rapid spread', 'Refer to hospital IMMEDIATELY if any red flags present', 'If localised fluctuant swelling: IANB/infiltration LA', 'Incision into most dependent point of fluctuant area', 'Blunt dissection, express pus, irrigate with saline', 'Place corrugated rubber drain if needed', 'Prescribe Amoxicillin 500mg TDS + Metronidazole 400mg TDS × 5 days', 'Analgesics: Ibuprofen 400mg alternate with Paracetamol', 'Book RCT or extraction appointment once swelling resolves', 'IOPA post-drainage'],
                    'assistant_steps' => ['Drainage tray: scalpel no.15, haemostat, rubber drain, saline irrigation', 'IOPA ready', 'Antibiotic prescription form ready'],
                    'pre'  => "Emergency — no preparation needed. Assess immediately on arrival.",
                    'post' => "Complete the antibiotic course — do not stop early.\nRinse with warm salt water 3× daily.\nReturn in 24–48 hours if swelling does not reduce.\nGo to hospital emergency if swelling spreads to throat or eye.",
                    'consent' => "Abscess drainage provides immediate relief. The causative tooth must be definitively treated (RCT or extraction) once the infection resolves. Incomplete treatment leads to recurrence. Spreading infections are life-threatening.",
                ],
                'rules' => [
                    'xray_required'    => 'IOPA mandatory',
                    'consent_required' => 'Verbal consent for emergency; written for drainage procedure',
                    'anesthesia_required' => 'Local anaesthesia for incision and drainage',
                    'min_visits'       => ['count' => 2],
                    'max_visits'       => ['count' => 4],
                    'follow_up_days'   => ['days' => 2],
                    'medical_clearance'=> 'Systemic spread, immunosuppression, or uncontrolled diabetes requires hospital management',
                ],
            ],

            // ── 16. GENERAL DENTAL CONSULTATION ──────────────────────────────
            [
                'category'    => 'Consultation',
                'name'        => 'General Dental Consultation',
                'code'        => 'CONS-01',
                'description' => 'Comprehensive dental examination — clinical, radiographic, and risk assessment — followed by a personalised treatment plan.',
                'duration'    => 30,
                'price'       => ['default' => 500, 'min' => 300, 'max' => 1500],
                'color'       => '#64748b',
                'specialty'   => 'general',
                'sort_order'  => 17,
                'stages'      => [
                    ['key' => 'history',      'label' => 'History Taking'],
                    ['key' => 'examination',  'label' => 'Clinical Examination'],
                    ['key' => 'investigation','label' => 'Investigations (if needed)'],
                    ['key' => 'diagnosis',    'label' => 'Diagnosis & Treatment Plan'],
                    ['key' => 'counselling',  'label' => 'Patient Counselling'],
                ],
                'keywords'    => ['consultation', 'checkup', 'check up', 'general checkup', 'teeth check', 'dental examination', 'first visit', 'new patient', 'routine visit', 'what is wrong with my teeth', 'overall dental health'],
                'questions'   => ['What brings you in today?', 'Last dental visit — when and what was done?', 'Any pain, sensitivity, or bleeding gums?', 'Any medical conditions or regular medications?', 'Any cosmetic concerns about your smile?'],
                'investigations' => ['IOPA (symptomatic teeth)', 'OPG (new patient or comprehensive exam)', 'BPE chart', 'Intraoral photographs', 'Bite-wing X-rays (caries screening)'],
                'diagnoses'   => ['Caries risk assessment', 'Periodontal health status', 'Orthodontic assessment', 'Prosthetic needs', 'Cosmetic concerns identified'],
                'patient_instructions' => "Bring any previous dental records, X-rays, or reports.\nInform the doctor of all medical conditions and medications.\nThis consultation results in a personalised treatment plan — we will explain the priorities and costs.",
                'sop' => [
                    'doctor_steps'    => ['Complete medical and dental history', 'Extra-oral examination: TMJ, lymph nodes, facial symmetry', 'Intra-oral: soft tissues, mucosa, tongue, palate', 'Dental charting: caries, restorations, missing, mobility', 'BPE periodontal screening', 'Occlusal assessment', 'Radiographic assessment if indicated', 'Summarise findings, explain to patient', 'Provide written treatment plan with priorities and costs', 'OHI and preventive advice'],
                    'assistant_steps' => ['New patient form + medical history form ready', 'Charting tray: probe, mirror, explorer, CPITN probe', 'Intraoral camera charged', 'OPG if needed'],
                    'pre'  => "Fill in the new patient form on arrival.\nBring any previous dental X-rays or records.",
                    'post' => "You will receive a written treatment plan.\nPrioritise treatment based on pain, infection, and function first.\nCosmetic and elective treatment can follow.",
                    'consent' => "This is a comprehensive dental examination. X-rays may be taken if clinically indicated. Findings will be explained and a treatment plan provided.",
                ],
                'rules' => [
                    'min_visits'     => ['count' => 1],
                    'max_visits'     => ['count' => 1],
                    'follow_up_days' => ['days' => 180],
                ],
            ],

        ]; // end $treatments array

        // ── Persist ───────────────────────────────────────────────────────────
        foreach ($treatments as $data) {
            $cat = $cats[$data['category']];

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

            // SOP
            TreatmentSop::updateOrCreate(
                ['treatment_id' => $treatment->id, 'version' => 1],
                [
                    'status'            => 'active',
                    'version'           => 1,
                    'doctor_steps'      => $data['sop']['doctor_steps'],
                    'assistant_steps'   => $data['sop']['assistant_steps'],
                    'pre_instructions'  => $data['sop']['pre'],
                    'post_instructions' => $data['sop']['post'],
                    'consent_notes'     => $data['sop']['consent'],
                ]
            );

            // Rules
            foreach ($data['rules'] as $ruleType => $ruleValue) {
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
        $this->command->info('  DentalTreatmentsMasterSeeder2 complete — ' . count($treatments) . ' treatments seeded');
        $this->command->info('  Total in system: 30 treatments across 12 categories');
        $this->command->info('  Pricing: Tier 1 Indian cities (Mumbai / Delhi / Bengaluru)');
        $this->command->info('══════════════════════════════════════════════════════════');
    }
}
