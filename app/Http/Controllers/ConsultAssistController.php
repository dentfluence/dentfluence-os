<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TreatmentKnowledge;
use App\Models\Treatment;
use App\Models\Consultation;

/**
 * ConsultAssistController — AJAX back-end for the Consult Assist sidebar.
 *
 * Endpoints:
 *   POST /consult-assist/suggest          — specialty + treatment matches from chief complaint
 *   POST /consult-assist/section-guidance — rule-based guidance for a given form section
 *   POST /consult-assist/tooth-timeline   — treatment history for a specific tooth
 */
class ConsultAssistController extends Controller
{
    /**
     * POST /consult-assist/suggest
     */
    public function suggest(Request $request)
    {
        $request->validate(['complaint' => ['required', 'string', 'max:2000']]);

        $complaint = $request->input('complaint');

        $specialtyMatches = TreatmentKnowledge::matchComplaint($complaint);
        $matched = $specialtyMatches->map(fn(TreatmentKnowledge $s) => [
            'tag'            => $s->specialty_tag,
            'label'          => $s->display_label,
            'icon'           => $s->display_icon,
            'questions'      => $s->suggested_questions      ?? [],
            'investigations' => $s->suggested_investigations  ?? [],
            'diagnoses'      => $s->possible_diagnoses        ?? [],
        ]);

        $treatmentMatches = Treatment::matchComplaint($complaint, 5);
        $treatments = $treatmentMatches->map(fn(Treatment $t) => [
            'id'            => $t->id,
            'name'          => $t->name,
            'code'          => $t->code,
            'category'      => $t->category?->name ?? '',
            'color'         => $t->color ?? '#6a0f70',
            'default_price' => $t->default_price,
            'min_price'     => $t->min_price,
            'max_price'     => $t->max_price,
            'questions'     => array_slice($t->suggested_questions      ?? [], 0, 5),
            'investigations'=> array_slice($t->suggested_investigations  ?? [], 0, 5),
            'diagnoses'     => array_slice($t->possible_diagnoses        ?? [], 0, 4),
            'stages'        => $t->stages ?? [],
        ]);

        return response()->json(['matched' => $matched, 'treatments' => $treatments]);
    }

    /**
     * POST /consult-assist/section-guidance
     *
     * Rule-based guidance for a given consultation section.
     * Request:  { "section": "radiographic-findings", "complaint": "...", "diagnosis": "..." }
     * Response: { "tips": [...], "checklist": [...], "red_flags": [...] }
     */
    public function sectionGuidance(Request $request)
    {
        $request->validate([
            'section'   => ['required', 'string', 'max:100'],
            'complaint' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
        ]);

        $section   = $request->input('section');
        $complaint = strtolower($request->input('complaint', ''));

        $map = [
            'chief-complaint' => [
                'tips'      => ['Be specific: location, onset, duration, severity, aggravating/relieving factors.', 'Use FDI tooth notation when referring to specific teeth.', 'Document pain character: sharp / dull / throbbing / burning.'],
                'checklist' => ['Duration captured', 'Severity graded (mild / moderate / severe)', 'Tooth / area documented', 'Aggravating factors noted'],
                'red_flags' => ['Sudden onset severe pain → possible acute pulpitis or abscess.', 'Pain on lying down → likely pulpitis.', 'Spontaneous nocturnal pain → endodontic urgency.'],
            ],
            'visit-type' => [
                'tips'      => ['Emergency visit: focus on pain relief — defer elective work.', 'Comprehensive exam: all sections mandatory (photos, chart, investigations).', 'Follow-up: document outcome of previous treatment.'],
                'checklist' => ['Visit type matches clinical intent', 'Consultation type (new / review) correct', 'Doctor assigned'],
                'red_flags' => [],
            ],
            'photographs' => [
                'tips'      => ['9-photo protocol: retracted frontal, lateral L/R, occlusal upper/lower, smile, profile L/R, close-up.', 'Use standard lighting and retractors for consistency.', 'Mandatory for comprehensive and smile-design cases.'],
                'checklist' => ['Retracted frontal taken', 'Both lateral views taken', 'Occlusal views taken', 'Smile photo taken'],
                'red_flags' => ['Visible facial swelling or asymmetry → document and consider escalation.', 'Ulceration visible in photos → consider biopsy referral.'],
            ],
            'intraoral-scans' => [
                'tips'      => ['Scan on dry field — moisture causes scan errors.', 'Upper arch + lower arch + bite registration minimum.', 'Export STL for lab communication.'],
                'checklist' => ['Upper arch scanned', 'Lower arch scanned', 'Occlusal bite recorded', 'Scan date documented'],
                'red_flags' => [],
            ],
            'investigations' => [
                'tips'      => ['IOPA: best for single tooth — use paralleling technique.', 'OPG: full arch overview, TMJ, third molars.', 'CBCT: indicated for implants, impactions, root resorption.', 'Blood tests: mandatory pre-extraction in high-risk patients.'],
                'checklist' => ['Investigation type documented', 'Findings noted for each investigation', 'Reports uploaded / attached'],
                'red_flags' => ['No X-ray for suspected caries / RCT → incomplete workup.', 'Blood tests not done pre-extraction in diabetic / anticoagulated patient.'],
            ],
            'clinical-findings' => [
                'tips'      => ['Chart teeth systematically: upper right → upper left → lower left → lower right.', 'BOP and probing depths for every perio case.', 'Record existing restorations and their current condition.'],
                'checklist' => ['Caries charted', 'Periodontal status noted', 'Occlusion recorded', 'TMJ assessed', 'Oral hygiene scored'],
                'red_flags' => ['Furcation involvement noted → perio referral warranted.', 'Class III mobility → guarded prognosis.', 'BOP >30% sites → active periodontitis.'],
            ],
            'radiographic-findings' => [
                'tips'      => ['Correlate X-ray with clinical findings — neither alone is sufficient.', 'Note: periapical lesion, bone loss %, crown-to-root ratio.', 'For RCT: confirm working length radiographically.'],
                'checklist' => ['X-ray type documented', 'Periapical status noted', 'Bone levels assessed', 'Root morphology noted if relevant'],
                'red_flags' => ['Widening of PDL space → periapical pathology.', 'Horizontal bone loss >50% → poor prognosis.', 'Furcation radiolucency → grade 3 involvement.'],
            ],
            'dbm-checklist' => [
                'tips'      => ['DBM score drives the consultation tier (basic / standard / premium).', 'Aim for 33/33 on comprehensive exams.', 'Shade and whitening fields unlock the cosmetic upsell flow.'],
                'checklist' => ['All 33 DBM points reviewed', 'Score documented', 'Shade noted if cosmetic case'],
                'red_flags' => [],
            ],
            'diagnosis' => [
                'tips'      => ['Primary diagnosis must match chief complaint + investigation findings.', 'Use standard dental nomenclature (ICD-10-CM where possible).', 'Secondary diagnosis: comorbid conditions affecting treatment decisions.'],
                'checklist' => ['Primary diagnosis documented', 'Risk level assessed (low / medium / high)', 'Differential diagnoses considered'],
                'red_flags' => ['Diagnosis not supported by investigations → revisit findings.', 'High-risk diagnosis without specialist referral note.'],
            ],
            'treatment-advised' => [
                'tips'      => ['Separate emergency (now), protective (soon), transformative (elective).', 'Sequence emergency relief before elective cosmetic work.', 'Present all options — patient chooses after informed consent.'],
                'checklist' => ['Emergency treatment listed (if applicable)', 'Preventive / protective treatment noted', 'Elective / cosmetic options discussed'],
                'red_flags' => ['No treatment advised for a diagnosed condition → document reason.', 'Transformative work advised without completing caries control first.'],
            ],
            'treatment-plan' => [
                'tips'      => ['Best Option = ideal sequence; Acceptable Option = patient-preferred compromise.', 'Include stage count, cost estimate, and tooth-specific notes per line.', 'AOC Plan: offer when total treatment cost > ₹10,000.'],
                'checklist' => ['Best options plan populated', 'Cost estimates entered', 'Tooth column filled for each item', 'Patient informed and plan discussed'],
                'red_flags' => ['Treatment plan without cost → billing gap.', 'Plan created without linked treatment-advised items.'],
            ],
            'finishing-section' => [
                'tips'      => ['Always set next visit type and date before closing the record.', 'Recall interval: 6 months default; 3 months for active perio cases.', 'Notes: document patient concerns, compliance, and home-care advice.'],
                'checklist' => ['Next visit type selected', 'Next visit date set', 'Recall interval documented', 'Responsible doctor confirmed'],
                'red_flags' => ['No follow-up date set → patient may be lost to follow-up.', 'Active perio with recall interval > 3 months is too long.'],
            ],
        ];

        $guidance = $map[$section] ?? [
            'tips'      => ['Complete all fields thoroughly for an accurate patient record.'],
            'checklist' => [],
            'red_flags' => [],
        ];

        // Complaint-aware tip injection
        if ($section === 'radiographic-findings' && str_contains($complaint, 'sensitivity')) {
            array_unshift($guidance['tips'], 'Sensitivity → check dentinal exposure, cracks, and periapical status on IOPA.');
        }
        if ($section === 'diagnosis' && (str_contains($complaint, 'pain') || str_contains($complaint, 'sensitivity'))) {
            array_unshift($guidance['tips'], 'Pain / sensitivity → consider: pulpitis (reversible / irreversible), cracked tooth, dentin hypersensitivity.');
        }

        return response()->json($guidance);
    }

    /**
     * POST /consult-assist/tooth-timeline
     *
     * Treatment history for a specific tooth across all patient consultations.
     * Request:  { "patient_id": 12, "tooth": "46" }
     * Response: { "tooth": "46", "count": 2, "timeline": [...] }
     */
    public function toothTimeline(Request $request)
    {
        $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'tooth'      => ['required', 'string', 'max:10'],
        ]);

        $patientId = $request->integer('patient_id');
        $tooth     = trim($request->input('tooth'));

        $consultations = Consultation::where('patient_id', $patientId)
            ->with('doctor')
            ->orderByDesc('consultation_date')
            ->get();

        $timeline = $consultations
            ->filter(function (Consultation $c) use ($tooth) {
                $teeth = array_merge(
                    $c->chartToothNumbers(),
                    (array) ($c->tx_teeth    ?? [])
                );
                return in_array($tooth, $teeth, true);
            })
            ->map(fn(Consultation $c) => [
                'consultation_id' => $c->id,
                'date'            => \Carbon\Carbon::parse($c->consultation_date)->format('d M Y'),
                'visit_type'      => $c->visit_type ?? '—',
                'diagnosis'       => $c->primary_diagnosis ?: ($c->chief_complaint ?: '—'),
                'treatment'       => implode(', ', array_slice(array_merge(
                    (array) ($c->tx_emergency      ?? []),
                    (array) ($c->tx_protective     ?? []),
                    (array) ($c->tx_transformative ?? [])
                ), 0, 3)) ?: '—',
                'doctor'          => $c->doctor?->name ?? '—',
                'status'          => $c->status ?? 'completed',
            ])
            ->values();

        return response()->json([
            'tooth'    => $tooth,
            'count'    => $timeline->count(),
            'timeline' => $timeline,
        ]);
    }
}
