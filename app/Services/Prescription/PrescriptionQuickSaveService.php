<?php

namespace App\Services\Prescription;

use App\Models\Patient;
use App\Models\Prescription\Prescription;
use App\Models\Prescription\PrescriptionAuditLog;
use App\Models\Prescription\PrescriptionItem;
use App\Models\Prescription\RxDrug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for turning a <x-prescription-panel> JSON payload
 * (prescriptions_data / instructions_data / a notes field) into real
 * Prescription + PrescriptionItem rows.
 *
 * Extracted from PrescriptionController so the exact same save logic can be
 * called from a second entry point (the Prescription section embedded in the
 * Consultation screen) without duplicating validation, item mapping, or
 * audit logging. See CEO Directive — Embed Existing Prescription Module into
 * Consultation (2026-07-31): "One business logic. Multiple entry points."
 */
class PrescriptionQuickSaveService
{
    /**
     * Create a brand-new Prescription (header + items) from a panel payload.
     * Status goes straight to ISSUED — there is no draft/finalize step; it's
     * live the moment it's saved, and can be edited again afterwards.
     *
     * $context lets a caller (e.g. ConsultationController) supply header
     * values that don't have their own form fields at that entry point
     * (consultation_id, chief_complaint, diagnosis, source) without those
     * fields needing to exist twice on screen.
     */
    public function createFromPanel(
        Request $request,
        Patient $patient,
        array $context = [],
        string $noteField = 'prescription_notes'
    ): Prescription {
        $prescription = null;

        DB::transaction(function () use ($request, $patient, $context, $noteField, &$prescription) {
            $prescription = new Prescription([
                'prescription_number' => Prescription::generateNumber(),
                'patient_id'          => $patient->id,
                'visit_id'            => $context['visit_id'] ?? ($request->input('visit_id') ?: null),
                'consultation_id'     => $context['consultation_id'] ?? ($request->input('consultation_id') ?: null),
                'prescribed_by'       => Auth::id(),
                'language'            => 'en',
                'source'              => $context['source'] ?? $request->input('source', $request->filled('visit_id')
                    ? Prescription::SOURCE_VISIT
                    : Prescription::SOURCE_CONSULTATION),
                'status'              => Prescription::STATUS_ISSUED,
            ]);
            $this->fillHeaderFromRequest($prescription, $request, $context, $noteField);
            $prescription->save();

            $this->createItemsFromRequest($prescription, $request);

            $this->audit($prescription, 'created');
        });

        return $prescription;
    }

    /**
     * Edit an existing Prescription in place from the same panel payload.
     * Always edits in place — no version-branching for already-issued/sent
     * prescriptions, matching PrescriptionController::update()'s behaviour.
     */
    public function updateFromPanel(
        Prescription $prescription,
        Request $request,
        array $context = [],
        string $noteField = 'prescription_notes'
    ): Prescription {
        DB::transaction(function () use ($prescription, $request, $context, $noteField) {
            $this->fillHeaderFromRequest($prescription, $request, $context, $noteField);
            $prescription->status = Prescription::STATUS_ISSUED;
            $prescription->save();

            $prescription->items()->delete();
            $this->createItemsFromRequest($prescription, $request);

            $this->audit($prescription, 'edited');
        });

        return $prescription;
    }

    /**
     * True if the panel's prescriptions_data payload has at least one row
     * with a drug entered. Callers use this to decide whether a Prescription
     * needs to be created/updated at all — an empty panel should be a no-op,
     * not an empty prescription record.
     */
    public function panelHasDrugRows(Request $request, string $prefix = 'prescriptions_data'): bool
    {
        $rows = json_decode($request->input($prefix, '[]'), true) ?: [];

        return collect($rows)->contains(fn ($row) => !empty($row['drug'] ?? null));
    }

    /**
     * Apply the clinical-context fields shared by every panel entry point:
     * chief complaint, diagnosis, weight, follow-up, and general instructions
     * (built from the selected chips + free-text note). Explicit $context
     * values win over same-named request fields, so a caller that already
     * knows the diagnosis (e.g. from the Consultation the panel is embedded
     * in) doesn't need a duplicate on-screen field to pass it through.
     */
    protected function fillHeaderFromRequest(Prescription $prescription, Request $request, array $context, string $noteField): void
    {
        $instrs   = json_decode($request->input('instructions_data', '[]'), true) ?: [];
        $instrTxt = implode('; ', array_filter((array) $instrs));
        $note     = trim((string) $request->input($noteField, ''));

        $prescription->chief_complaint      = $context['chief_complaint'] ?? ($request->input('chief_complaint') ?: null);
        $prescription->diagnosis            = $context['diagnosis'] ?? ($request->input('diagnosis') ?: null);
        $prescription->weight               = $context['weight'] ?? ($request->input('weight') ?: null);
        $prescription->follow_up_date       = $context['follow_up_date'] ?? ($request->input('follow_up_date') ?: null);
        $prescription->follow_up_after_days = $context['follow_up_after_days'] ?? ($request->input('follow_up_after_days') ?: null);
        $prescription->general_instructions = implode("\n", array_filter([$instrTxt, $note])) ?: null;
    }

    /**
     * Decode the <x-prescription-panel> JSON payload and create PrescriptionItem
     * rows. The panel only sends the combined drug label, a form type, and
     * dosing — when a drug_id is present we look it up against the RxDrug
     * master so generic name, strength, and dosage form are snapshotted too
     * (used by the print view's "Tablet Flexon / composition" formatting).
     */
    protected function createItemsFromRequest(Prescription $prescription, Request $request, string $prefix = 'prescriptions_data'): void
    {
        $rows = json_decode($request->input($prefix, '[]'), true) ?: [];

        foreach ($rows as $i => $row) {
            if (empty($row['drug'])) continue;

            $drug = !empty($row['drug_id']) ? RxDrug::find($row['drug_id']) : null;

            $item = new PrescriptionItem([
                'prescription_id' => $prescription->id,
                'drug_id'         => $drug?->id,
                'drug_name'       => $row['drug'],
                'generic_name'    => $drug?->generic?->name,
                'strength'        => $drug?->strength,
                'dosage_form'     => $drug?->dosage_form ?? ($row['form_type'] ?? null),
                'food_advice'     => $row['food'] ?: ($drug?->defaultFoodInstruction?->label),
                'morning'         => $this->doseValue($row['morn'] ?? null),
                'afternoon'       => $this->doseValue($row['noon'] ?? null),
                'night'           => $this->doseValue($row['night'] ?? null),
                'is_sos'          => !empty($row['sos']),
                'duration'        => (int) ($row['duration'] ?? 0),
                'duration_unit'   => $row['unit'] ?? 'days',
                'dispensing_type' => $drug?->dispensing_type ?? RxDrug::DISPENSING_UNIT,
                'unit_label'      => $drug?->unit_label,
                'sort_order'      => $i,
            ]);
            $item->quantity = $item->calculateQuantity();
            $item->save();
        }
    }

    /**
     * Normalise a panel dose value into a stored amount.
     * Solids send a boolean (checkbox) → 1 or 0; liquids send millilitres as a
     * number → stored as-is (e.g. 5 ml). Blank/false becomes 0.
     */
    protected function doseValue($value): float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }
        return is_numeric($value) ? (float) $value : 0.0;
    }

    public function audit(Prescription $prescription, string $action, ?string $notes = null): void
    {
        PrescriptionAuditLog::create([
            'prescription_id' => $prescription->id,
            'user_id'         => Auth::id(),
            'action'          => $action,
            'notes'           => $notes,
        ]);
    }
}
