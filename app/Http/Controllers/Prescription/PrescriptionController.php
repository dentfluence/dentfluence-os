<?php

namespace App\Http\Controllers\Prescription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;
use App\Models\Prescription\{
    Prescription,
    PrescriptionItem,
    PrescriptionAuditLog,
    RxDrug,
};
use App\Services\Prescription\PrescriptionAlertService;
use App\Services\Relationship\CommunicationGuard;

class PrescriptionController extends Controller
{
    public function __construct(private PrescriptionAlertService $alertService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // GLOBAL INDEX  /prescriptions
    // Lists all prescriptions across all patients, like an invoice register.
    // ─────────────────────────────────────────────────────────────────────────

    public function globalIndex(Request $request)
    {
        $query = Prescription::with(['patient', 'prescribedBy'])
            ->withCount('items')
            ->withTrashed(); // include cancelled

        // Search by Rx number or patient name
        if ($search = $request->input('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('prescription_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by date range
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $prescriptions = $query->latest()->paginate(25)->withQueryString();

        // Summary counts for the stat cards
        $stats = [
            'total'   => Prescription::withTrashed()->count(),
            'draft'   => Prescription::where('status', 'draft')->count(),
            'issued'  => Prescription::whereIn('status', ['issued', 'printed', 'whatsapp_sent', 'email_sent'])->count(),
            'today'   => Prescription::whereDate('created_at', today())->count(),
        ];

        return view('prescriptions.global-index', compact('prescriptions', 'stats'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX  /patients/{patient}/prescriptions
    // ─────────────────────────────────────────────────────────────────────────

    public function index(Patient $patient)
    {
        $prescriptions = Prescription::forPatient($patient->id)
            ->with('prescribedBy')
            ->withTrashed()                 // show cancelled too
            ->latest()
            ->paginate(20);

        return view('prescriptions.index', compact('patient', 'prescriptions'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE  GET /patients/{patient}/prescriptions/create
    //   Thin wrapper page around the same quick-form partial used in the
    //   patient's Prescriptions tab — only needed here for entry points that
    //   aren't already inside that tab (e.g. "Write Prescription", or writing
    //   one from a Treatment Visit).
    // ─────────────────────────────────────────────────────────────────────────

    public function create(Request $request, Patient $patient)
    {
        $prescription = new Prescription([
            'patient_id'      => $patient->id,
            'visit_id'        => $request->query('visit_id'),
            'consultation_id' => $request->query('consultation_id'),
            'chief_complaint' => $patient->chief_complaint,
            'language'        => 'en',
        ]);

        return view('prescriptions.form', compact('patient', 'prescription'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE  POST /patients/{patient}/prescriptions
    //   Accepts the JSON payload from <x-prescription-panel> (used by both the
    //   inline tab form and the standalone create page). A prescription is
    //   live the moment it's saved — no draft/finalize step. It can always be
    //   edited again afterwards via update().
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request, Patient $patient)
    {
        $prescription = $this->saveQuickPrescription($request, $patient);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Prescription saved: ' . $prescription->prescription_number)
            ->with('active_tab', 'prescriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW  GET /patients/{patient}/prescriptions/{prescription}
    // ─────────────────────────────────────────────────────────────────────────

    public function show(Patient $patient, Prescription $prescription)
    {
        $prescription->load([
            'items.drug',
            'prescribedBy',
            'auditLogs.user',
            'overrides',
        ]);

        return view('prescriptions.show', compact('patient', 'prescription'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EDIT  GET /patients/{patient}/prescriptions/{prescription}/edit
    // ─────────────────────────────────────────────────────────────────────────

    public function edit(Patient $patient, Prescription $prescription)
    {
        abort_if($prescription->isCancelled(), 403, 'Cancelled prescriptions cannot be edited.');

        $prescription->load('items.drug');

        return view('prescriptions.form', compact('patient', 'prescription'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE  PUT /patients/{patient}/prescriptions/{prescription}
    //   Always edits in place — no version-branching for already-issued/sent
    //   prescriptions. Editing is always available (until cancelled), so
    //   there's no separate "revise" workflow to maintain.
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, Patient $patient, Prescription $prescription)
    {
        abort_if($prescription->isCancelled(), 403, 'Cancelled prescriptions cannot be edited.');

        DB::transaction(function () use ($request, $prescription) {
            $this->fillQuickHeader($prescription, $request);
            $prescription->status = Prescription::STATUS_ISSUED;
            $prescription->save();

            $prescription->items()->delete();
            $this->createQuickItems($prescription, $request);

            $this->audit($prescription, 'edited');
        });

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Prescription updated: ' . $prescription->prescription_number)
            ->with('active_tab', 'prescriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPEAT  POST /patients/{patient}/prescriptions/{prescription}/repeat
    //   Clones a prescription so it can be tweaked for a new visit without
    //   retyping everything. Lands on the same Edit screen; saving it there
    //   makes it live immediately (see UPDATE above).
    // ─────────────────────────────────────────────────────────────────────────

    public function repeat(Patient $patient, Prescription $prescription)
    {
        abort_unless($prescription->isLocked(), 422, 'Only issued prescriptions can be repeated.');

        $clone = null;
        DB::transaction(function () use ($prescription, $patient, &$clone) {
            $clone = $prescription->replicate([
                'prescription_number',
                'status',
                'printed_at', 'print_count',
                'whatsapp_sent_at',
                'email_sent_at', 'email_sent_count',
                // version + parent_id are reset below
                'version', 'parent_id',
            ]);
            $clone->prescription_number = Prescription::generateNumber();
            $clone->status              = Prescription::STATUS_DRAFT;
            $clone->repeated_from_id    = $prescription->id;
            $clone->version             = 1;
            $clone->parent_id           = null;
            $clone->save();

            foreach ($prescription->items as $item) {
                $newItem = $item->replicate(['prescription_id']);
                $newItem->prescription_id = $clone->id;
                $newItem->save();
            }

            $this->audit($clone, 'repeated', 'Repeated from ' . $prescription->prescription_number);
        });

        return redirect()
            ->route('patients.prescriptions.edit', [$patient, $clone])
            ->with('success', 'Copy created from ' . $prescription->prescription_number . ' — review and save.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CANCEL  DELETE /patients/{patient}/prescriptions/{prescription}
    // ─────────────────────────────────────────────────────────────────────────

    public function destroy(Request $request, Patient $patient, Prescription $prescription)
    {
        abort_if($prescription->status === 'cancelled', 422, 'Already cancelled.');

        $prescription->update(['status' => 'cancelled']);
        $prescription->delete(); // soft delete
        $this->audit($prescription, 'cancelled', $request->input('reason'));

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Prescription cancelled.')
            ->with('active_tab', 'prescriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CDSS CHECK  POST /api/prescriptions/check-alerts  (JSON)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Live CDSS alert check called from the form via fetch().
     * Payload: { patient_id, items: [{drug_id, drug_name, morning, afternoon, night, duration, duration_unit}] }
     */
    public function checkAlerts(Request $request)
    {
        $patient = Patient::findOrFail($request->input('patient_id'));
        $items   = $request->input('items', []);

        $alerts = $this->alertService->check($patient, $items);

        return response()->json(['alerts' => $alerts]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DRUG SEARCH  GET /api/rx/drugs/search  (JSON)
    // ─────────────────────────────────────────────────────────────────────────

    public function drugSearch(Request $request)
    {
        $term  = $request->get('q', '');
        $drugs = RxDrug::active()
            ->search($term)
            ->with(['generic', 'category', 'defaultFoodInstruction'])
            ->limit(15)
            ->get()
            ->map(fn($d) => [
                'id'                       => $d->id,
                'brand_name'               => $d->brand_name,
                'generic_name'             => $d->generic?->name,
                'category'                 => $d->category?->name,
                'strength'                 => $d->strength,
                'dosage_form'              => $d->dosage_form,
                'composition'              => $d->composition,
                'route'                    => $d->route?->name,
                // Dispensing
                'dispensing_type'          => $d->dispensing_type ?? RxDrug::DISPENSING_UNIT,
                'unit_label'               => $d->unit_label,
                'pack_size'                => $d->pack_size,
                'default_quantity'         => $d->defaultQuantityForForm(),
                // Dose defaults
                'default_dose'             => $d->default_dose,
                'adult_dose'               => $d->adult_dose,
                'pediatric_dose'           => $d->pediatric_dose,
                'default_duration'         => $d->default_duration,
                'default_duration_unit'    => $d->default_duration_unit ?? 'days',
                'food_advice'              => $d->defaultFoodInstruction?->label,
                'default_instructions'     => $d->default_instructions,
                // Safety
                'duplicate_molecule_group' => $d->duplicate_molecule_group,
                'antibiotic_class'         => $d->antibiotic_class,
                'max_daily_dose'           => $d->max_daily_dose,
                'pregnancy_category'       => $d->pregnancy_category,
                'allergy_tags'             => $d->allergy_tags ?? [],
                'interaction_tags'         => $d->interaction_tags ?? [],
            ]);

        return response()->json($drugs);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRINT  GET /patients/{patient}/prescriptions/{prescription}/print
    // Opens a clean, print-optimised page. JS auto-triggers window.print().
    // ─────────────────────────────────────────────────────────────────────────

    public function printView(Patient $patient, Prescription $prescription)
    {
        $prescription->load(['prescribedBy', 'items.drug', 'patient']);

        // Update status to 'printed' if it was issued
        if ($prescription->status === Prescription::STATUS_ISSUED) {
            $prescription->update([
                'status'     => Prescription::STATUS_PRINTED,
                'printed_at' => now(),
                'print_count'=> ($prescription->print_count ?? 0) + 1,
            ]);
        } elseif ($prescription->status === Prescription::STATUS_PRINTED) {
            $prescription->increment('print_count');
            $prescription->update(['printed_at' => now()]);
        }

        return view('prescriptions.print', compact('patient', 'prescription'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF  GET /patients/{patient}/prescriptions/{prescription}/pdf
    // Returns the same print view — user saves as PDF via browser print dialog.
    // ─────────────────────────────────────────────────────────────────────────

    public function downloadPdf(Patient $patient, Prescription $prescription)
    {
        // Reuse the same print view; browser Print → Save as PDF does the job.
        return $this->printView($patient, $prescription);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WHATSAPP SEND  POST /patients/{patient}/prescriptions/{prescription}/whatsapp-send
    // Marks status as whatsapp_sent and returns the wa.me URL for the front-end
    // to open in a new tab. No third-party API required.
    // ─────────────────────────────────────────────────────────────────────────

    public function sendWhatsApp(Patient $patient, Prescription $prescription)
    {
        abort_unless($prescription->isFinalized(), 422, 'Only issued prescriptions can be sent via WhatsApp.');

        // ── Phase 4: DPDP consent gate (closes a live bypass) ────────────────
        // This used to build the wa.me link with zero consent check. It now
        // reuses the same PatientConsent/ConsentPurpose lookup the real
        // WhatsApp Cloud API send path uses (CommunicationGuard::hasWhatsAppConsent).
        // Gated behind guard.consent_required (same flag Phase 0 declared for
        // "no-consent blocks") so this stays a no-op — logged only — until you
        // flip it, exactly like every other Guard rule. That keeps today's
        // Prescription-send behaviour unchanged unless you deliberately enable
        // enforcement (e.g. after confirming ConsentPurposeSeeder has run).
        $consent = app(CommunicationGuard::class)->hasWhatsAppConsent($patient, 'service');
        if (! $consent['allowed']) {
            \Illuminate\Support\Facades\Log::info('Prescription WhatsApp send blocked by consent check (shadow unless guard.consent_required is on)', [
                'patient_id'     => $patient->id,
                'prescription_id'=> $prescription->id,
                'reason'         => $consent['reason'],
            ]);

            if (\App\Support\Features\Feature::enabled('guard.consent_required')) {
                return response()->json([
                    'success' => false,
                    'message' => $consent['reason'] ?? 'WhatsApp consent required before sending.',
                ], 422);
            }
        }
        // ──────────────────────────────────────────────────────────────────

        // ── Phase 4: Do-Not-Contact + channel eligibility ────────────────────
        // Only runs if this patient is linked to a Master Relationship
        // (identity.link_patient backfill/linking — unlinked patients have
        // nothing to check against, so they pass through unaffected).
        // Deliberately uses the FOCUSED check, not the full Guard decide()
        // pipeline — that also runs frequency/quiet-hours/birthday rules meant
        // for batch/automated contact, not a doctor-requested prescription send.
        // Gated behind guard.full_8factor, same shadow-log-then-enforce pattern.
        if ($patient->relationship_id) {
            $guardCheck = app(CommunicationGuard::class)
                ->checkDoNotContactAndChannel($patient->relationship_id, 'whatsapp');

            if (! $guardCheck['allowed']) {
                \Illuminate\Support\Facades\Log::info('Prescription WhatsApp send blocked by CommunicationGuard (shadow unless guard.full_8factor is on)', [
                    'patient_id'      => $patient->id,
                    'prescription_id' => $prescription->id,
                    'reason'          => $guardCheck['reason'],
                ]);

                if (\App\Support\Features\Feature::enabled('guard.full_8factor')) {
                    return response()->json([
                        'success' => false,
                        'message' => match ($guardCheck['reason']) {
                            'do_not_contact'     => 'This patient has asked not to be contacted.',
                            'channel_ineligible'  => 'No phone number on file for WhatsApp.',
                            default               => 'This message was blocked by the communication guard.',
                        },
                    ], 422);
                }
            }
        }
        // ──────────────────────────────────────────────────────────────────

        // Load items if not already loaded
        $prescription->loadMissing(['items', 'prescribedBy']);

        // Mark as whatsapp_sent (allow re-sends — just refresh the timestamp)
        $prescription->update([
            'status'           => Prescription::STATUS_WHATSAPP_SENT,
            'whatsapp_sent_at' => now(),
        ]);
        $this->audit($prescription, 'whatsapp_sent');

        // Build the wa.me deep-link URL
        $phone   = preg_replace('/[^0-9]/', '', $patient->phone ?? '');
        // Prepend country code 91 (India) if 10-digit mobile number
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        $message = $this->buildWhatsAppMessage($prescription, $patient);
        $url     = $phone
            ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($message)
            : 'https://wa.me/?text=' . rawurlencode($message); // no phone → generic share

        return response()->json([
            'success' => true,
            'url'     => $url,
            'phone'   => $phone,
        ]);
    }

    /**
     * Build the WhatsApp message text for a prescription.
     * Uses WhatsApp bold (*text*) for headings.
     */
    private function buildWhatsAppMessage(Prescription $prescription, Patient $patient): string
    {
        $clinicName = config('app.clinic_name', 'Dental Clinic');
        $lines      = [];

        $lines[] = "🦷 *{$clinicName}*";
        $lines[] = "Rx: *{$prescription->prescription_number}*";
        $lines[] = "Patient: {$patient->name}";
        $lines[] = "Date: " . $prescription->created_at->format('d M Y');
        $lines[] = "Doctor: Dr. " . ($prescription->prescribedBy?->name ?? '—');
        $lines[] = '';
        $lines[] = '*Medications:*';

        foreach ($prescription->items as $i => $item) {
            // Dose string: morning-afternoon-night
            $parts = [
                $item->morning   ? (string) $item->morning   : '0',
                $item->afternoon ? (string) $item->afternoon : '0',
                $item->night     ? (string) $item->night     : '0',
            ];
            $doseStr = implode('-', $parts);

            $dur = $item->duration
                ? " × {$item->duration} " . ($item->duration_unit ?? 'days')
                : '';
            $qty = $item->quantity ? " (Total: {$item->quantity})" : '';
            $sos = $item->is_sos ? ' ⚠️ _SOS only_' : '';

            $name = $item->drug_name . ($item->strength ? " {$item->strength}" : '');
            $lines[] = ($i + 1) . ". {$name} — {$doseStr}{$dur}{$qty}{$sos}";

            if ($item->food_advice) {
                $lines[] = "   🍽 {$item->food_advice}";
            }
        }

        if ($prescription->general_instructions) {
            $lines[] = '';
            $lines[] = '*Instructions:*';
            $lines[] = $prescription->general_instructions;
        }

        if ($prescription->follow_up_date || $prescription->follow_up_after_days) {
            $lines[] = '';
            $lines[] = '📅 *Follow-up:* ' . $prescription->followUpLabel();
        }

        $lines[] = '';
        $lines[] = '_This prescription was generated by ' . $clinicName . '._';

        return implode("\n", $lines);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function checkRepeat(Request $request): \Illuminate\Http\JsonResponse
    {
        $patientId       = $request->input('patient_id');
        $moleculeGroup   = $request->input('molecule_group');
        $antibioticClass = $request->input('antibiotic_class');
        $drugId          = $request->input('drug_id');

        // Nothing to check without identifiers
        if (!$patientId || (!$moleculeGroup && !$antibioticClass && !$drugId)) {
            return response()->json(['warning' => false, 'message' => null, 'days_ago' => null]);
        }

        // Look for the same drug prescribed in the last 90 days
        $query = PrescriptionItem::query()
            ->whereHas('prescription', fn($q) => $q
                ->where('patient_id', $patientId)
                ->whereNotIn('status', [Prescription::STATUS_CANCELLED, Prescription::STATUS_REVISED])
                ->where('created_at', '>=', now()->subDays(90))
            );

        if ($drugId) {
            $query->where('drug_id', $drugId);
        } elseif ($moleculeGroup) {
            $query->whereHas('drug', fn($d) => $d->where('duplicate_molecule_group', $moleculeGroup));
        } elseif ($antibioticClass) {
            $query->whereHas('drug', fn($d) => $d->where('antibiotic_class', $antibioticClass));
        }

        $last = $query->with('prescription')->latest()->first();

        if (!$last) {
            return response()->json(['warning' => false, 'message' => null, 'days_ago' => null]);
        }

        $daysAgo = (int) now()->diffInDays($last->prescription->created_at);
        $label   = $last->drug_name ?? 'this medication';

        return response()->json([
            'warning'  => true,
            'days_ago' => $daysAgo,
            'message'  => "Patient received {$label} {$daysAgo} day(s) ago. Review before repeating.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build + persist a brand-new Prescription (header + items) from the
     * quick form's POST payload. Status goes straight to ISSUED — there is
     * no draft/finalize step; it's live the moment it's saved, and can be
     * opened again in Edit at any time.
     */
    private function saveQuickPrescription(Request $request, Patient $patient): Prescription
    {
        $prescription = null;

        DB::transaction(function () use ($request, $patient, &$prescription) {
            $prescription = new Prescription([
                'prescription_number' => Prescription::generateNumber(),
                'patient_id'          => $patient->id,
                'visit_id'            => $request->input('visit_id') ?: null,
                'consultation_id'     => $request->input('consultation_id') ?: null,
                'prescribed_by'       => Auth::id(),
                'language'            => 'en',
                'source'              => $request->input('source', $request->filled('visit_id')
                    ? Prescription::SOURCE_VISIT
                    : Prescription::SOURCE_CONSULTATION),
                'status'              => Prescription::STATUS_ISSUED,
            ]);
            $this->fillQuickHeader($prescription, $request);
            $prescription->save();

            $this->createQuickItems($prescription, $request);

            $this->audit($prescription, 'created');
        });

        return $prescription;
    }

    /**
     * Apply the clinical-context fields shared by the create/edit quick form:
     * chief complaint, diagnosis, follow-up (date or "after N days" note),
     * and general instructions (built from the selected chips + free-text note).
     */
    private function fillQuickHeader(Prescription $prescription, Request $request): void
    {
        $instrs   = json_decode($request->input('instructions_data', '[]'), true) ?: [];
        $instrTxt = implode('; ', array_filter((array) $instrs));
        $note     = trim($request->input('prescription_notes', ''));

        $prescription->chief_complaint      = $request->input('chief_complaint') ?: null;
        $prescription->diagnosis            = $request->input('diagnosis') ?: null;
        $prescription->weight               = $request->input('weight') ?: null;
        $prescription->follow_up_date       = $request->input('follow_up_date') ?: null;
        $prescription->follow_up_after_days = $request->input('follow_up_after_days') ?: null;
        $prescription->general_instructions = implode("\n", array_filter([$instrTxt, $note])) ?: null;
    }

    /**
     * Decode the <x-prescription-panel> JSON payload and create PrescriptionItem
     * rows. The panel only sends the combined drug label, a form type, and
     * dosing — when a drug_id is present we look it up against the RxDrug
     * master so generic name, strength, and dosage form are snapshotted too
     * (used by the print view's "Tablet Flexon / composition" formatting).
     */
    private function createQuickItems(Prescription $prescription, Request $request): void
    {
        $rows = json_decode($request->input('prescriptions_data', '[]'), true) ?: [];

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
                'morning'         => !empty($row['morn'])  ? 1.0 : 0.0,
                'afternoon'       => !empty($row['noon'])  ? 1.0 : 0.0,
                'night'           => !empty($row['night']) ? 1.0 : 0.0,
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

    private function audit(Prescription $prescription, string $action, ?string $notes = null): void
    {
        PrescriptionAuditLog::create([
            'prescription_id' => $prescription->id,
            'user_id'         => Auth::id(),
            'action'  