<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TreatmentKnowledge;

/**
 * P2C3 — TreatmentKnowledgeSeeder
 *
 * Populates the treatment_knowledge table with 5 core dental specialties.
 * Run: php artisan db:seed --class=TreatmentKnowledgeSeeder
 *
 * Safe to re-run: uses updateOrCreate on specialty_tag.
 */
class TreatmentKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [
            // ── 1. Orthodontics ────────────────────────────────────────────────
            [
                'specialty_tag'  => 'orthodontics',
                'display_label'  => 'Orthodontics',
                'display_icon'   => 'tooth',
                'sort_order'     => 1,
                'trigger_keywords' => [
                    'brace', 'braces', 'aligner', 'aligners', 'crooked',
                    'crowding', 'spacing', 'overjet', 'overbite', 'underbite',
                    'crossbite', 'gap', 'gaps', 'straight', 'straighten',
                    'misaligned', 'protruding', 'teeth alignment',
                ],
                'patient_concerns' => ['cosmetic', 'functional'],
                'suggested_questions' => [
                    'How long have you noticed the crowding or spacing?',
                    'Have you worn braces or aligners before?',
                    'Do you experience any jaw pain or clicking sounds?',
                    'Are you concerned primarily about appearance, function, or both?',
                    'Any difficulty biting or chewing?',
                ],
                'suggested_findings' => [
                    'Crowding severity (mild/moderate/severe)',
                    'Overjet measurement',
                    'Overbite percentage',
                    'Midline shift',
                    'Arch form assessment',
                ],
                'suggested_investigations' => [
                    'OPG', 'Lateral ceph', 'Study models', 'Intraoral photos', 'CBCT (if skeletal)',
                ],
                'possible_diagnoses' => [
                    'Class I malocclusion with crowding',
                    'Class II div 1 malocclusion',
                    'Class II div 2 malocclusion',
                    'Class III malocclusion',
                    'Anterior open bite',
                    'Posterior crossbite',
                    'Spacing / diastema',
                ],
                'module_config' => [
                    ['label' => 'Crowding', 'name' => 'ortho_crowding', 'type' => 'select',
                     'options' => ['None', 'Mild', 'Moderate', 'Severe']],
                    ['label' => 'Overjet (mm)', 'name' => 'ortho_overjet', 'type' => 'number'],
                    ['label' => 'Overbite (%)', 'name' => 'ortho_overbite', 'type' => 'number'],
                    ['label' => 'Skeletal Class', 'name' => 'ortho_skeletal_class', 'type' => 'select',
                     'options' => ['I', 'II', 'III', 'Mixed']],
                    ['label' => 'Treatment preference', 'name' => 'ortho_preference', 'type' => 'select',
                     'options' => ['Fixed braces', 'Clear aligners', 'No preference']],
                ],
            ],

            // ── 2. Periodontics ────────────────────────────────────────────────
            [
                'specialty_tag'  => 'periodontics',
                'display_label'  => 'Periodontics',
                'display_icon'   => 'gums',
                'sort_order'     => 2,
                'trigger_keywords' => [
                    'gum', 'gums', 'bleed', 'bleeding', 'calculus', 'tartar',
                    'loose', 'mobile', 'mobility', 'scale', 'scaling',
                    'gum disease', 'periodontitis', 'gingivitis', 'recession',
                    'pocket', 'pus', 'bad breath', 'halitosis', 'swollen gum',
                ],
                'patient_concerns' => ['pain', 'functional', 'cosmetic'],
                'suggested_questions' => [
                    'How long have your gums been bleeding?',
                    'Do you smoke or use tobacco?',
                    'Is there any family history of gum disease?',
                    'Have you had scaling done before? When was the last time?',
                    'Do you notice any bad breath or a bad taste?',
                ],
                'suggested_findings' => [
                    'BPE score per sextant',
                    'Probing depths',
                    'Gingival recession measurements',
                    'Furcation involvement',
                    'Tooth mobility grade',
                ],
                'suggested_investigations' => [
                    'IOPA (key teeth)', 'Full mouth periapicals', 'OPG', 'BPE chart',
                    'Blood sugar (if diabetic risk)', 'CBC (if systemic concern)',
                ],
                'possible_diagnoses' => [
                    'Gingivitis',
                    'Stage I Periodontitis',
                    'Stage II Periodontitis',
                    'Stage III Periodontitis',
                    'Stage IV Periodontitis',
                    'Necrotising gingivitis',
                    'Drug-induced gingival enlargement',
                ],
                'module_config' => [
                    ['label' => 'BPE Score', 'name' => 'perio_bpe', 'type' => 'text',
                     'placeholder' => 'e.g. 3|3|4|4|3|3'],
                    ['label' => 'Worst pocket depth (mm)', 'name' => 'perio_pocket_depth', 'type' => 'number'],
                    ['label' => 'Furcation', 'name' => 'perio_furcation', 'type' => 'select',
                     'options' => ['None', 'Class I', 'Class II', 'Class III']],
                    ['label' => 'Mobility', 'name' => 'perio_mobility', 'type' => 'select',
                     'options' => ['None', 'Grade I', 'Grade II', 'Grade III']],
                    ['label' => 'Smoking status', 'name' => 'perio_smoking', 'type' => 'select',
                     'options' => ['Non-smoker', 'Ex-smoker', 'Current smoker']],
                ],
            ],

            // ── 3. Endodontics ─────────────────────────────────────────────────
            [
                'specialty_tag'  => 'endodontics',
                'display_label'  => 'Endodontics',
                'display_icon'   => 'nerve',
                'sort_order'     => 3,
                'trigger_keywords' => [
                    'pain', 'ache', 'aching', 'sensitive', 'sensitivity',
                    'hot', 'cold', 'nerve', 'rct', 'root canal', 'root treatment',
                    'decay', 'cavity', 'cavities', 'throbbing', 'abscess',
                    'swelling', 'swollen', 'pus', 'tender', 'spontaneous pain',
                    'pulp', 'infection', 'toothache',
                ],
                'patient_concerns' => ['pain'],
                'suggested_questions' => [
                    'Is the pain spontaneous or does something trigger it?',
                    'Does cold or hot make the pain worse? How long does it linger?',
                    'Is there any swelling near the tooth?',
                    'Have you taken antibiotics or painkillers for this?',
                    'Is the pain keeping you awake at night?',
                ],
                'suggested_findings' => [
                    'Thermal sensitivity (cold/hot)',
                    'Percussion sensitivity',
                    'Palpation of apex',
                    'Sinus tract / fistula',
                    'Extent of caries',
                    'Restorations present',
                ],
                'suggested_investigations' => [
                    'IOPA', 'OPG', 'Electric pulp test', 'Cold test', 'CBCT (if complex anatomy)',
                ],
                'possible_diagnoses' => [
                    'Normal pulp',
                    'Reversible pulpitis',
                    'Symptomatic irreversible pulpitis',
                    'Asymptomatic irreversible pulpitis',
                    'Necrotic pulp',
                    'Previously treated',
                    'Acute apical abscess',
                    'Chronic apical periodontitis',
                ],
                'module_config' => [
                    ['label' => 'Affected tooth', 'name' => 'endo_tooth', 'type' => 'text',
                     'placeholder' => 'FDI number e.g. 16'],
                    ['label' => 'Pain type', 'name' => 'endo_pain_type', 'type' => 'select',
                     'options' => ['Spontaneous', 'Provoked', 'Both', 'None currently']],
                    ['label' => 'Thermal response', 'name' => 'endo_thermal', 'type' => 'select',
                     'options' => ['Normal', 'Hypersensitive', 'Lingering >30s', 'No response']],
                    ['label' => 'Percussion', 'name' => 'endo_percussion', 'type' => 'select',
                     'options' => ['Negative', 'Mildly tender', 'Severely tender']],
                    ['label' => 'Swelling present', 'name' => 'endo_swelling', 'type' => 'select',
                     'options' => ['None', 'Localised', 'Diffuse', 'Facial']],
                ],
            ],

            // ── 4. Smile Design ────────────────────────────────────────────────
            [
                'specialty_tag'  => 'smile_design',
                'display_label'  => 'Smile Design',
                'display_icon'   => 'sparkles',
                'sort_order'     => 4,
                'trigger_keywords' => [
                    'smile', 'whiten', 'whitening', 'white', 'colour', 'color',
                    'stain', 'stains', 'stained', 'cosmetic', 'veneer', 'veneers',
                    'aesthetic', 'aesthetics', 'shade', 'discolour', 'discoloration',
                    'bonding', 'composite', 'makeover', 'reshape', 'gummy smile',
                ],
                'patient_concerns' => ['cosmetic'],
                'suggested_questions' => [
                    'What bothers you most about your smile?',
                    'Have you tried any whitening treatments before?',
                    'Are you looking for a conservative fix or a full smile makeover?',
                    'Any concerns about the shape or size of your teeth?',
                    'Do you have upcoming events (wedding, interview) with a target date?',
                ],
                'suggested_findings' => [
                    'Shade assessment (VITA scale)',
                    'Gingival display on smile',
                    'Lip line high/medium/low',
                    'Tooth proportions',
                    'Midline alignment',
                    'Existing restorations',
                ],
                'suggested_investigations' => [
                    'Clinical photos (smile, retracted, lateral)',
                    'Shade mapping photos',
                    'Study models',
                    'Digital smile design mockup',
                ],
                'possible_diagnoses' => [
                    'Extrinsic staining',
                    'Intrinsic discolouration',
                    'Fluorosis',
                    'Tetracycline staining',
                    'Gummy smile (altered passive eruption)',
                    'Worn dentition',
                    'Diastema',
                ],
                'module_config' => [
                    ['label' => 'Current shade', 'name' => 'sd_current_shade', 'type' => 'text',
                     'placeholder' => 'e.g. A3'],
                    ['label' => 'Target shade', 'name' => 'sd_target_shade', 'type' => 'text',
                     'placeholder' => 'e.g. A1'],
                    ['label' => 'Gingival display on smile', 'name' => 'sd_gum_display', 'type' => 'select',
                     'options' => ['None', '<2mm', '2-4mm (gummy)', '>4mm']],
                    ['label' => 'Treatment interest', 'name' => 'sd_treatment', 'type' => 'select',
                     'options' => ['Whitening only', 'Veneers', 'Composite bonding', 'Full makeover', 'Not decided']],
                ],
            ],

            // ── 5. Prosthodontics ──────────────────────────────────────────────
            [
                'specialty_tag'  => 'prosthodontics',
                'display_label'  => 'Prosthodontics',
                'display_icon'   => 'crown',
                'sort_order'     => 5,
                'trigger_keywords' => [
                    'missing', 'implant', 'implants', 'bridge', 'crown', 'crowns',
                    'denture', 'dentures', 'replace', 'replacement', 'extracted',
                    'gap', 'gaps', 'edentulous', 'partial denture', 'full denture',
                    'removable', 'fixed prosthesis', 'broken tooth', 'fractured',
                ],
                'patient_concerns' => ['functional', 'cosmetic'],
                'suggested_questions' => [
                    'How long has the tooth been missing or broken?',
                    'Are you experiencing any difficulty chewing?',
                    'Do you have a preference between implants, bridge, or denture?',
                    'Any previous prosthetic work? Any issues with it?',
                    'Any medical conditions or medications that may affect healing?',
                ],
                'suggested_findings' => [
                    'Number of missing teeth and location',
                    'Ridge form and height',
                    'Occlusal space available',
                    'Abutment tooth condition',
                    'Opposing dentition',
                    'Soft tissue health',
                ],
                'suggested_investigations' => [
                    'OPG', 'CBCT (implant planning)', 'IOPA (abutments)',
                    'Blood tests (CBP, blood sugar, INR if on anticoagulants)',
                    'Study models',
                ],
                'possible_diagnoses' => [
                    'Partially edentulous — implant candidate',
                    'Partially edentulous — bridge candidate',
                    'Partially edentulous — removable partial denture',
                    'Completely edentulous — complete denture',
                    'Completely edentulous — implant-retained overdenture',
                    'Fractured tooth — crown required',
                    'Failed restoration — replacement crown/onlay',
                ],
                'module_config' => [
                    ['label' => 'Missing teeth (FDI)', 'name' => 'pros_missing', 'type' => 'text',
                     'placeholder' => 'e.g. 16, 26, 36'],
                    ['label' => 'Proposed treatment', 'name' => 'pros_tx', 'type' => 'select',
                     'options' => ['Implant', 'Bridge', 'RPD', 'Complete denture', 'Implant overdenture', 'Crown', 'Not decided']],
                    ['label' => 'Ridge adequacy', 'name' => 'pros_ridge', 'type' => 'select',
                     'options' => ['Adequate', 'Deficient — horizontal', 'Deficient — vertical', 'Atrophic']],
                    ['label' => 'Bone grafting needed', 'name' => 'pros_graft', 'type' => 'select',
                     'options' => ['Not required', 'Possible', 'Required — sinus lift', 'Required — block graft']],
                ],
            ],
        ];

        foreach ($specialties as $data) {
            TreatmentKnowledge::updateOrCreate(
                ['specialty_tag' => $data['specialty_tag']],
                $data
            );
        }

        $this->command->info('TreatmentKnowledgeSeeder: 5 specialties seeded ✓');
    }
}
