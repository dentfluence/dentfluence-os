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
    PrescriptionOverride,
    RxDrug,
    RxDrugCategory,
    RxTemplate,
    RxFoodInstruction,
    RxDoseTemplate,
    RxDurationTemplate,
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
    // ─────────────────────────────────────────────────────────────────────────

    public function create(Request $request, Patient $patient)
    {
        // Pre-load helpers for the form dropdowns
        $foodInstructions  = RxFoodInstruction::orderBy('label')->get();
        $doseTemplates     = RxDoseTemplate::orderBy('name')->get();      // column is 'name'
        $durationTemplates = RxDurationTemplate::orderBy('label')->get(); // column is 'label'
        $templates         = RxTemplate::where('is_active', true)->orderBy('name')->get();

        // Optional: load from a visit or consultation
        $visitId        = $request->query('visit_id');
        $consultationId = $request->query('consultation_id');

        // Pre-fill diagnosis/complaint from patient record
        $prescription = new Prescription([
            'patient_id'       => $patient->id,
            'visit_id'         => $visitId,
            'consultation_id'  => $consultationId,
            'chief_complaint'  => $patient->chief_complaint,
            'language'         => 'en',
            'status'           => 'draft',
        ]);

        return view('prescriptions.form', compact(
            'patient', 'prescription',
            'foodInstructions', 'doseTemplates', 'durationTemplates', 'templates',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE QUICK  POST /patients/{patient}/prescriptions/quick
    // Accepts JSON from the universal <x-prescription-panel> component.
    // Maps {drug, sos, morn, noon, night, duration, unit} → PrescriptionItem.
    // ─────────────────────────────────────────────────────────────────────────

    public function storeQuick(Request $request, Patient $patient)
    {
        $drugs  = json_decode($request->input('prescriptions_data', '[]'), true) ?: [];
        $instrs = json_decode($request->input('instructions_data',  '[]'), true) ?: [];

        // Build general_instructions from selected chips + free-text note
        $instrText = implode('; ', array_filter((array) $instrs));
        $noteText  = trim($request->input('prescription_notes', ''));
        $general   = implode("\n", array_filter([$instrText, $noteText]));

        DB::transaction(function () use ($drugs, $general, $request, $patient, &$prescription) {
            $prescription = Prescription::create([
                'prescription_number'  => Prescription::generateNumber(),
                'patient_id'           => $patient->id,
                'prescribed_by'        => Auth::id(),
                'chief_complaint'      => $request->input('chief_complaint'),
                'diagnosis'            => $request->input('diagnosis'),
                'general_instructions' => $general ?: null,
                'language'             => 'en',
                'source'               => $request->input('source', Prescription::SOURCE_VISIT),
                'status'               => Prescription::STATUS_DRAFT,
            ]);

            foreach ($drugs as $i => $row) {
                if (empty($row['drug'])) continue;

                $item = new PrescriptionItem([
                    'prescription_id' => $prescription->id,
                    'drug_name'       => $row['drug'],
                    'morning'         => !empty($row['morn'])  ? 1.0 : 0.0,
                    'afternoon'       => !empty($row['noon'])  ? 1.0 : 0.0,
                    'night'           => !empty($row['night']) ? 1.0 : 0.0,
                    'is_sos'          => !empty($row['sos']),
                    'duration'        => (int) ($row['duration'] ?? 0),
                    'duration_unit'   => $row['unit'] ?? 'days',
                    'sort_order'      => $i,
                ]);
                $item->quantity = $item->calculateQuantity();
                $item->save();
            }

            $this->audit($prescription, 'created', 'Quick prescription from patient profile');
        });

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Prescription saved: ' . ($prescription->prescription_number ?? ''))
            ->with('active_tab', 'prescriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE  POST /patients/{patient}/prescriptions
    // ─────────────────────────────────────────────────────────────────────────

    public function store(Request $request, Patient $patient)
    {
        $validated = $this->validatePrescription($request);

        DB::transaction(function () use ($validated, $patient, $request, &$prescription) {
            // 1. Create header
            $prescription = Prescription::create([
                'prescription_number'  => Prescription::generateNumber(),
                'patient_id'           => $patient->id,
                'visit_id'             => $validated['visit_id'] ?? null,
                'consultation_id'      => $validated['consultation_id'] ?? null,
                'prescribed_by'        => Auth::id(),
                'diagnosis'            => $validated['diagnosis'] ?? null,
                'chief_complaint'      => $validated['chief_complaint'] ?? null,
                'follow_up_date'       => $validated['follow_up_date'] ?? null,
                'general_instructions' => $validated['general_instructions'] ?? null,
                'language'             => $validated['language'] ?? 'en',
                'source'               => $validated['source'] ?? Prescription::SOURCE_CONSULTATION,
                'status'               => Prescription::STATUS_DRAFT,
            ]);

            // 2. Save items
            $this->syncItems($prescription, $validated['items'] ?? []);

            // 3. Save any CDSS overrides the user acknowledged
            $rawOverrides = $request->input('overrides', []);
            if (is_string($rawOverrides)) {
                $rawOverrides = json_decode($rawOverrides, true) ?? [];
            }
            $this->syncOverrides($prescription, $rawOverrides);

            // 4. Audit
            $this->audit($prescription, 'created');

            // 5. Auto-finalize if requested
            if ($request->boolean('finalize')) {
                $this->doFinalize($prescription);
            }
        });

        $msg = isset($prescription) && $prescription->isLocked()
            ? 'Prescription issued: ' . $prescription->prescription_number
            : 'Draft saved: ' . $prescription->prescription_number;

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', $msg)
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
        // Cancelled prescriptions cannot be edited at all
        abort_if($prescription->isCancelled(), 403, 'Cancelled prescriptions cannot be edited.');

        $prescription->load('items.drug');

        $foodInstructions  = RxFoodInstruction::orderBy('label')->get();
        $doseTemplates     = RxDoseTemplate::orderBy('name')->get();
        $durationTemplates = RxDurationTemplate::orderBy('label')->get();
        $templates         = RxTemplate::where('is_active', true)->orderBy('name')->get();

        return view('prescriptions.form', compact(
            'patient', 'prescription',
            'foodInstructions', 'doseTemplates', 'durationTemplates', 'templates',
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE  PUT /patients/{patient}/prescriptions/{prescription}
    // ─────────────────────────────────────────────────────────────────────────

    public function update(Request $request, Patient $patient, Prescription $prescription)
    {
        abort_if($prescription->isCancelled(), 403, 'Cancelled prescriptions cannot be edited.');

        $validated = $this->validatePrescription($request);

        $target = null; // will point to the prescription being saved (original or new version)

        DB::transaction(function () use ($validated, $prescription, $request, &$target) {

            if ($prescription->isLocked()) {
                // ── Version control path ──────────────────────────────────────
                // The original is already issued/printed/sent — create a new version
                // and mark the original as 'revised' so it's archived but traceable.

                $rootId = $prescription->parent_id ?? $prescription->id;

                $newVersion = $prescription->replicate([
                    'prescription_number', 'status',
                    'printed_at', 'print_count',
                    'whatsapp_sent_at',
                    'email_sent_at', 'email_sent_count',
                ]);
                $newVersion->prescription_number = Prescription::generateNumber();
                $newVersion->status              = Prescription::STATUS_DRAFT;
                $newVersion->version             = $prescription->version + 1;
                $newVersion->parent_id           = $rootId;

                // Apply the submitted edits to the new version
                $newVersion->visit_id             = $validated['visit_id'] ?? null;
                $newVersion->consultation_id      = $validated['consultation_id'] ?? null;
                $newVersion->diagnosis            = $validated['diagnosis'] ?? null;
                $newVersion->chief_complaint      = $validated['chief_complaint'] ?? null;
                $newVersion->follow_up_date       = $validated['follow_up_date'] ?? null;
                $newVersion->general_instructions = $validated['general_instructions'] ?? null;
                $newVersion->language             = $validated['language'] ?? 'en';
                $newVersion->source               = $validated['source'] ?? $prescription->source;
                $newVersion->save();

                $this->syncItems($newVersion, $validated['items'] ?? []);
                $rawOv = $request->input('overrides', []);
                if (is_string($rawOv)) { $rawOv = json_decode($rawOv, true) ?? []; }
                $this->syncOverrides($newVersion, $rawOv);

                // Archive the original
                $prescription->update(['status' => Prescription::STATUS_REVISED]);
                $this->audit($prescription, 'edited', 'Superseded by ' . $newVersion->prescription_number);
                $this->audit($newVersion, 'created', 'Version ' . $newVersion->version . ' of ' . $prescription->prescription_number);

                if ($request->boolean('finalize')) {
                    $this->doFinalize($newVersion);
                }

                $target = $newVersion;

            } else {
                // ── Draft in-place edit path ──────────────────────────────────
                $prescription->update([
                    'visit_id'             => $validated['visit_id'] ?? null,
                    'consultation_id'      => $validated['consultation_id'] ?? null,
                    'diagnosis'            => $validated['diagnosis'] ?? null,
                    'chief_complaint'      => $validated['chief_complaint'] ?? null,
                    'follow_up_date'       => $validated['follow_up_date'] ?? null,
                    'general_instructions' => $validated['general_instructions'] ?? null,
                    'language'             => $validated['language'] ?? 'en',
                    'source'               => $validated['source'] ?? $prescription->source,
                ]);

                $prescription->items()->delete();
                $this->syncItems($prescription, $validated['items'] ?? []);
                $rawOv2 = $request->input('overrides', []);
                if (is_string($rawOv2)) { $rawOv2 = json_decode($rawOv2, true) ?? []; }
                $this->syncOverrides($prescription, $rawOv2);
                $this->audit($prescription, 'edited');

                if ($request->boolean('finalize')) {
                    $this->doFinalize($prescription);
                }

                $target = $prescription;
            }
        });

        $msg = ($target && $target->isLocked())
            ? 'Prescription issued: ' . $target->prescription_number
            : 'Prescription updated: ' . ($target->prescription_number ?? '');

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', $msg)
            ->with('active_tab', 'prescriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FINALIZE  POST /patients/{patient}/prescriptions/{prescription}/finalize
    // ─────────────────────────────────────────────────────────────────────────

    public function finalize(Patient $patient, Prescription $prescription)
    {
        abort_if($prescription->isLocked(),    422, 'Already issued.');
        abort_if($prescription->isCancelled(), 422, 'Cannot issue a cancelled prescription.');
        abort_if($prescription->items()->count() === 0, 422, 'Cannot issue an empty prescription.');

        $this->doFinalize($prescription);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Prescription issued: ' . $prescription->prescription_number)
            ->with('active_tab', 'prescriptions');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REPEAT  POST /patients/{patient}/prescriptions/{prescription}/repeat
    //   Clones a finalized prescription back to draft so it can be tweaked.
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
            ->with('success', 'Draft copy created from ' . $prescription->prescription_number);
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

        if ($prescription->follow_up_date) {
            $lines[] = '';
            $lines[] = '📅 *Follow-up:* ' . \Carbon\Carbon::parse($prescription->follow_up_date)->format('d M Y');
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

    private function validatePrescription(Request $request): array
    {
        return $request->validate([
            'visit_id'             => 'nullable|integer',
            'consultation_id'      => 'nullable|integer',
            'source'               => 'nullable|in:consultation,visit,emergency_consultation,review_visit,post_operative_visit',
            'diagnosis'            => 'nullable|string|max:255',
            'chief_complaint'      => 'nullable|string|max:255',
            'follow_up_date'       => 'nullable|date',
            'general_instructions' => 'nullable|string|max:1000',
            'language'             => 'nullable|in:en,mr,hi',
            // Items array
            'items'                         => 'nullable|array',
            'items.*.drug_id'               => 'nullable|integer|exists:rx_drugs,id',
            'items.*.drug_name'             => 'required_with:items.*|string|max:255',
            'items.*.generic_name'          => 'nullable|string|max:255',
            'items.*.strength'              => 'nullable|string|max:100',
            'items.*.dosage_form'           => 'nullable|string|max:100',
            'items.*.route'                 => 'nullable|string|max:100',
            'items.*.dispensing_type'       => 'nullable|in:unit,pack,manual,volume',
            'items.*.unit_label'            => 'nullable|string|max:50',
            'items.*.morning'               => 'nullable|numeric|min:0',
            'items.*.afternoon'             => 'nullable|numeric|min:0',
            'items.*.night'                 => 'nullable|numeric|min:0',
            'items.*.is_sos'                => 'nullable|boolean',
            'items.*.duration'              => 'nullable|integer|min:1',
            'items.*.duration_unit'         => 'nullable|in:days,weeks,months',
            'items.*.quantity'              => 'nullable|integer|min:0',
            'items.*.quantity_manual'       => 'nullable|boolean',
            'items.*.food_advice'           => 'nullable|string|max:255',
            'items.*.instructions'          => 'nullable|string|max:500',
            'items.*.sort_order'            => 'nullable|integer',
        ]);
    }

    /**
     * Create PrescriptionItem rows from the validated items array.
     */
    private function syncItems(Prescription $prescription, array $items): void
    {
        foreach ($items as $i => $data) {
            // ── Snapshot dispensing info from drug master ─────────────────────
            // If the form passed a drug_id, pull the dispensing_type and
            // unit_label from the master so the snapshot is always accurate.
            $dispensingType = $data['dispensing_type'] ?? RxDrug::DISPENSING_UNIT;
            $unitLabel      = $data['unit_label'] ?? null;

            if (!empty($data['drug_id'])) {
                $drug = RxDrug::find($data['drug_id']);
                if ($drug) {
                    $dispensingType = $drug->dispensing_type ?? RxDrug::DISPENSING_UNIT;
                    $unitLabel      = $drug->unit_label;
                }
            }

            $item = new PrescriptionItem(array_merge($data, [
                'prescription_id' => $prescription->id,
                'sort_order'      => $data['sort_order'] ?? $i,
                'morning'         => (float)($data['morning'] ?? 0),
                'afternoon'       => (float)($data['afternoon'] ?? 0),
                'night'           => (float)($data['night'] ?? 0),
                'is_sos'          => (bool)($data['is_sos'] ?? false),
                'quantity_manual' => (bool)($data['quantity_manual'] ?? false),
                'dispensing_type' => $dispensingType,
                'unit_label'      => $unitLabel,
            ]));

            // Auto-calculate quantity based on dispensing type,
            // unless the dentist has manually entered a value.
            if (!$item->quantity_manual || !$item->quantity) {
                $item->quantity = $item->calculateQuantity();
            }

            $item->save();
        }
    }

    /**
     * Save CDSS override acknowledgements posted from the form.
     * Expected format: [{ drug_id, alert_type, alert_code, alert_message, override_reason }]
     */
    private function syncOverrides(Prescription $prescription, array $overrides): void
    {
        // Remove old overrides for this prescription (re-save fresh)
        PrescriptionOverride::where('prescription_id', $prescription->id)->delete();

        foreach ($overrides as $ov) {
            PrescriptionOverride::create([
                'prescription_id' => $prescription->id,
                'user_id'         => Auth::id(),
                'drug_id'         => $ov['drug_id'] ?? null,
                'alert_type'      => $ov['alert_type'],
                'alert_code'      => $ov['alert_code'] ?? null,
                'alert_message'   => $ov['alert_message'],
                'override_reason' => $ov['override_reason'] ?? null,
            ]);
        }

        if (count($overrides)) {
            $this->audit($prescription, 'override', 'Override acknowledged for ' . count($overrides) . ' alert(s).');
        }
    }

    private function doFinalize(Prescription $prescription): void
    {
        $prescription->update(['status' => Prescription::STATUS_ISSUED]);
        $this->audit($prescription, 'finalized'); // 'finalized' kept in audit log for medicolegal history
    }

    private function audit(Prescription $prescription, string $action, ?string $notes = null): void
    {
        PrescriptionAuditLog::create([
            'prescription_id' => $prescription->id,
            'user_id'         => Auth::id(),
            'action'          => $action,
            'notes'           => $notes,
        ]);
    }
}
