<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\Treatment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TreatmentPlanController extends Controller
{
    // ── List all plans for a patient ─────────────────────────────────────────

    public function index(Patient $patient): JsonResponse
    {
        $plans = $patient->treatmentPlans()
            ->with(['items', 'creator'])
            ->latest()
            ->get()
            ->map(fn($p) => $this->formatPlan($p));

        return response()->json(['success' => true, 'plans' => $plans]);
    }

    // ── Create a new plan ────────────────────────────────────────────────────

    public function store(Request $request, Patient $patient): JsonResponse
    {
        $request->validate([
            'plan_name'        => ['nullable', 'string', 'max:100'],
            'plan_type'        => ['nullable', 'in:best,acceptable'],
            'consultation_id'  => ['nullable', 'exists:consultations,id'],
            'overall_disc_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.treatment_name' => ['required', 'string', 'max:150'],
            'items.*.tooth_number'   => ['nullable', 'string', 'max:20'],
            'items.*.unit_price'     => ['required', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
            'items.*.disc_pct'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.option_rank'    => ['nullable', 'in:best,acceptable,alternative'],
            'items.*.notes'          => ['nullable', 'string'],
        ]);

        $plan = DB::transaction(function () use ($request, $patient) {
            $plan = TreatmentPlan::create([
                'patient_id'       => $patient->id,
                'consultation_id'  => $request->consultation_id,
                'plan_name'        => $request->plan_name ?? ('Treatment Plan ' . chr(65 + $patient->treatmentPlans()->count())),
                'plan_type'        => $request->plan_type ?? 'best',
                'status'           => 'pending',
                'overall_disc_pct' => $request->overall_disc_pct ?? 0,
                'created_by'       => Auth::id(),
            ]);

            $this->syncItems($plan, $request->items, (float)($request->overall_disc_pct ?? 0));

            $plan->update(['total' => $plan->items()->sum('total')]);

            return $plan;
        });

        $plan->load(['items', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Treatment plan created.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Update plan (header fields + items) ──────────────────────────────────

    public function update(Request $request, TreatmentPlan $plan): JsonResponse
    {
        $request->validate([
            'plan_name'        => ['nullable', 'string', 'max:100'],
            'status'           => ['nullable', 'in:pending,ongoing,completed,cancelled'],
            'overall_disc_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items'            => ['nullable', 'array'],
            'items.*.id'             => ['nullable', 'exists:treatment_plan_items,id'],
            'items.*.treatment_name' => ['required_with:items', 'string', 'max:150'],
            'items.*.tooth_number'   => ['nullable', 'string', 'max:20'],
            'items.*.unit_price'     => ['required_with:items', 'numeric', 'min:0'],
            'items.*.units'          => ['nullable', 'integer', 'min:1'],
            'items.*.disc_pct'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.gst_pct'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.option_rank'    => ['nullable', 'in:best,acceptable,alternative'],
            'items.*.notes'          => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($request, $plan) {
            $plan->update([
                'plan_name'        => $request->plan_name        ?? $plan->plan_name,
                'status'           => $request->status           ?? $plan->status,
                'overall_disc_pct' => $request->overall_disc_pct ?? $plan->overall_disc_pct,
            ]);

            if ($request->has('items')) {
                // Remove items not in the new list
                $keptIds = collect($request->items)->pluck('id')->filter()->all();
                $plan->items()->whereNotIn('id', $keptIds)->delete();

                $this->syncItems($plan, $request->items, (float)($request->overall_disc_pct ?? $plan->overall_disc_pct));

                $plan->update(['total' => $plan->items()->sum('total')]);
            }
        });

        $plan->load(['items', 'creator']);

        return response()->json([
            'success' => true,
            'message' => 'Treatment plan updated.',
            'plan'    => $this->formatPlan($plan),
        ]);
    }

    // ── Delete a plan ────────────────────────────────────────────────────────

    public function destroy(TreatmentPlan $plan): JsonResponse
    {
        $plan->delete();

        return response()->json(['success' => true, 'message' => 'Treatment plan deleted.']);
    }

    // ── Delete a single item ─────────────────────────────────────────────────

    public function destroyItem(TreatmentPlanItem $item): JsonResponse
    {
        $plan = $item->plan;
        $item->delete();
        $plan->update(['total' => $plan->items()->sum('total')]);

        return response()->json(['success' => true, 'message' => 'Item removed.']);
    }

    // ── AI: suggest treatment based on consultation findings ─────────────────

    public function aiSuggest(Request $request, Patient $patient): JsonResponse
    {
        $request->validate([
            'chief_complaint'      => ['nullable', 'string'],
            'examination_notes'    => ['nullable', 'string'],
            'radiographic_notes'   => ['nullable', 'string'],
            'diagnosis'            => ['nullable', 'string'],
        ]);

        $text = strtolower(implode(' ', array_filter([
            $request->chief_complaint,
            $request->examination_notes,
            $request->radiographic_notes,
            $request->diagnosis,
        ])));

        if (!$text) {
            return response()->json(['success' => false, 'message' => 'No consultation data found.'], 422);
        }

        $groups = [];

        // ── RCT ──
        if (preg_match('/pulp|rct|root canal|periapical|abscess|irreversible|necrosis|apical/', $text)) {
            $groups[] = [
                'problem'      => 'Pulpal / Periapical Pathology',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Root Canal- Posterior', 'option_rank' => 'best',        'brief_reason' => 'Pulp involvement — save the tooth', 'unit_price' => $this->price('Root Canal- Posterior')],
                    ['treatment_name' => 'Extraction',            'option_rank' => 'acceptable',  'brief_reason' => 'If tooth not restorable',            'unit_price' => $this->price('Extraction')],
                ],
            ];
        }

        // ── Crown ──
        if (preg_match('/crown|cap|post rct|after rct|zirconia|pfm/', $text)) {
            $groups[] = [
                'problem'      => 'Crown Restoration',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Crown Zirconia',  'option_rank' => 'best',       'brief_reason' => 'Best aesthetics and strength', 'unit_price' => $this->price('Crown Zirconia')],
                    ['treatment_name' => 'Crown PFM',       'option_rank' => 'acceptable', 'brief_reason' => 'Cost-effective alternative',   'unit_price' => $this->price('Crown PFM')],
                ],
            ];
        }

        // ── Filling ──
        if (preg_match('/caries|cavity|decay|filling|composite|restoration|carries/', $text)) {
            $groups[] = [
                'problem'      => 'Carious Lesion',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Composite Filing- 1 Surface', 'option_rank' => 'best',       'brief_reason' => 'Tooth-coloured restoration', 'unit_price' => $this->price('Composite Filing- 1 Surface')],
                    ['treatment_name' => 'GIC Filling',                  'option_rank' => 'acceptable', 'brief_reason' => 'Economical option',          'unit_price' => $this->price('GIC Filling')],
                ],
            ];
        }

        // ── Scaling ──
        if (preg_match('/calculus|tartar|scaling|plaque|gingivitis|gum|periodontal|bleeding/', $text)) {
            $groups[] = [
                'problem'      => 'Periodontal Disease',
                'tooth_number' => '',
                'options'      => [
                    ['treatment_name' => 'Scaling & Polishing', 'option_rank' => 'best',       'brief_reason' => 'Remove calculus and plaque', 'unit_price' => $this->price('Scaling & Polishing')],
                    ['treatment_name' => 'Root Planing',        'option_rank' => 'acceptable', 'brief_reason' => 'If deep pockets present',    'unit_price' => $this->price('Root Planing')],
                ],
            ];
        }

        // ── Implant ──
        if (preg_match('/missing|implant|edentulous|gap|space|replacement/', $text)) {
            $groups[] = [
                'problem'      => 'Missing Tooth',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Implant',         'option_rank' => 'best',        'brief_reason' => 'Best long-term replacement',   'unit_price' => $this->price('Implant')],
                    ['treatment_name' => 'Bridge (PFM)',    'option_rank' => 'acceptable',  'brief_reason' => 'Fixed but involves adjacent teeth', 'unit_price' => $this->price('Bridge (PFM)')],
                ],
            ];
        }

        // ── Extraction ──
        if (preg_match('/extract|remove|wisdom|impacted|mobile|grade [23]/', $text)) {
            $groups[] = [
                'problem'      => 'Extraction Required',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Extraction',          'option_rank' => 'best',       'brief_reason' => 'Tooth not restorable',         'unit_price' => $this->price('Extraction')],
                    ['treatment_name' => 'Surgical Extraction', 'option_rank' => 'acceptable', 'brief_reason' => 'If impacted or complex case',  'unit_price' => $this->price('Surgical Extraction')],
                ],
            ];
        }

        // ── Sensitivity / Desensitization ──
        if (preg_match('/sensitiv|sensitivity|cold|hot|sweet|thermal/', $text)) {
            $groups[] = [
                'problem'      => 'Tooth Sensitivity',
                'tooth_number' => $this->extractTooth($text),
                'options'      => [
                    ['treatment_name' => 'Desensitization', 'option_rank' => 'best',       'brief_reason' => 'Non-invasive first line',   'unit_price' => $this->price('Desensitization')],
                    ['treatment_name' => 'Fluoride Therapy', 'option_rank' => 'acceptable', 'brief_reason' => 'Strengthen enamel',        'unit_price' => $this->price('Fluoride Therapy')],
                ],
            ];
        }

        if (empty($groups)) {
            $groups[] = [
                'problem'      => 'General Assessment',
                'tooth_number' => '',
                'options'      => [
                    ['treatment_name' => 'Consultation', 'option_rank' => 'best', 'brief_reason' => 'Further evaluation needed', 'unit_price' => $this->price('Consultation')],
                ],
            ];
        }

        return response()->json([
            'success'    => true,
            'suggestion' => [
                'diagnosis_summary' => 'Based on clinical findings — please review and adjust as needed.',
                'plan_groups'       => $groups,
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    private function extractTooth(string $text): string
    {
        if (preg_match('/\b([1-4][1-8]|[5-8][1-5])\b/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/lower left|ll/i', $text))  return '36';
        if (preg_match('/lower right|lr/i', $text)) return '46';
        if (preg_match('/upper left|ul/i', $text))  return '26';
        if (preg_match('/upper right|ur/i', $text)) return '16';
        return '';
    }

    private function price(string $name): float
    {
        static $prices = null;
        if ($prices === null) {
            $prices = \App\Models\Treatment::pluck('default_price', 'name')->toArray();
        }
        return (float)($prices[$name] ?? 0);
    }
    private function syncItems(TreatmentPlan $plan, array $items, float $overallDiscPct): void
    {
        foreach ($items as $idx => $row) {
            $item = isset($row['id'])
                ? TreatmentPlanItem::find($row['id']) ?? new TreatmentPlanItem()
                : new TreatmentPlanItem();

            $item->fill([
                'treatment_plan_id' => $plan->id,
                'tooth_number'      => $row['tooth_number']  ?? null,
                'treatment_name'    => $row['treatment_name'],
                'unit_price'        => (float)($row['unit_price']  ?? 0),
                'units'             => (int)($row['units']         ?? 1),
                'disc_pct'          => (float)($row['disc_pct']    ?? $overallDiscPct),
                'gst_pct'           => (float)($row['gst_pct']     ?? 0),
                'option_rank'       => $row['option_rank']  ?? 'best',
                'status'            => $row['status']        ?? 'pending',
                'notes'             => $row['notes']         ?? null,
                'sort_order'        => $idx,
                'aocp_applied'      => (bool)($row['aocp_applied'] ?? false),
            ]);

            $item->recalculate();
            $item->save();
        }
    }

    private function formatPlan(TreatmentPlan $plan): array
    {
        return [
            'id'               => $plan->id,
            'plan_name'        => $plan->plan_name,
            'plan_type'        => $plan->plan_type,
            'plan_type_label'  => $plan->plan_type_label,
            'status'           => $plan->status,
            'overall_disc_pct' => (float)$plan->overall_disc_pct,
            'total'            => (float)$plan->total,
            'consultation_id'  => $plan->consultation_id,
            'created_by_name'  => $plan->creator?->name,
            'created_at'       => $plan->created_at?->format('d M Y'),
            'items'            => $plan->items->map(fn($i) => [
                'id'             => $i->id,
                'tooth_number'   => $i->tooth_number,
                'treatment_name' => $i->treatment_name,
                'unit_price'     => (float)$i->unit_price,
                'units'          => (int)$i->units,
                'disc_pct'       => (float)$i->disc_pct,
                'disc_amount'    => (float)$i->disc_amount,
                'net_amount'     => (float)$i->net_amount,
                'gst_pct'        => (float)$i->gst_pct,
                'gst_amount'     => (float)$i->gst_amount,
                'total'          => (float)$i->total,
                'aocp_applied'   => (bool)$i->aocp_applied,
                'option_rank'    => $i->option_rank,
                'status'         => $i->status,
                'notes'          => $i->notes,
            ])->values()->all(),
        ];
    }
}
