<?php

namespace App\Services;

use App\Models\BillingPrompt;
use App\Models\Inventory\ImplantCatalog;
use App\Models\Inventory\ImplantPlacement;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\StockMovement;
use App\Models\LabCase;
use App\Models\Patient;
use App\Models\Task;
use App\Models\TreatmentPlan;
use App\Models\TreatmentVisit;
use App\Services\Relationship\ActivityEngine;
use App\Services\Workflow\WorkflowShadowRunner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * TreatmentVisitService
 * ---------------------
 * The single "brain" for creating / updating treatment visits. Both the web
 * controller and the API (mobile) controller call this, so a visit saved from
 * the phone behaves EXACTLY like one saved from the web — same side-effects:
 *
 *   • visit items  → billing prompt for front desk (F2)
 *   • lab section  → draft LabCase
 *   • mark plan complete → plan status = completed + auto 6-month recall task
 *   • Phase 5 shadow-run  → observe current_stage against the Workflow
 *     Engine (behind `workflow.engine`, off by default; log-only, see
 *     App\Services\Workflow\WorkflowShadowRunner)
 *
 * This was lifted verbatim out of App\Http\Controllers\TreatmentVisitController
 * so existing web behaviour is unchanged; the controller is now a thin wrapper.
 */
class TreatmentVisitService
{
    public function __construct(private WorkflowShadowRunner $workflowShadow)
    {
    }

    /**
     * Validation rules shared by store + update (web + API identical).
     * Kept here so there is one source of truth for the visit contract.
     *
     * NOTE: cost, amount_paid, payment_mode, payment_reference intentionally
     * excluded. Billing is managed by front desk via billing prompts (F3+).
     */
    public static function rules(): array
    {
        return [
            'visit_date'           => ['required', 'date'],
            'visit_type'           => ['required', Rule::in(['treatment','followup','emergency','recall'])],
            'status'               => ['required', Rule::in(['scheduled','in_chair','completed','cancelled','no_show'])],
            'doctor_id'            => ['nullable', 'exists:users,id'],
            'appointment_id'        => ['nullable', 'exists:appointments,id'],
            'consultation_id'      => ['nullable', 'exists:consultations,id'],
            'treatment_plan_id'    => ['nullable', 'exists:treatment_plans,id'],  // links visit to plan
            'treatment_name'       => ['nullable', 'string', 'max:100'],
            'current_stage'        => ['nullable', 'string', 'max:100'],
            'completed_stages'     => ['nullable', 'array'],
            'tooth_number'         => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string'],
            'chief_complaint'      => ['nullable', 'string', 'max:500'],
            'next_visit_date'      => ['nullable', 'date'],
            'next_visit_type'      => ['nullable', 'string', 'max:100'],
            // RCT
            'rct_num_canals'         => ['nullable', 'integer', 'min:1', 'max:5'],
            'rct_canal_lengths'      => ['nullable', 'array'],
            'rct_file_type'          => ['nullable', 'string', 'max:100'],
            'rct_irrigant'           => ['nullable', 'string', 'max:100'],
            'rct_obturation_method'  => ['nullable', 'string', 'max:100'],
            // Implant
            'impl_brand'             => ['nullable', 'string', 'max:100'],
            'impl_size'              => ['nullable', 'string', 'max:50'],
            'impl_torque'            => ['nullable', 'string', 'max:50'],
            'impl_graft_used'        => ['nullable', 'string', 'max:100'],
            'impl_graft_brand'       => ['nullable', 'string', 'max:100'],
            'impl_membrane'          => ['nullable', 'string', 'max:100'],
            'impl_healing_collar'    => ['nullable', 'string', 'max:100'],
            // Implant — stock-linked catalog picker (falls back to impl_brand/impl_size
            // free text above when the fixture isn't in the catalog yet)
            'implant_fixture_catalog_id'   => ['nullable', 'integer', 'exists:implant_catalog,id'],
            'implant_components_used'      => ['nullable', 'array'],
            'implant_components_used.*'    => ['integer', 'exists:implant_catalog,id'],
            'implant_lot_number'           => ['nullable', 'string', 'max:100'],
            // Filling
            'fill_material'          => ['nullable', 'string', 'max:100'],
            'fill_shade'             => ['nullable', 'string', 'max:50'],
            // Scaling
            'scale_quadrants'        => ['nullable', 'string', 'max:100'],
            'scale_method'           => ['nullable', 'string', 'max:50'],
            // Extraction
            'ext_type'               => ['nullable', 'string', 'max:50'],
            'ext_socket'             => ['nullable', 'string', 'max:100'],
            'ext_suture'             => ['nullable', 'boolean'],
            // Crown prep
            'crown_type'             => ['nullable', 'string', 'max:50'],
            'crown_shade'            => ['nullable', 'string', 'max:50'],
            'crown_impression'       => ['nullable', 'boolean'],
            'crown_temp_placed'      => ['nullable', 'string', 'max:100'],
            // Prescription
            'prescription_drugs'         => ['nullable', 'array'],
            'prescription_instructions'  => ['nullable', 'array'],
            'prescription_custom_notes'  => ['nullable', 'string'],
            // Vitals (all optional)
            'bp_systolic'        => ['nullable', 'integer', 'min:40',  'max:300'],
            'bp_diastolic'       => ['nullable', 'integer', 'min:20',  'max:200'],
            'pulse_rate'         => ['nullable', 'integer', 'min:20',  'max:250'],
            'spo2'               => ['nullable', 'integer', 'min:50',  'max:100'],
            'temperature'        => ['nullable', 'numeric', 'min:30',  'max:45'],
            'blood_sugar'        => ['nullable', 'integer', 'min:20',  'max:800'],
            'blood_sugar_type'   => ['nullable', Rule::in(['random','fasting','pp'])],
            'weight'             => ['nullable', 'numeric', 'min:1',   'max:400'],
            'vitals_notes'       => ['nullable', 'string', 'max:255'],
            // F2: Visit items for billing (doctor selects what was done; front desk bills from this)
            'visit_items'                              => ['nullable', 'array'],
            'visit_items.*.treatment_plan_item_id'     => ['nullable', 'exists:treatment_plan_items,id'],
            'visit_items.*.treatment_name'             => ['required_with:visit_items', 'string', 'max:150'],
            'visit_items.*.material_option'            => ['nullable', 'string', 'max:100'],
            'visit_items.*.tooth_number'               => ['nullable', 'string', 'max:100'],
            'visit_items.*.suggested_price'            => ['nullable', 'numeric', 'min:0'],
            'visit_items.*.notes'                      => ['nullable', 'string', 'max:500'],
            // Repeat-work tracking — reason is required when an item is flagged as repeat
            'visit_items.*.is_repeat'                  => ['nullable', 'boolean'],
            'visit_items.*.repeat_reason'              => ['nullable', 'required_if:visit_items.*.is_repeat,true', 'string', 'max:300'],
            'visit_items.*.repeat_of_visit_item_id'    => ['nullable', 'integer'],
            // Mark the linked treatment plan as completed
            'mark_treatment_complete'          => ['nullable', 'boolean'],
            // Lab case creation (optional — only if treatment needs lab)
            'lab_case'                         => ['nullable', 'array'],
            'lab_case.enabled'                 => ['nullable', 'boolean'],
            'lab_case.lab_vendor_id'           => ['nullable', 'exists:lab_vendors,id'],
            'lab_case.work_category'           => ['nullable', 'string', 'max:100'],
            'lab_case.work_subtype'            => ['nullable', 'string', 'max:100'],
            'lab_case.priority'                => ['nullable', 'in:routine,urgent,express'],
            'lab_case.expected_return_date'    => ['nullable', 'date'],
            'lab_case.instructions'            => ['nullable', 'string'],
        ];
    }

    /**
     * Create a treatment visit and fire all the same side-effects the web does.
     */
    public function create(Patient $patient, array $data): TreatmentVisit
    {
        $visit = DB::transaction(function () use ($data, $patient) {
            // Strip visit_items, lab_case, mark_treatment_complete, implant_* —
            // these are transient control params, not real treatment_visits columns.
            $visit = $patient->treatmentVisits()->create(
                array_diff_key($data, $this->transientKeys())
            );

            // Save visit items and fire billing prompt (F2)
            if (!empty($data['visit_items'])) {
                $this->saveVisitItems($visit, $data['visit_items']);
            }

            // Create lab case if doctor filled the lab section
            if (!empty($data['lab_case']['enabled'])) {
                $this->createLabCase($visit, $data['lab_case']);
            }

            // Mark the linked treatment plan as completed if requested
            if (!empty($data['mark_treatment_complete']) && $visit->treatment_plan_id) {
                $this->completePlanAndQueueRecall($visit->treatment_plan_id, $patient->id, $patient->name, $data['treatment_name'] ?? null);
            }

            // Record implant placement + deduct stock for fixture/components used
            $this->recordImplantPlacementAndStock($visit, $data);

            return $visit;
        });

        // Phase 5 shadow-run — AFTER the transaction has committed, and
        // self-contained (see WorkflowShadowRunner::run() docblock), so a
        // shadow-run bug can NEVER roll back or block the real visit save
        // that already succeeded above. Extra try/catch here is belt and
        // braces on top of the runner's own internal guard.
        try {
            $this->workflowShadow->run($visit);
        } catch (Throwable $e) {
            report($e);
        }

        return $visit->load(['doctor', 'visitItems']);
    }

    /**
     * Update an existing treatment visit (mirrors the web update exactly).
     */
    public function update(TreatmentVisit $visit, array $data): TreatmentVisit
    {
        DB::transaction(function () use ($data, $visit) {
            $visit->update(array_diff_key($data, $this->transientKeys()));

            // Mark the linked treatment plan as completed if requested
            if (!empty($data['mark_treatment_complete']) && $visit->treatment_plan_id) {
                $this->completePlanAndQueueRecall(
                    $visit->treatment_plan_id,
                    $visit->patient_id,
                    $visit->patient->name ?? 'Patient',
                    $data['treatment_name'] ?? $visit->treatment_name ?? null
                );
            }

            // Re-sync visit items if provided (F2)
            if (array_key_exists('visit_items', $data)) {
                // Delete old pending items and re-create
                $visit->visitItems()->delete();
                if (!empty($data['visit_items'])) {
                    // Dismiss any existing pending billing prompt for this visit
                    BillingPrompt::where('trigger_type', 'treatment_visit')
                        ->where('trigger_id', $visit->id)
                        ->where('status', 'pending')
                        ->update(['status' => 'dismissed']);

                    $this->saveVisitItems($visit, $data['visit_items']);
                }
            }

            // Record implant placement + deduct stock for fixture/components used.
            // Idempotent — re-saving the same visit won't double-deduct (see method docblock).
            $this->recordImplantPlacementAndStock($visit, $data);
        });

        // Phase 5 shadow-run — see the matching comment in create() above.
        try {
            $this->workflowShadow->run($visit);
        } catch (Throwable $e) {
            report($e);
        }

        return $visit->load(['doctor', 'visitItems']);
    }

    /**
     * Keys in the validated payload that are transient control params, not
     * real columns on treatment_visits — stripped before the mass-assignment
     * create()/update() call and handled by their own dedicated methods.
     */
    private function transientKeys(): array
    {
        return [
            'visit_items'                => true,
            'lab_case'                   => true,
            'mark_treatment_complete'    => true,
            'implant_fixture_catalog_id' => true,
            'implant_components_used'    => true,
            'implant_lot_number'         => true,
        ];
    }

    /**
     * Record implant placement traceability + deduct stock for the fixture
     * and any accessory components (healing abutment, cover screw, coping,
     * scan body, graft) selected from the catalog.
     *
     * Idempotent by design:
     *   - One ImplantPlacement per visit (keyed on treatment_visit_id). Re-saving
     *     the visit updates traceability fields but never touches the clinical
     *     `status`, since staff may have already advanced it via the Implant
     *     Registry (placed → osseointegrating → loaded, etc.).
     *   - Stock is deducted once per catalog component per visit (guarded via
     *     the stock_movements reference_type/reference_id pair). Re-saving an
     *     unchanged visit won't double-deduct; adding a new component on a
     *     later edit deducts only the newly-added one.
     *
     * Deliberately does NOT attempt any "return to stock" / resterilization
     * automation — reusable components (healing abutment, cover screw, coping,
     * scan body) are tracked as assets and returned to stock manually via the
     * existing Inventory screens once cleaned. Keeping this slice to placement
     * + deduction only.
     */
    private function recordImplantPlacementAndStock(TreatmentVisit $visit, array $data): void
    {
        if (($data['treatment_name'] ?? null) !== 'Implant') {
            return;
        }

        $fixtureCatalogId = $data['implant_fixture_catalog_id'] ?? null;
        $componentIds     = array_values(array_filter($data['implant_components_used'] ?? []));
        $hasFreeText      = !empty($data['impl_brand']);

        if (!$fixtureCatalogId && !$hasFreeText && empty($componentIds)) {
            return; // nothing implant-specific was recorded on this visit
        }

        // One placement record per visit — traceability fields only.
        $placement = ImplantPlacement::firstOrNew(['treatment_visit_id' => $visit->id]);
        $placement->patient_id             = $visit->patient_id;
        $placement->surgeon_id             = $visit->doctor_id;
        $placement->tooth_position         = $visit->tooth_number;
        $placement->surgery_date           = $visit->visit_date;
        $placement->implant_catalog_id     = $fixtureCatalogId;
        $placement->implant_brand_freetext = $fixtureCatalogId ? null : ($data['impl_brand'] ?? null);
        $placement->implant_code_freetext  = $fixtureCatalogId ? null : ($data['impl_size'] ?? null);
        $placement->lot_number             = $data['implant_lot_number'] ?? $placement->lot_number;
        if (!$placement->exists) {
            $placement->status     = 'placed';
            $placement->created_by = Auth::id();
        }
        $placement->save();

        // Deduct stock for the fixture + every accessory component.
        $catalogIdsToConsume = array_values(array_unique(array_filter(array_merge([$fixtureCatalogId], $componentIds))));

        foreach ($catalogIdsToConsume as $catalogId) {
            $catalogItem   = ImplantCatalog::with('inventoryItem')->find($catalogId);
            $inventoryItem = $catalogItem?->inventoryItem;
            if (!$inventoryItem) {
                continue; // this component isn't stock-linked yet — nothing to deduct
            }

            $alreadyMoved = StockMovement::where('inventory_item_id', $inventoryItem->id)
                ->where('reference_type', TreatmentVisit::class)
                ->where('reference_id', $visit->id)
                ->exists();
            if ($alreadyMoved) {
                continue;
            }

            $location = InventoryLocation::where('type', 'implant_drawer')->where('is_active', true)->first()
                ?? InventoryLocation::where('is_active', true)->first();
            if (!$location) {
                continue; // no location configured yet — never block the clinical save on this
            }

            StockMovement::create([
                'inventory_item_id' => $inventoryItem->id,
                'movement_type'     => 'treatment_usage',
                'qty'               => -1,
                'from_location_id'  => $location->id,
                'unit_cost'         => $inventoryItem->average_purchase_price,
                'total_cost'        => $inventoryItem->average_purchase_price,
                'reference_type'    => TreatmentVisit::class,
                'reference_id'      => $visit->id,
                'notes'             => 'Used in implant placement — ' . $catalogItem->getFullName(),
                'created_by'        => Auth::id(),
            ]);
        }
    }

    /**
     * Mark the linked plan complete and auto-create a 6-month recall task
     * (due 1 week before the 6-month mark). Skipped if a recall already exists.
     * Identical to the block that previously lived in store()/update().
     */
    private function completePlanAndQueueRecall(int $treatmentPlanId, int $patientId, string $patientName, ?string $treatmentName = null): void
    {
        TreatmentPlan::where('id', $treatmentPlanId)->update(['status' => 'completed']);

        $hasRecall = Task::where('patient_id', $patientId)
            ->where('category', 'follow_up')
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('title', 'like', '%recall%')
                  ->orWhere('title', 'like', '%6-month%');
            })
            ->exists();

        if (! $hasRecall) {
            Task::create([
                'title'       => '6-Month Recall: Book appointment for ' . $patientName,
                'description' => 'Treatment plan completed. Please contact the patient and schedule their 6-month recall checkup appointment.',
                'category'    => 'follow_up',
                'priority'    => 'medium',
                'status'      => 'pending',
                'patient_id'  => $patientId,
                'branch_id'   => Auth::user()->branch_id,
                'created_by'  => Auth::id(),
                // Due 1 week before the 6-month mark so staff has time to reach out
                'due_date'    => now()->addMonths(6)->subWeek(),
            ]);
        }

        // ── Backend orchestration (docs/backend-orchestration-plan.md §2.7) ────
        // Record 'treatment.completed' — this is the trigger the already-enabled
        // implant_followup / post_treatment_followup RulesEngine rules have been
        // configured for since they were written, but nothing has ever fired it.
        // Deliberately NOT logging 'visit.completed' here: the recall_6months
        // rule keys off that string and would create a second, duplicate
        // 6-month recall task alongside the inline one just above.
        $plan = TreatmentPlan::with('patient')->find($treatmentPlanId);
        if ($plan) {
            app(ActivityEngine::class)->log(
                subject:        $plan,
                event:          'treatment.completed',
                actor:          Auth::user(),
                metadata:       [
                    'patient_id'      => $patientId,
                    'treatment_type'  => $treatmentName === 'Implant' ? 'implant' : 'other',
                    'treatment_name'  => $treatmentName,
                ],
                relationshipId: $plan->patient?->relationship_id,
                description:    'Treatment plan marked complete',
            );
        }
    }

    /**
     * Create a LabCase linked to the given visit.
     */
    private function createLabCase(TreatmentVisit $visit, array $lc): void
    {
        $labCase = LabCase::create([
            'patient_id'           => $visit->patient_id,
            'treatment_visit_id'   => $visit->id,
            'doctor_id'            => $visit->doctor_id ?? auth()->id(),
            'lab_vendor_id'        => $lc['lab_vendor_id'] ?? null,
            'work_category'        => $lc['work_category'] ?? 'Other',
            'work_subtype'         => $lc['work_subtype'] ?? null,
            'priority'             => $lc['priority'] ?? 'routine',
            'expected_return_date' => $lc['expected_return_date'] ?? null,
            'instructions'         => $lc['instructions'] ?? null,
            'status'               => 'draft',
            'payment_status'       => 'pending',
        ]);

        // Inherit the teeth the doctor already picked on this visit's tooth chart
        // (treatment_visits.tooth_number, comma-joined) so the Lab tab shows them
        // without re-selecting — one LabCaseItem per tooth.
        $teeth = collect(explode(',', (string) $visit->tooth_number))
            ->map(fn ($t) => trim($t))
            ->filter()
            ->unique()
            ->values();

        if ($teeth->isEmpty()) {
            return;
        }

        $workType = $lc['work_subtype'] ?? $lc['work_category'] ?? 'Other';

        foreach ($teeth as $i => $tooth) {
            $labCase->items()->create([
                'tooth_number' => $tooth,
                'work_type'    => $workType,
                'sort_order'   => $i,
            ]);
        }
    }

    /**
     * Save visit items to treatment_visit_items and fire a billing prompt.
     */
    private function saveVisitItems(TreatmentVisit $visit, array $items): void
    {
        foreach ($items as $row) {
            $visit->visitItems()->create([
                'patient_id'             => $visit->patient_id,
                'treatment_plan_item_id' => $row['treatment_plan_item_id'] ?? null,
                'treatment_name'         => $row['treatment_name'],
                'material_option'        => $row['material_option'] ?? null,
                'tooth_number'           => $row['tooth_number'] ?? null,
                'suggested_price'        => $row['suggested_price'] ?? 0,
                'billing_status'         => 'pending',
                'notes'                  => $row['notes'] ?? null,
                // Repeat-work tracking
                'is_repeat'               => !empty($row['is_repeat']),
                'repeat_reason'           => !empty($row['is_repeat']) ? ($row['repeat_reason'] ?? null) : null,
                'repeat_of_visit_item_id' => !empty($row['is_repeat']) ? ($row['repeat_of_visit_item_id'] ?? null) : null,
            ]);
        }

        // Build a human-readable description for the front desk
        $parts = collect($items)->map(function ($i) {
            $label = $i['treatment_name'];
            if (!empty($i['material_option'])) $label .= ' (' . $i['material_option'] . ')';
            if (!empty($i['tooth_number']))    $label .= ' — Tooth ' . $i['tooth_number'];
            return $label;
        });

        BillingPrompt::create([
            'patient_id'   => $visit->patient_id,
            'trigger_type' => 'treatment_visit',
            'trigger_id'   => $visit->id,
            'description'  => 'Bill for: ' . $parts->join(', '),
            'status'       => 'pending',
            'created_by'   => Auth::id(),
        ]);
    }

    /**
     * Normalise a visit to the array shape the web JS (and now the API) expects.
     */
    public function format(TreatmentVisit $v): array
    {
        return [
            'id'                  => $v->id,
            'appointment_id'      => $v->appointment_id,
            'visit_date'          => $v->visit_date->format('Y-m-d'),
            'visit_type'          => $v->visit_type,
            'status'              => $v->status,
            'doctor_id'           => $v->doctor_id,
            'doctor_name'         => $v->doctor?->name,
            'treatment_plan_id'   => $v->treatment_plan_id,
            'treatment_name'      => $v->treatment_name,
            'current_stage'       => $v->current_stage,
            'completed_stages'    => $v->completed_stages ?? [],
            'tooth_number'        => $v->tooth_number,
            'notes'               => $v->notes,
            'chief_complaint'     => $v->chief_complaint,
            'next_visit_date'     => $v->next_visit_date?->format('Y-m-d'),
            'next_visit_type'     => $v->next_visit_type,
            // RCT
            'rct_num_canals'         => $v->rct_num_canals,
            'rct_canal_lengths'      => $v->rct_canal_lengths ?? [],
            'rct_file_type'          => $v->rct_file_type,
            'rct_irrigant'           => $v->rct_irrigant,
            'rct_obturation_method'  => $v->rct_obturation_method,
            // Implant
            'impl_brand'             => $v->impl_brand,
            'impl_size'              => $v->impl_size,
            'impl_torque'            => $v->impl_torque,
            'impl_graft_used'        => $v->impl_graft_used,
            'impl_graft_brand'       => $v->impl_graft_brand,
            'impl_membrane'          => $v->impl_membrane,
            'impl_healing_collar'    => $v->impl_healing_collar,
            'implant_fixture_catalog_id' => $v->implantPlacement?->implant_catalog_id,
            'implant_lot_number'         => $v->implantPlacement?->lot_number,
            'implant_components_used'    => $v->implantPlacement
                ? StockMovement::where('reference_type', TreatmentVisit::class)
                    ->where('reference_id', $v->id)
                    ->pluck('inventory_item_id')
                    ->flatMap(fn($itemId) => ImplantCatalog::where('inventory_item_id', $itemId)
                        ->where('id', '!=', $v->implantPlacement->implant_catalog_id)
                        ->pluck('id'))
                    ->values()->all()
                : [],
            // Filling
            'fill_material'          => $v->fill_material,
            'fill_shade'             => $v->fill_shade,
            // Scaling
            'scale_quadrants'        => $v->scale_quadrants,
            'scale_method'           => $v->scale_method,
            // Extraction
            'ext_type'               => $v->ext_type,
            'ext_socket'             => $v->ext_socket,
            'ext_suture'             => $v->ext_suture,
            // Crown
            'crown_type'             => $v->crown_type,
            'crown_shade'            => $v->crown_shade,
            'crown_impression'       => $v->crown_impression,
            'crown_temp_placed'      => $v->crown_temp_placed,
            // Prescription
            'prescription_drugs'         => $v->prescription_drugs ?? [],
            'prescription_instructions'  => $v->prescription_instructions ?? [],
            'prescription_custom_notes'  => $v->prescription_custom_notes,
            // F2: visit items for billing
            'visit_items' => ($v->relationLoaded('visitItems') ? $v->visitItems : collect())->map(fn($i) => [
                'id'                     => $i->id,
                'treatment_plan_item_id' => $i->treatment_plan_item_id,
                'treatment_name'         => $i->treatment_name,
                'material_option'        => $i->material_option,
                'tooth_number'           => $i->tooth_number,
                'suggested_price'        => (float)$i->suggested_price,
                'billing_status'         => $i->billing_status,
                'notes'                  => $i->notes,
                'is_repeat'              => (bool)$i->is_repeat,
                'repeat_reason'          => $i->repeat_reason,
                'repeat_of_visit_item_id'=> $i->repeat_of_visit_item_id,
            ])->values()->all(),
            '_isNew' => false,
        ];
    }
}
