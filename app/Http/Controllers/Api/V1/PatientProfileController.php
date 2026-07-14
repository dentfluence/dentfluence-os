<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\ClinicalFile;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Prescription\Prescription;
use App\Models\Wallet;
use App\Services\ClinicalLibrary\ClinicalFileUploadService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * PatientProfileController (API v1)
 * ---------------------------------
 * Read-only sub-resources that power the mobile Patient Profile tabs. Each
 * endpoint is branch-scoped and returns a lean, curated list (no heavy clinical
 * JSON blobs). The web Patient Profile keeps using PatientProfileService; this
 * is the mobile-friendly slice.
 *
 *   GET /api/v1/patients/{id}/consultations
 *   GET /api/v1/patients/{id}/treatment-plans
 *   GET /api/v1/patients/{id}/visits
 *   GET /api/v1/patients/{id}/lab-cases
 *   GET /api/v1/patients/{id}/prescriptions
 *   GET /api/v1/patients/{id}/invoices
 *   GET /api/v1/patients/{id}/wallet
 *   GET /api/v1/patients/{id}/documents
 *   POST /api/v1/patients/{id}/documents      — upload (writes to clinical_files)
 *   POST /api/v1/patients/{id}/clinical-files — mobile photo capture
 *   GET /api/v1/patients/{id}/notes
 *   GET /api/v1/patients/{id}/communications
 *   GET /api/v1/patients/{id}/memberships
 */
class PatientProfileController extends ApiController
{
    public function consultations(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = $p->consultations()
            ->orderByDesc('consultation_date')
            ->get()
            ->map(fn ($c) => [
                'id'              => $c->id,
                'date'            => $c->consultation_date,
                'status'          => $c->status,
                // consultation_type ('new'/'same_issue'/'minor_visit'/'emergency'/'coha')
                // — the mobile list/edit flow branches on this to know which
                // screen (and which API, for COHA) to open. visit_type is the
                // older legacy column, kept for backward compat.
                'type'            => $c->consultation_type,
                'visit_type'      => $c->visit_type,
                'chief_complaint' => $c->chief_complaint,
                'diagnosis'       => $c->provisional_diagnosis ?: $c->primary_diagnosis,
            ]);

        return $this->success($rows, '');
    }

    public function treatmentPlans(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = $p->treatmentPlans()
            ->orderByDesc('id')
            ->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'name'     => $t->plan_name,
                'status'   => $t->status,
                'type'     => $t->plan_type,
                'total'    => $t->total,
                'accepted' => $t->accepted_at !== null,
                'duration' => $t->estimated_duration,
                'visits'   => $t->visit_count,
                'aocp'     => (bool) $t->aocp,
            ]);

        return $this->success($rows, '');
    }

    public function visits(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = $p->treatmentVisits()
            ->with('doctor:id,name')
            ->orderByDesc('visit_date')
            ->get()
            ->map(fn ($v) => [
                'id'        => $v->id,
                'date'      => $v->visit_date,
                'status'    => $v->status,
                'procedure' => $v->procedure,
                'treatment' => $v->treatment_name,
                'tooth'     => $v->tooth_number,
                'cost'      => $v->cost,
                'paid'      => $v->amount_paid,
                'doctor'    => $v->doctor?->name,
            ]);

        return $this->success($rows, '');
    }

    public function labCases(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = $p->labCases()
            ->orderByDesc('id')
            ->get()
            ->map(fn ($l) => [
                'id'          => $l->id,
                'case_number' => $l->case_number,
                'category'    => $l->work_category,
                'subtype'     => $l->work_subtype,
                'status'      => $l->status,
                'priority'    => $l->priority,
                'sent'        => $l->sent_date,
                'expected'    => $l->expected_return_date,
                'cost'        => $l->lab_cost,
                'technician'  => $l->technician_name,
            ]);

        return $this->success($rows, '');
    }

    public function prescriptions(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = Prescription::where('patient_id', $p->id)
            ->with('prescribedBy:id,name')
            ->withCount('items')
            ->withTrashed()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($rx) => [
                'id'          => $rx->id,
                'number'      => $rx->prescription_number,
                'date'        => $rx->created_at,
                'status'      => $rx->status,
                'diagnosis'   => $rx->diagnosis,
                'doctor'      => $rx->prescribedBy?->name,
                'items_count' => (int) $rx->items_count,
                'source'      => $rx->source,
            ]);

        return $this->success($rows, '');
    }

    public function invoices(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = Invoice::where('patient_id', $p->id)
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn ($i) => [
                'id'      => $i->id,
                'number'  => $i->invoice_number,
                'date'    => $i->invoice_date,
                'total'   => $i->total_amount,
                'paid'    => $i->paid_amount,
                'balance' => $i->balance_due,
                'status'  => $i->status,
            ]);

        return $this->success($rows, '');
    }

    public function wallet(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $wallet = Wallet::forPatient($p->id);

        // Real ledger columns (direction/credit_type/source/notes) — the old
        // mapping read fields that don't exist on WalletTransaction, so the
        // mobile ledger rendered blank rows (fixed 2026-07-14). The legacy
        // 'type'/'description' keys are kept for older client builds.
        $tx = $wallet->transactions()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id'             => $t->id,
                'amount'         => (float) $t->amount,
                'direction'      => $t->direction,      // credit | debit
                'credit_type'    => $t->credit_type,    // permanent | promotional
                'source'         => $t->source,         // advance, invoice_debit, refund, …
                'invoice_number' => $t->invoice_number,
                'payment_mode'   => $t->payment_mode,
                'notes'          => $t->notes,
                'date'           => $t->created_at,
                // Legacy keys (pre-2026-07-14 client builds)
                'type'           => $t->direction,
                'description'    => $t->notes,
            ]);

        return $this->success([
            'balance_total'       => $wallet->balance_total,
            'balance_promotional' => $wallet->balance_promotional,
            'balance_permanent'   => $wallet->balance_permanent,
            'transactions'        => $tx,
        ], '');
    }

    /**
     * PUT /api/v1/patients/{patient}/clinical-files/{file}
     * Edit a clinical file's metadata — mirrors web
     * ClinicalFileController::update() exactly (2026-07-14 parity; mobile
     * could upload but never correct a wrong category/tooth/stage).
     */
    public function updateClinicalFile(Request $request, $patient, ClinicalFile $file): JsonResponse
    {
        $p = $this->find($request, $patient);

        if ($file->patient_id !== $p->id) {
            return $this->error('File does not belong to this patient.', [], 403);
        }

        $request->validate([
            'visit_id'                 => ['nullable', 'integer', 'exists:treatment_visits,id'],
            'procedure'                => ['nullable', 'string', 'max:255'],
            'treatment_category'       => ['nullable', \Illuminate\Validation\Rule::in(array_keys(ClinicalFile::TREATMENT_CATEGORIES))],
            'tooth_number'             => ['nullable', 'string', 'max:50'],
            'stage'                    => ['nullable', \Illuminate\Validation\Rule::in(ClinicalFile::STAGES)],
            'file_type'                => ['nullable', \Illuminate\Validation\Rule::in(ClinicalFile::FILE_TYPES)],
            'title'                    => ['nullable', 'string', 'max:255'],
            'notes'                    => ['nullable', 'string', 'max:5000'],
            'captured_at'              => ['nullable', 'date'],
            'tags'                     => ['nullable', 'array'],
            'tags.*'                   => ['string', 'max:50'],
            'is_marketing_eligible'    => ['nullable', 'boolean'],
            'is_education_eligible'    => ['nullable', 'boolean'],
            'is_teaching_eligible'     => ['nullable', 'boolean'],
            'is_research_eligible'     => ['nullable', 'boolean'],
            'is_case_library_eligible' => ['nullable', 'boolean'],
            'consent_status'           => ['nullable', \Illuminate\Validation\Rule::in(['not_given', 'pending', 'given'])],
            'marketing_status'         => ['nullable', \Illuminate\Validation\Rule::in(['pending', 'approved', 'rejected'])],
            'content_rating'           => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $file->update($request->only([
            'visit_id', 'procedure', 'treatment_category', 'tooth_number', 'stage', 'file_type',
            'title', 'notes', 'captured_at', 'tags',
            'is_marketing_eligible', 'is_education_eligible',
            'is_teaching_eligible', 'is_research_eligible', 'is_case_library_eligible',
            'consent_status', 'marketing_status', 'content_rating',
        ]));

        return $this->success(['id' => $file->id], 'File updated.');
    }

    /**
     * DELETE /api/v1/patients/{patient}/clinical-files/{file}
     * Soft delete — mirrors web ClinicalFileController::destroy().
     */
    public function deleteClinicalFile(Request $request, $patient, ClinicalFile $file): JsonResponse
    {
        $p = $this->find($request, $patient);

        if ($file->patient_id !== $p->id) {
            return $this->error('File does not belong to this patient.', [], 403);
        }

        $file->delete(); // soft delete only

        return $this->success(null, 'File deleted.');
    }

    public function documents(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = ClinicalFile::forPatient($p->id)
            ->latest('captured_at')
            ->limit(100)
            ->get()
            ->map(fn ($f) => [
                'id'                        => $f->id,
                'title'                     => $f->title,
                'file_type'                 => $f->file_type,
                'procedure'                 => $f->procedure,
                'treatment_category'        => $f->treatment_category,
                'treatment_category_label'  => $f->treatment_category_label,
                'tooth'                     => $f->tooth_number,
                'captured_at'               => $f->captured_at,
                'filename'                  => $f->original_filename,
            ]);

        return $this->success($rows, '');
    }

    /**
     * POST /api/v1/patients/{patient}/documents
     * Upload a document for a patient (X-ray/Consent/Lab Report/etc from the
     * mobile "Add Document" screen). Used to call $patient->documents(), a
     * relation that was removed when clinical_files became the single source
     * of truth — every call here would 500. Now writes through
     * ClinicalFileUploadService, same as every other upload path in the app,
     * so these files also show up in the web Clinical Library.
     * Multipart: file (required), category (required), title, notes.
     */
    public function storeDocument(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $request->validate([
            'file'     => 'required|file|max:20480|mimes:jpg,jpeg,png,pdf,dcm,doc,docx',
            'category' => 'required|string|max:100',
            'title'    => 'nullable|string|max:255',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $fileTypeMap = [
            'X-Ray'         => 'xray',
            'Photo'         => 'photo',
            'Consent Form'  => 'consent',
            'Lab Report'    => 'lab_slip',
            'Invoice'       => 'invoice',
        ];

        $record = app(ClinicalFileUploadService::class)->store($request->file('file'), [
            'patient_id'  => $p->id,
            'file_type'   => $fileTypeMap[$request->category] ?? 'other',
            'title'       => $request->title,
            'notes'       => $request->notes,
            'tags'        => [$request->category],
            'uploaded_by' => Auth::id(),
            'source_type' => 'mobile_document',
        ]);

        return $this->success(['document' => [
            'id'    => $record->id,
            'title' => $record->title,
        ]], 'Document uploaded.');
    }

    /**
     * POST /api/v1/patients/{patient}/clinical-files
     * Mobile clinical photo capture (camera or gallery). Writes through the
     * same ClinicalFileUploadService the web app and consultations use, so a
     * photo taken on the phone shows up in the Clinical Library immediately —
     * not stuck in the phone's own gallery app.
     * Multipart: file (required). Everything else optional — treatment_category
     * is auto-detected server-side from `procedure` when not supplied.
     */
    public function storeClinicalFile(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $request->validate([
            'file'               => 'required|file|max:51200|mimes:jpg,jpeg,png,heic,heif',
            'procedure'          => 'nullable|string|max:255',
            'treatment_category' => ['nullable', Rule::in(array_keys(ClinicalFile::TREATMENT_CATEGORIES))],
            'stage'              => ['nullable', Rule::in(ClinicalFile::STAGES)],
            'tooth_number'       => 'nullable|string|max:50',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $record = app(ClinicalFileUploadService::class)->store($request->file('file'), [
            'patient_id'         => $p->id,
            'procedure'          => $request->procedure,
            'treatment_category' => $request->treatment_category,
            'stage'              => $request->input('stage', 'general'),
            'tooth_number'       => $request->tooth_number,
            'notes'              => $request->notes,
            'uploaded_by'        => Auth::id(),
            'source_type'        => 'mobile_capture',
        ]);

        return $this->success([
            'id'                        => $record->id,
            'treatment_category'       => $record->treatment_category,
            'treatment_category_label' => $record->treatment_category_label,
        ], 'Photo saved.');
    }

    public function notes(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = $p->relationshipNotes()
            ->with('author:id,name')
            ->latest()
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'note'       => $n->note,
                'type'       => $n->note_type,
                'tags'       => $n->tags,
                'author'     => $n->author?->name,
                'created_at' => $n->created_at,
            ]);

        return $this->success($rows, '');
    }

    public function communications(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = $p->communications()
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn ($c) => [
                'id'           => $c->id,
                'type'         => $c->type,
                'direction'    => $c->direction,
                'status'       => $c->status,
                'subject'      => $c->subject,
                'message'      => $c->message,
                'sent_at'      => $c->sent_at,
                'scheduled_at' => $c->scheduled_at,
                'staff'        => $c->staff_name,
            ]);

        return $this->success($rows, '');
    }

    public function memberships(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = FinancePatientMembership::with('plan')
            ->where('patient_id', $p->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'plan'       => $m->plan?->name,
                'status'     => $m->status,
                'start_date' => $m->start_date,
                'end_date'   => $m->end_date,
                'price'      => $m->price ?? ($m->amount ?? null),
            ]);

        return $this->success($rows, '');
    }

    /** Full prescription with its drug lines. */
    public function prescriptionDetail(Request $request, $prescription): JsonResponse
    {
        $rx = Prescription::with([
                'items' => fn ($q) => $q->orderBy('sort_order'),
                'prescribedBy:id,name',
                'patient:id,branch_id',
            ])
            ->withTrashed()
            ->whereKey($prescription)
            ->first();

        if (! $rx || ! $rx->patient ||
            (int) $rx->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Prescription not found.', [], 404);
        }

        return $this->success([
            'id'                   => $rx->id,
            'number'               => $rx->prescription_number,
            'date'                 => $rx->created_at,
            'status'               => $rx->status,
            'diagnosis'            => $rx->diagnosis,
            'chief_complaint'      => $rx->chief_complaint,
            'general_instructions' => $rx->general_instructions,
            'follow_up_date'       => $rx->follow_up_date,
            'doctor'               => $rx->prescribedBy?->name,
            'items'                => $rx->items->map(fn ($it) => [
                'drug_name'     => $it->drug_name,
                'generic_name'  => $it->generic_name,
                'strength'      => $it->strength,
                'dosage_form'   => $it->dosage_form,
                'route'         => $it->route,
                'morning'       => $it->morning,
                'afternoon'     => $it->afternoon,
                'night'         => $it->night,
                'is_sos'        => (bool) $it->is_sos,
                'duration'      => $it->duration,
                'duration_unit' => $it->duration_unit,
                'food_advice'   => $it->food_advice,
                'instructions'  => $it->instructions,
            ])->values(),
        ], '');
    }

    /** Full invoice with line items + receipts. */
    public function invoiceDetail(Request $request, $invoice): JsonResponse
    {
        $inv = Invoice::with([
                'items' => fn ($q) => $q->orderBy('sort_order'),
                'receipts.payment',
                'patient:id,branch_id,name,patient_id,phone',
            ])
            ->whereKey($invoice)
            ->first();

        if (! $inv || ! $inv->patient ||
            (int) $inv->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Invoice not found.', [], 404);
        }

        $cs = AppSetting::where('group', 'clinic')->pluck('value', 'key');

        return $this->success([
            'clinic' => [
                'name'    => $cs->get('name') ?? $cs->get('clinic_name') ?? config('app.name'),
                'phone'   => $cs->get('phone') ?? $cs->get('contact'),
                'address' => $cs->get('address'),
                'gstin'   => $cs->get('gstin') ?? $cs->get('gst_number'),
            ],
            'patient' => [
                'name'       => $inv->patient->name,
                'patient_id' => $inv->patient->patient_id,
                'phone'      => $inv->patient->phone,
            ],
            'id'              => $inv->id,
            'number'          => $inv->invoice_number,
            'date'            => $inv->invoice_date,
            'due_date'        => $inv->due_date,
            'status'          => $inv->status,
            'subtotal'        => $inv->subtotal,
            'discount_amount' => $inv->discount_amount,
            'gst_amount'      => $inv->gst_amount,
            'wallet_applied'  => $inv->wallet_applied,
            'coupon_discount' => $inv->coupon_discount,
            'membership_discount' => $inv->membership_discount,
            'manual_discount_type'   => $inv->manual_discount_type,
            'manual_discount_value'  => $inv->manual_discount_value,
            'manual_discount_amount' => $inv->manual_discount_amount,
            'manual_discount_reason' => $inv->manual_discount_reason,
            'total_amount'    => $inv->total_amount,
            'paid_amount'     => $inv->paid_amount,
            'balance_due'     => $inv->balance_due,
            'notes'           => $inv->notes,
            'cancelled_reason' => $inv->cancelled_reason,
            'items'           => $inv->items->map(fn ($it) => [
                'description'  => $it->description,
                'tooth_number' => $it->tooth_number,
                'unit_price'   => $it->unit_price,
                'qty'          => $it->qty,
                'total'        => $it->total,
            ])->values(),
            'receipts'        => $inv->receipts->map(fn ($r) => [
                'id'         => $r->id,
                'number'     => $r->receipt_number,
                'amount'     => $r->amount,
                'mode'       => $r->payment_mode,
                'date'       => $r->receipt_date,
                'reference'  => $r->reference_no,
                // EMI settlement tracking — only present when this receipt's
                // payment is a Provider EMI, so mobile can show a "Mark as
                // Received" action for the still-pending settlement.
                'payment_id'         => $r->payment?->id,
                'emi_type'           => $r->payment?->emi_type,
                'provider_paid_at'   => $r->payment?->provider_paid_at,
                'clinic_net_amount'  => $r->payment?->clinic_net_amount,
            ])->values(),
        ], '');
    }

    // ── Write actions ────────────────────────────────────────────────────────

    public function storeNote(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:30'],
            'tags' => ['nullable', 'array'],
        ]);

        $n = $p->relationshipNotes()->create([
            'note'       => $data['note'],
            'note_type'  => $data['type'] ?? 'internal',
            'tags'       => $data['tags'] ?? [],
            'created_by' => $request->user()->id,
        ]);
        $n->load('author:id,name');

        return $this->success($this->notePayload($n), 'Note added.', 201);
    }

    public function updateNote(Request $request, $patient, $note): JsonResponse
    {
        $p = $this->find($request, $patient);
        $n = $p->relationshipNotes()->whereKey($note)->first();
        if (! $n) {
            return $this->error('Note not found.', [], 404);
        }
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', 'string', 'max:30'],
        ]);
        $n->update([
            'note'      => $data['note'],
            'note_type' => $data['type'] ?? $n->note_type,
        ]);
        $n->load('author:id,name');

        return $this->success($this->notePayload($n), 'Note updated.');
    }

    public function deleteNote(Request $request, $patient, $note): JsonResponse
    {
        $p = $this->find($request, $patient);
        $n = $p->relationshipNotes()->whereKey($note)->first();
        if (! $n) {
            return $this->error('Note not found.', [], 404);
        }
        $n->delete();

        return $this->success(null, 'Note deleted.');
    }

    public function storeCommunication(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);
        $data = $request->validate([
            'type'      => ['required', 'string', 'max:30'],
            'direction' => ['nullable', 'in:incoming,outgoing'],
            'subject'   => ['nullable', 'string', 'max:255'],
            'message'   => ['nullable', 'string', 'max:2000'],
        ]);

        $c = $p->communications()->create([
            'type'       => $data['type'],
            'direction'  => $data['direction'] ?? 'outgoing',
            'status'     => 'sent',
            'is_auto'    => false,
            'subject'    => $data['subject'] ?? null,
            'message'    => $data['message'] ?? null,
            'sent_at'    => now(),
            'created_by' => $request->user()->id,
            'staff_name' => $request->user()->name,
        ]);

        return $this->success([
            'id'        => $c->id,
            'type'      => $c->type,
            'direction' => $c->direction,
            'status'    => $c->status,
            'subject'   => $c->subject,
            'message'   => $c->message,
            'sent_at'   => $c->sent_at,
            'staff'     => $c->staff_name,
        ], 'Logged.', 201);
    }

    private function notePayload($n): array
    {
        return [
            'id'         => $n->id,
            'note'       => $n->note,
            'type'       => $n->note_type,
            'tags'       => $n->tags,
            'author'     => $n->author?->name,
            'created_at' => $n->created_at,
        ];
    }

    /** Branch-scoped patient lookup (enveloped 404 on miss / cross-branch). */
    private function find(Request $request, $id): Patient
    {
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($id)
            ->first();

        if (! $patient) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Patient not found.',
                'errors'  => [],
            ], 404));
        }

        return $patient;
    }
}
