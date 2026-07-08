<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\Prescription\{
    Prescription,
    PrescriptionItem,
    PrescriptionAuditLog,
    PrescriptionOverride,
    RxDrug,
    RxFoodInstruction,
    RxDoseTemplate,
    RxDurationTemplate,
    RxTemplate,
};
use App\Services\Prescription\PrescriptionAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PrescriptionController (API v1)
 * --------------------------------
 * Full mirror of the web prescription write-pad so mobile records are
 * IDENTICAL to web (same tables, same fields, same side-effects):
 *  - drug-master typeahead search
 *  - live CDSS alerts (allergy / duplicate / interaction / warnings)
 *  - override acknowledgement capture
 *  - draft → issue → print → whatsapp lifecycle (version control on edit)
 *  - repeat, cancel
 *  - dose/duration/food/Rx templates for the picker
 *
 * Branch-scoped via the patient. Reuses the same PrescriptionAlertService
 * and the same models/columns as the web controller.
 */
class PrescriptionController extends ApiController
{
    public function __construct(private PrescriptionAlertService $alertService) {}

    /** Quick patient-instruction chips (mirrors the web form chip list). */
    private const INSTRUCTION_CHIPS = [
        'Avoid hard/crunchy food for 24 hrs',
        'Do not rinse vigorously',
        'Keep the area clean',
        'Use warm saline rinse',
        'Apply ice pack for swelling',
        'Avoid alcohol & smoking',
        'Complete the full course of antibiotics',
        'Return if bleeding does not stop',
        'Avoid brushing near the area',
        'Take medications as prescribed',
    ];

    // ── Drug master search ───────────────────────────────────────────────────────

    /** Typeahead search across brand / generic / category / composition. */
    public function drugSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->get('q', ''));
        if (strlen($term) < 2) {
            return $this->success([], '');
        }

        $drugs = RxDrug::active()
            ->search($term)
            ->with(['generic', 'category', 'route', 'defaultFoodInstruction'])
            ->limit(15)
            ->get()
            ->map(fn ($d) => [
                'id'                       => $d->id,
                'brand_name'               => $d->brand_name,
                'generic_name'             => $d->generic?->name,
                'category'                 => $d->category?->name,
                'strength'                 => $d->strength,
                'dosage_form'              => $d->dosage_form,
                'composition'              => $d->composition,
                'route'                    => $d->route?->name,
                'dispensing_type'          => $d->dispensing_type ?? RxDrug::DISPENSING_UNIT,
                'unit_label'               => $d->unit_label,
                'pack_size'                => $d->pack_size,
                'default_quantity'         => $d->defaultQuantityForForm(),
                'default_dose'             => $d->default_dose,
                'adult_dose'               => $d->adult_dose,
                'pediatric_dose'           => $d->pediatric_dose,
                'default_duration'         => $d->default_duration,
                'default_duration_unit'    => $d->default_duration_unit ?? 'days',
                'food_advice'              => $d->defaultFoodInstruction?->label,
                'default_instructions'     => $d->default_instructions,
                'duplicate_molecule_group' => $d->duplicate_molecule_group,
                'antibiotic_class'         => $d->antibiotic_class,
                'max_daily_dose'           => $d->max_daily_dose,
                'pregnancy_category'       => $d->pregnancy_category,
                'is_controlled'            => (bool) $d->is_controlled,
                'allergy_tags'             => $d->allergy_tags ?? [],
                'interaction_tags'         => $d->interaction_tags ?? [],
            ]);

        return $this->success($drugs, '');
    }

    // ── CDSS ──────────────────────────────────────────────────────────────────────

    /** Live CDSS alert check. Payload: { patient_id, items:[{drug_id,...}] }. */
    public function checkAlerts(Request $request): JsonResponse
    {
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($request->input('patient_id'))
            ->first();
        if (! $patient) {
            return $this->error('Patient not found.', [], 404);
        }

        $alerts = $this->alertService->check($patient, $request->input('items', []));

        return $this->success(['alerts' => $alerts], '');
    }

    /** "Repeated recently?" check for a drug / molecule group / antibiotic class. */
    public function checkRepeat(Request $request): JsonResponse
    {
        $patientId       = $request->input('patient_id');
        $moleculeGroup   = $request->input('molecule_group');
        $antibioticClass = $request->input('antibiotic_class');
        $drugId          = $request->input('drug_id');

        if (! $patientId || (! $moleculeGroup && ! $antibioticClass && ! $drugId)) {
            return $this->success(['warning' => false, 'message' => null, 'days_ago' => null], '');
        }

        $query = PrescriptionItem::query()
            ->whereHas('prescription', fn ($q) => $q
                ->where('patient_id', $patientId)
                ->whereNotIn('status', [Prescription::STATUS_CANCELLED, Prescription::STATUS_REVISED])
                ->where('created_at', '>=', now()->subDays(90)));

        if ($drugId) {
            $query->where('drug_id', $drugId);
        } elseif ($moleculeGroup) {
            $query->whereHas('drug', fn ($d) => $d->where('duplicate_molecule_group', $moleculeGroup));
        } elseif ($antibioticClass) {
            $query->whereHas('drug', fn ($d) => $d->where('antibiotic_class', $antibioticClass));
        }

        $last = $query->with('prescription')->latest()->first();
        if (! $last) {
            return $this->success(['warning' => false, 'message' => null, 'days_ago' => null], '');
        }

        $daysAgo = (int) now()->diffInDays($last->prescription->created_at);
        $label   = $last->drug_name ?? 'this medication';

        return $this->success([
            'warning'  => true,
            'days_ago' => $daysAgo,
            'message'  => "Patient received {$label} {$daysAgo} day(s) ago. Review before repeating.",
        ], '');
    }

    // ── Form options (templates / food / chips) ────────────────────────────────────

    public function formOptions(Request $request): JsonResponse
    {
        $food = RxFoodInstruction::where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'code', 'label', 'label_mr', 'label_hi']);

        $dose = RxDoseTemplate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'morning', 'afternoon', 'night', 'is_sos']);

        $duration = RxDurationTemplate::where('is_active', true)
            ->orderBy('label')
            ->get(['id', 'label', 'value', 'unit']);

        $templates = RxTemplate::where('is_active', true)
            ->with(['items.drug:id,brand_name,generic_id,strength,dosage_form,dispensing_type,unit_label',
                    'items.foodInstruction:id,label'])
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'category'     => $t->category,
                'description'  => $t->description,
                'instructions' => $t->instructions,
                'items'        => $t->items->map(fn ($it) => [
                    'drug_id'       => $it->drug_id,
                    'drug_name'     => $it->drug?->brand_name,
                    'generic_name'  => $it->drug?->generic?->name,
                    'strength'      => $it->strength ?? $it->drug?->strength,
                    'dosage_form'   => $it->drug?->dosage_form,
                    'dispensing_type' => $it->drug?->dispensing_type ?? RxDrug::DISPENSING_UNIT,
                    'unit_label'    => $it->drug?->unit_label,
                    'route'         => $it->route,
                    'morning'       => (float) $it->morning,
                    'afternoon'     => (float) $it->afternoon,
                    'night'         => (float) $it->night,
                    'is_sos'        => (bool) $it->is_sos,
                    'duration'      => $it->duration,
                    'duration_unit' => $it->duration_unit ?? 'days',
                    'food_advice'   => $it->foodInstruction?->label,
                    'instructions'  => $it->instructions,
                ])->values(),
            ]);

        return $this->success([
            'food_instructions' => $food,
            'dose_templates'    => $dose,
            'duration_templates' => $duration,
            'templates'         => $templates,
            'instruction_chips' => self::INSTRUCTION_CHIPS,
        ], '');
    }

    // ── Store ───────────────────────────────────────────────────────────────────

    public function store(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        $validated = $this->validateRx($request);

        DB::transaction(function () use ($validated, $pt, $request, &$prescription) {
            $prescription = Prescription::create([
                'prescription_number'  => Prescription::generateNumber(),
                'patient_id'           => $pt->id,
                'visit_id'             => $validated['visit_id'] ?? null,
                'consultation_id'      => $validated['consultation_id'] ?? null,
                'prescribed_by'        => $request->user()->id,
                'diagnosis'            => $validated['diagnosis'] ?? null,
                'chief_complaint'      => $validated['chief_complaint'] ?? null,
                'follow_up_date'       => $validated['follow_up_date'] ?? null,
                'follow_up_after_days' => $validated['follow_up_after_days'] ?? null,
                'general_instructions' => $validated['general_instructions'] ?? null,
                'language'             => $validated['language'] ?? 'en',
                'source'               => $validated['source'] ?? Prescription::SOURCE_CONSULTATION,
                'status'               => Prescription::STATUS_DRAFT,
            ]);

            $this->syncItems($prescription, $validated['items'] ?? []);
            $this->syncOverrides($prescription, $this->overridesFrom($request));
            $this->audit($prescription, 'created');

            if ($request->boolean('finalize')) {
                $this->doFinalize($prescription);
            }
        });

        $msg = $prescription->isLocked()
            ? 'Prescription issued: ' . $prescription->prescription_number
            : 'Draft saved: ' . $prescription->prescription_number;

        return $this->success($this->detailPayload($this->reload($prescription)), $msg, 201);
    }

    // ── Update (version control on locked / in-place on draft) ────────────────────

    public function update(Request $request, $prescription): JsonResponse
    {
        $rx = $this->findRx($request, $prescription);
        if ($rx instanceof JsonResponse) return $rx;

        if ($rx->isCancelled()) {
            return $this->error('Cancelled prescriptions cannot be edited.', [], 403);
        }

        $validated = $this->validateRx($request);
        $target    = null;

        DB::transaction(function () use ($validated, $rx, $request, &$target) {
            if ($rx->isLocked()) {
                // Issued/printed/sent → create a new version, archive the original.
                $rootId = $rx->parent_id ?? $rx->id;

                $newVersion = $rx->replicate([
                    'prescription_number', 'status',
                    'printed_at', 'print_count',
                    'whatsapp_sent_at', 'email_sent_at', 'email_sent_count',
                ]);
                $newVersion->prescription_number = Prescription::generateNumber();
                $newVersion->status              = Prescription::STATUS_DRAFT;
                $newVersion->version             = $rx->version + 1;
                $newVersion->parent_id           = $rootId;
                $newVersion->visit_id            = $validated['visit_id'] ?? null;
                $newVersion->consultation_id     = $validated['consultation_id'] ?? null;
                $newVersion->diagnosis           = $validated['diagnosis'] ?? null;
                $newVersion->chief_complaint     = $validated['chief_complaint'] ?? null;
                $newVersion->follow_up_date      = $validated['follow_up_date'] ?? null;
                $newVersion->follow_up_after_days = $validated['follow_up_after_days'] ?? null;
                $newVersion->general_instructions = $validated['general_instructions'] ?? null;
                $newVersion->language            = $validated['language'] ?? 'en';
                $newVersion->source              = $validated['source'] ?? $rx->source;
                $newVersion->save();

                $this->syncItems($newVersion, $validated['items'] ?? []);
                $this->syncOverrides($newVersion, $this->overridesFrom($request));

                $rx->update(['status' => Prescription::STATUS_REVISED]);
                $this->audit($rx, 'edited', 'Superseded by ' . $newVersion->prescription_number);
                $this->audit($newVersion, 'created', 'Version ' . $newVersion->version . ' of ' . $rx->prescription_number);

                if ($request->boolean('finalize')) {
                    $this->doFinalize($newVersion);
                }
                $target = $newVersion;
            } else {
                // Draft → edit in place.
                $rx->update([
                    'visit_id'             => $validated['visit_id'] ?? null,
                    'consultation_id'      => $validated['consultation_id'] ?? null,
                    'diagnosis'            => $validated['diagnosis'] ?? null,
                    'chief_complaint'      => $validated['chief_complaint'] ?? null,
                    'follow_up_date'       => $validated['follow_up_date'] ?? null,
                    'follow_up_after_days' => $validated['follow_up_after_days'] ?? null,
                    'general_instructions' => $validated['general_instructions'] ?? null,
                    'language'             => $validated['language'] ?? 'en',
                    'source'               => $validated['source'] ?? $rx->source,
                ]);

                $rx->items()->delete();
                $this->syncItems($rx, $validated['items'] ?? []);
                $this->syncOverrides($rx, $this->overridesFrom($request));
                $this->audit($rx, 'edited');

                if ($request->boolean('finalize')) {
                    $this->doFinalize($rx);
                }
                $target = $rx;
            }
        });

        $msg = $target->isLocked()
            ? 'Prescription issued: ' . $target->prescription_number
            : 'Prescription updated: ' . $target->prescription_number;

        return $this->success($this->detailPayload($this->reload($target)), $msg);
    }

    // ── Finalize / repeat / cancel ────────────────────────────────────────────────

    public function finalize(Request $request, $prescription): JsonResponse
    {
        $rx = $this->findRx($request, $prescription);
        if ($rx instanceof JsonResponse) return $rx;

        if ($rx->isLocked())    return $this->error('Already issued.', [], 422);
        if ($rx->isCancelled()) return $this->error('Cannot issue a cancelled prescription.', [], 422);
        if ($rx->items()->count() === 0) return $this->error('Cannot issue an empty prescription.', [], 422);

        $this->doFinalize($rx);

        return $this->success($this->detailPayload($this->reload($rx)),
            'Prescription issued: ' . $rx->prescription_number);
    }

    public function repeat(Request $request, $prescription): JsonResponse
    {
        $rx = $this->findRx($request, $prescription);
        if ($rx instanceof JsonResponse) return $rx;

        if (! $rx->isLocked()) {
            return $this->error('Only issued prescriptions can be repeated.', [], 422);
        }

        $clone = null;
        DB::transaction(function () use ($rx, &$clone) {
            $clone = $rx->replicate([
                'prescription_number', 'status',
                'printed_at', 'print_count',
                'whatsapp_sent_at', 'email_sent_at', 'email_sent_count',
                'version', 'parent_id',
            ]);
            $clone->prescription_number = Prescription::generateNumber();
            $clone->status              = Prescription::STATUS_DRAFT;
            $clone->repeated_from_id    = $rx->id;
            $clone->version             = 1;
            $clone->parent_id           = null;
            $clone->save();

            foreach ($rx->items as $item) {
                $newItem = $item->replicate(['prescription_id']);
                $newItem->prescription_id = $clone->id;
                $newItem->save();
            }

            $this->audit($clone, 'repeated', 'Repeated from ' . $rx->prescription_number);
        });

        return $this->success($this->detailPayload($this->reload($clone)),
            'Draft copy created from ' . $rx->prescription_number, 201);
    }

    public function cancel(Request $request, $prescription): JsonResponse
    {
        $rx = $this->findRx($request, $prescription);
        if ($rx instanceof JsonResponse) return $rx;

        if ($rx->isCancelled()) {
            return $this->error('Already cancelled.', [], 422);
        }

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $rx->update(['status' => Prescription::STATUS_CANCELLED]);
        $rx->delete(); // soft delete
        $this->audit($rx, 'cancelled', $request->input('reason'));

        return $this->success($this->detailPayload($this->reload($rx)), 'Prescription cancelled.');
    }

    // ── Show (full detail for view / edit prefill / print) ────────────────────────

    public function show(Request $request, $prescription): JsonResponse
    {
        $rx = $this->findRx($request, $prescription);
        if ($rx instanceof JsonResponse) return $rx;

        return $this->success($this->detailPayload($rx, withClinic: true), '');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────────

    private function validateRx(Request $request): array
    {
        return $request->validate([
            'visit_id'             => 'nullable|integer',
            'consultation_id'      => 'nullable|integer',
            'source'               => 'nullable|in:consultation,visit,emergency_consultation,review_visit,post_operative_visit',
            'diagnosis'            => 'nullable|string|max:255',
            'chief_complaint'      => 'nullable|string|max:255',
            'follow_up_date'       => 'nullable|date',
            // Alternative to a fixed follow_up_date — "come back in N days"
            // (2026-07-06 web parity). Both may be set; Prescription::followUpLabel()
            // prefers the fixed date when present.
            'follow_up_after_days' => 'nullable|integer|min:1|max:365',
            'general_instructions' => 'nullable|string|max:1000',
            'language'             => 'nullable|in:en,mr,hi',
            'items'                   => 'nullable|array',
            'items.*.drug_id'         => 'nullable|integer|exists:rx_drugs,id',
            'items.*.drug_name'       => 'required_with:items.*|string|max:255',
            'items.*.generic_name'    => 'nullable|string|max:255',
            'items.*.strength'        => 'nullable|string|max:100',
            'items.*.dosage_form'     => 'nullable|string|max:100',
            'items.*.route'           => 'nullable|string|max:100',
            'items.*.dispensing_type' => 'nullable|in:unit,pack,manual,volume',
            'items.*.unit_label'      => 'nullable|string|max:50',
            'items.*.morning'         => 'nullable|numeric|min:0',
            'items.*.afternoon'       => 'nullable|numeric|min:0',
            'items.*.night'           => 'nullable|numeric|min:0',
            'items.*.is_sos'          => 'nullable|boolean',
            'items.*.duration'        => 'nullable|integer|min:1',
            'items.*.duration_unit'   => 'nullable|in:days,weeks,months',
            'items.*.quantity'        => 'nullable|integer|min:0',
            'items.*.quantity_manual' => 'nullable|boolean',
            'items.*.food_advice'     => 'nullable|string|max:255',
            'items.*.instructions'    => 'nullable|string|max:500',
            'items.*.sort_order'      => 'nullable|integer',
        ]);
    }

    /** Build PrescriptionItem rows, snapshotting dispensing info from the master. */
    private function syncItems(Prescription $prescription, array $items): void
    {
        foreach ($items as $i => $data) {
            $dispensingType = $data['dispensing_type'] ?? RxDrug::DISPENSING_UNIT;
            $unitLabel      = $data['unit_label'] ?? null;

            if (! empty($data['drug_id'])) {
                $drug = RxDrug::find($data['drug_id']);
                if ($drug) {
                    $dispensingType = $drug->dispensing_type ?? RxDrug::DISPENSING_UNIT;
                    $unitLabel      = $drug->unit_label;
                }
            }

            $item = new PrescriptionItem(array_merge($data, [
                'prescription_id' => $prescription->id,
                'sort_order'      => $data['sort_order'] ?? $i,
                'morning'         => (float) ($data['morning'] ?? 0),
                'afternoon'       => (float) ($data['afternoon'] ?? 0),
                'night'           => (float) ($data['night'] ?? 0),
                'is_sos'          => (bool) ($data['is_sos'] ?? false),
                'quantity_manual' => (bool) ($data['quantity_manual'] ?? false),
                'dispensing_type' => $dispensingType,
                'unit_label'      => $unitLabel,
            ]));

            if (! $item->quantity_manual || ! $item->quantity) {
                $item->quantity = $item->calculateQuantity();
            }

            $item->save();
        }
    }

    private function syncOverrides(Prescription $prescription, array $overrides): void
    {
        PrescriptionOverride::where('prescription_id', $prescription->id)->delete();

        foreach ($overrides as $ov) {
            PrescriptionOverride::create([
                'prescription_id' => $prescription->id,
                'user_id'         => auth()->id(),
                'drug_id'         => $ov['drug_id'] ?? null,
                'alert_type'      => $ov['alert_type'] ?? 'unknown',
                'alert_code'      => $ov['alert_code'] ?? null,
                'alert_message'   => $ov['alert_message'] ?? '',
                'override_reason' => $ov['override_reason'] ?? null,
            ]);
        }

        if (count($overrides)) {
            $this->audit($prescription, 'override', 'Override acknowledged for ' . count($overrides) . ' alert(s).');
        }
    }

    /** Normalize overrides input (array or JSON string) to an array. */
    private function overridesFrom(Request $request): array
    {
        $raw = $request->input('overrides', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?? [];
        }
        return is_array($raw) ? $raw : [];
    }

    private function doFinalize(Prescription $prescription): void
    {
        $prescription->update(['status' => Prescription::STATUS_ISSUED]);
        $this->audit($prescription, 'finalized');
    }

    private function audit(Prescription $prescription, string $action, ?string $notes = null): void
    {
        PrescriptionAuditLog::create([
            'prescription_id' => $prescription->id,
            'user_id'         => auth()->id(),
            'action'          => $action,
            'notes'           => $notes,
        ]);
    }

    /** Branch-checked fetch (with items + doctor + patient). Returns model or JsonResponse error. */
    private function findRx(Request $request, $id)
    {
        $rx = Prescription::with([
                'items' => fn ($q) => $q->orderBy('sort_order'),
                'prescribedBy:id,name',
                'patient',
                'overrides',
            ])
            ->withTrashed()
            ->whereKey($id)
            ->first();

        if (! $rx || ! $rx->patient ||
            (int) $rx->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Prescription not found.', [], 404);
        }
        return $rx;
    }

    private function reload(Prescription $rx): Prescription
    {
        return $rx->fresh([
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'prescribedBy:id,name',
            'patient',
            'overrides',
        ]);
    }

    private function detailPayload(Prescription $rx, bool $withClinic = false): array
    {
        $statusLabels = [
            'draft' => 'Draft', 'issued' => 'Issued', 'printed' => 'Printed',
            'whatsapp_sent' => 'WhatsApp Sent', 'email_sent' => 'Email Sent',
            'revised' => 'Revised', 'cancelled' => 'Cancelled',
        ];

        $out = [
            'id'                   => $rx->id,
            'number'               => $rx->prescription_number,
            'date'                 => $rx->created_at,
            'status'               => $rx->status,
            'status_label'         => $statusLabels[$rx->status] ?? ucfirst($rx->status),
            'source'               => $rx->source,
            'source_label'         => $rx->sourceLabel(),
            'diagnosis'            => $rx->diagnosis,
            'chief_complaint'      => $rx->chief_complaint,
            'general_instructions' => $rx->general_instructions,
            'follow_up_date'       => $rx->follow_up_date,
            'follow_up_after_days' => $rx->follow_up_after_days,
            'follow_up_label'      => $rx->followUpLabel(),
            'language'             => $rx->language ?? 'en',
            'doctor'               => $rx->prescribedBy?->name,
            'doctor_name'          => $rx->prescribedBy?->doctor_name ?? $rx->prescribedBy?->name,
            'print_count'          => (int) ($rx->print_count ?? 0),
            'printed_at'           => $rx->printed_at?->format('d M Y'),
            'version'              => (int) ($rx->version ?? 1),
            // UI capability flags
            'is_draft'             => $rx->isDraft(),
            'is_locked'            => $rx->isLocked(),
            'is_cancelled'         => $rx->isCancelled(),
            'can_edit'             => ! $rx->isCancelled(),
            'can_finalize'         => ! $rx->isLocked() && ! $rx->isCancelled() && $rx->items->count() > 0,
            'can_repeat'           => $rx->isLocked(),
            'can_cancel'           => ! $rx->isCancelled(),
            'items'                => $rx->items->map(fn ($it) => [
                'id'              => $it->id,
                'drug_id'         => $it->drug_id,
                'drug_name'       => $it->drug_name,
                'generic_name'    => $it->generic_name,
                'strength'        => $it->strength,
                'dosage_form'     => $it->dosage_form,
                'route'           => $it->route,
                'dispensing_type' => $it->dispensing_type,
                'unit_label'      => $it->unit_label,
                'morning'         => (float) $it->morning,
                'afternoon'       => (float) $it->afternoon,
                'night'           => (float) $it->night,
                'is_sos'          => (bool) $it->is_sos,
                'duration'        => $it->duration,
                'duration_unit'   => $it->duration_unit,
                'quantity'        => $it->quantity,
                'quantity_manual' => (bool) $it->quantity_manual,
                'food_advice'     => $it->food_advice,
                'instructions'    => $it->instructions,
                'sort_order'      => $it->sort_order,
            ])->values(),
            'overrides'            => $rx->overrides->map(fn ($o) => [
                'alert_type'      => $o->alert_type,
                'alert_code'      => $o->alert_code,
                'alert_message'   => $o->alert_message,
                'override_reason' => $o->override_reason,
            ])->values(),
        ];

        if ($withClinic) {
            $c = AppSetting::group('clinic');
            $out['clinic'] = [
                'name'    => $c['clinic_name'] ?? config('app.clinic_name', 'Dental Clinic'),
                'tagline' => $c['clinic_tagline'] ?? null,
                'address' => $c['clinic_address'] ?? null,
                'city'    => $c['clinic_city'] ?? null,
                'phone'   => $c['clinic_phone'] ?? null,
                'email'   => $c['clinic_email'] ?? null,
            ];
            $out['patient'] = [
                'name'          => $rx->patient->name,
                'patient_id'    => $rx->patient->patient_id,
                'age'           => $rx->patient->age_years,
                'gender'        => $rx->patient->gender,
                'phone'         => $rx->patient->phone,
                'medical_alert' => $rx->patient->medical_alert,
            ];
        }

        return $out;
    }
}
