<?php
/**
 * Treatment Visit form bootstrap — raw PHP, NOT a Blade view (08-05).
 *
 * Deliberately included via PHP's native `include` (inside a @php block),
 * never via Blade's @include(). Blade's @include() renders the target as an
 * independent View — variables it sets internally do NOT propagate back to
 * the calling template's scope. A native `include` executes in the same
 * compiled-function scope as the calling view, so $visitsJson, $stagesJson,
 * etc. become real local variables there, visible to any @include() that
 * follows (treatment-visit-form-fields / treatment-visit-form-script both
 * depend on this). Do not convert this back to a .blade.php @include target
 * without also fixing that scoping problem.
 */

// Stages loaded from Treatment module (defined per-treatment in Treatments → Stages tab)
$treatmentStages = \App\Models\TreatmentVisit::allStagesFromDb();

// Safe fallback so the closure below never throws "Undefined variable $prescriptions"
$_rxCollection = $prescriptions ?? collect();

// Implant stock-usage lookups, batched once (not per-visit) to avoid N+1 —
// used below to re-populate the "components used" picker when editing a visit.
$_visitIds = $patient->treatmentVisits->pluck('id');

// Visit → Next Action (08-14) — reception actions the doctor issued from each
// visit, batched once to avoid N+1. Re-populates the Next Action repeater when
// a visit is edited, so re-saving reconciles the SAME follow_ups rows instead
// of creating duplicates. Only pending rows are editable; an executed call is
// history and is filtered out here.
$_nextActionsByVisit = \App\Models\FollowUp::fromVisit()
    ->whereIn('treatment_visit_id', $_visitIds)
    ->where('status', 'pending')
    ->orderBy('due_date')
    ->get()
    ->groupBy('treatment_visit_id');
$_implantMovementsByVisit = \App\Models\Inventory\StockMovement::where('reference_type', \App\Models\TreatmentVisit::class)
    ->whereIn('reference_id', $_visitIds)
    ->get()
    ->groupBy('reference_id');
$_catalogIdByInventoryItem = \App\Models\Inventory\ImplantCatalog::whereNotNull('inventory_item_id')
    ->pluck('id', 'inventory_item_id');

// Medical Alerts — presentation-only (08-05): same derivation already used by
// patients/profile/header.blade.php's "Clinical Alerts" banner, reused here so
// the encounter view surfaces them without a second data source. $patient is
// already loaded for this tab; no new query, no schema change.
$_clinicalAlerts = [];
if (!empty($patient->medical_conditions)) {
    foreach ($patient->medical_conditions as $mc) {
        if (trim($mc)) $_clinicalAlerts[] = ['text' => trim($mc), 'type' => 'condition'];
    }
}
if (!empty($patient->allergies)) {
    foreach ($patient->allergies as $al) {
        if (trim($al)) $_clinicalAlerts[] = ['text' => 'Allergy: '.trim($al), 'type' => 'allergy'];
    }
}
if (!empty($patient->medical_alert)) {
    foreach (array_map('trim', explode(',', $patient->medical_alert)) as $ma) {
        if ($ma) $_clinicalAlerts[] = ['text' => $ma, 'type' => 'alert'];
    }
}

$visitsJson = $patient->treatmentVisits->map(function($v) use ($_rxCollection, $_implantMovementsByVisit, $_catalogIdByInventoryItem, $_nextActionsByVisit) {
    return [
        'id'               => $v->id,
        'appointment_id'   => $v->appointment_id,
        'visit_date'       => $v->visit_date?->format('Y-m-d'),
        'visit_type'       => $v->visit_type,
        'status'           => $v->status,
        'doctor_id'        => (string)($v->doctor_id ?? ''),
        'doctor_name'      => $v->doctor?->name,
        'treatment_name'   => $v->treatment_name,
        'current_stage'    => $v->current_stage,
        'completed_stages' => $v->completed_stages ?? [],
        'tooth_number'     => $v->tooth_number,
        'notes'            => $v->notes,
        'chief_complaint'  => $v->chief_complaint,
        'cost'             => (float)$v->cost,
        'amount_paid'      => (float)$v->amount_paid,
        'balance_due'      => max(0,(float)$v->cost-(float)$v->amount_paid),
        'payment_mode'     => $v->payment_mode,
        'payment_reference'=> $v->payment_reference,
        'next_visit_date'  => $v->next_visit_date?->format('Y-m-d'),
        'next_visit_type'  => $v->next_visit_type,
        'handover'         => $v->handover,
        'handover_summary' => \App\Support\Handover::summary($v->handover),
        'rct_num_canals'        => $v->rct_num_canals,
        'rct_canal_lengths'     => $v->rct_canal_lengths ?? [],
        'rct_file_type'         => $v->rct_file_type,
        'rct_irrigant'          => $v->rct_irrigant,
        'rct_obturation_method' => $v->rct_obturation_method,
        'impl_brand'            => $v->impl_brand,
        'impl_size'             => $v->impl_size,
        'impl_torque'           => $v->impl_torque,
        'impl_graft_used'       => $v->impl_graft_used,
        'impl_graft_brand'      => $v->impl_graft_brand,
        'impl_membrane'         => $v->impl_membrane,
        'impl_healing_collar'   => $v->impl_healing_collar,
        'implant_fixture_catalog_id' => $v->implantPlacement?->implant_catalog_id,
        'implant_lot_number'         => $v->implantPlacement?->lot_number,
        'implant_components_used'    => $v->implantPlacement
            ? collect($_implantMovementsByVisit->get($v->id, collect()))
                ->pluck('inventory_item_id')
                ->map(fn($itemId) => $_catalogIdByInventoryItem->get($itemId))
                ->filter()
                ->reject(fn($cid) => $cid === $v->implantPlacement->implant_catalog_id)
                ->unique()->values()->all()
            : [],
        'fill_material'         => $v->fill_material,
        'fill_shade'            => $v->fill_shade,
        'scale_quadrants'       => $v->scale_quadrants,
        'scale_method'          => $v->scale_method,
        'ext_type'              => $v->ext_type,
        'ext_socket'            => $v->ext_socket,
        'ext_suture'            => (bool)$v->ext_suture,
        'crown_type'            => $v->crown_type,
        'crown_shade'           => $v->crown_shade,
        'crown_impression'      => (bool)$v->crown_impression,
        'crown_temp_placed'     => $v->crown_temp_placed,
        'treatment_plan_id'         => $v->treatment_plan_id,
        'prescription_drugs'        => $v->prescription_drugs ?? [],
        'prescription_instructions' => $v->prescription_instructions ?? [],
        'prescription_custom_notes' => $v->prescription_custom_notes,
        // Vitals
        'bp_systolic'      => $v->bp_systolic,
        'bp_diastolic'     => $v->bp_diastolic,
        'pulse_rate'       => $v->pulse_rate,
        'spo2'             => $v->spo2,
        'temperature'      => $v->temperature,
        'blood_sugar'      => $v->blood_sugar,
        'blood_sugar_type' => $v->blood_sugar_type,
        'weight'           => $v->weight,
        'vitals_notes'     => $v->vitals_notes,
        // F2: visit items for billing
        'visit_items' => $v->visitItems->map(fn($i) => [
            'id'                     => $i->id,
            'treatment_plan_item_id' => $i->treatment_plan_item_id,
            'treatment_name'         => $i->treatment_name,
            'material_option'        => $i->material_option,
            'tooth_number'           => $i->tooth_number,
            'suggested_price'        => (float)$i->suggested_price,
            'billing_status'         => $i->billing_status,
            'work_outcome'           => $i->work_outcome,
            'notes'                  => $i->notes,
            'is_repeat'              => (bool)$i->is_repeat,
            'repeat_reason'          => $i->repeat_reason,
            'repeat_of_visit_item_id'=> $i->repeat_of_visit_item_id,
        ])->values()->all(),
        // Visit → Next Action — pending reception actions issued from this visit
        'next_actions' => collect($_nextActionsByVisit->get($v->id, collect()))
            ->map(fn($f) => \App\Services\Clinical\VisitNextActionService::present($f))
            ->values()->all(),
        // Linked formal prescription (new Prescription module)
        'linked_rx' => (function() use ($v, $_rxCollection) {
            $rx = $_rxCollection->where('visit_id', $v->id)->whereNotIn('status', ['cancelled'])->first();
            if (!$rx) return null;
            return ['number' => $rx->prescription_number, 'id' => $rx->id,
                    'drugs'  => $rx->items->count(), 'status' => $rx->status];
        })(),
        '_isNew' => false,
    ];
});

$stagesJson       = $treatmentStages;

// All active treatments — select lab columns only after migration has run
$_labColsExist  = \Illuminate\Support\Facades\Schema::hasColumn('treatments', 'needs_lab');
$_selectCols    = $_labColsExist
                    ? ['id','name','default_price','needs_lab','lab_work_category']
                    : ['id','name','default_price'];
$_allTreatments = \App\Models\Treatment::where('is_active', true)
                      ->orderBy('sort_order')->orderBy('name')
                      ->get($_selectCols);
$treatmentsList   = $_allTreatments->pluck('name')->all();

// Clinic procedure catalogue for the "+ Add Custom Treatment" picker in
// Today's Procedures. Name + catalogue price + lab flag only: picking a
// procedure fills its Suggested Price and pre-arms the per-procedure
// "Lab Required?" toggle, so the doctor never re-types what the catalogue
// already knows. A procedure the catalogue does not have can still be
// typed in and recorded -- the catalogue is a shortcut, not a gate.
$treatmentsCatalog = $_allTreatments->map(fn($t) => [
    'name'      => $t->name,
    'price'     => (float) ($t->default_price ?? 0),
    'needs_lab' => $_labColsExist ? (bool) $t->needs_lab : false,
])->values()->all();

// Map of treatment name → lab info (empty until migration runs)
$labTreatmentsMap = $_labColsExist
    ? $_allTreatments->where('needs_lab', true)
          ->keyBy('name')
          ->map(fn($t) => ['work_category' => $t->lab_work_category ?? ''])
          ->toArray()
    : [];

$doctorsList        = $doctors ?? collect();
$labVendorsList     = \App\Models\LabVendor::where('is_active', true)->orderBy('name')->get(['id','name']);

// F2: pass plans as JSON so visit form can load plan items via AJAX
// Only ACCEPTED plans (patient said yes -> accepted_at is set) should surface
// in the visit form. Pending/un-accepted options stay hidden here.
// Slice 2.4e — the plan picker used to label each plan with
// treatment_plans.status, so an accepted-but-untouched plan read "(Ongoing)".
// That is a lifecycle column, not clinical progress. It now consumes the
// canonical Derived Progress Service, which answers from recorded clinical
// work. Nothing is derived here; this only asks.
$_progress = app(\App\Services\Clinical\DerivedProgressService::class);

// Canonical clinical progress per (procedure, tooth) for THIS patient, keyed
// "rct|46". Repeat-work detection in the visit form reads this map instead of
// deciding for itself whether past work was finished -- one derivation, one
// owner (DerivedProgressService). A procedure with no recorded outcome is
// simply absent, which reads as "not finished", which is the safe answer.
$procedureProgressJson = $_progress->deriveProcedureProgressForPatient($patient->id);

$treatmentPlansJson = ($patient->treatmentPlans ?? collect())
    ->filter(fn($p) => !is_null($p->accepted_at))
    ->map(fn($p) => [
        'id'        => $p->id,
        'plan_name' => $p->plan_name,
        'progress'  => $_progress->deriveTreatmentPlanProgress($p)->progress->label(),
    ])->values();

// Appointments: upcoming + last 30 days, for linking to a visit
$appointmentsJson = ($patient->appointments ?? collect())
    ->filter(fn($a) => $a->appointment_date >= now()->subDays(30))
    ->sortByDesc('appointment_date')
    ->values()
    ->map(fn($a) => [
        'id'          => $a->id,
        'date'        => $a->appointment_date->format('Y-m-d'),
        'label'       => $a->appointment_date->format('d M Y') .
                         ($a->appointment_time ? ' · ' . \Carbon\Carbon::parse($a->appointment_time)->format('h:i A') : '') .
                         ($a->treatmentCategory ? ' · ' . $a->treatmentCategory->name : ''),
        'doctor_id'   => (string)($a->doctor_id ?? ''),
        'status'      => $a->status,
        // Redesign meta-line: appointment type drives the visit_type default
        // (consultation/treatment/follow-up → visit_type mapping in JS).
        'type'        => $a->type,
    ]);
