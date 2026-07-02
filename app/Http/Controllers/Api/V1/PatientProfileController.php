<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\AppSetting;
use App\Models\ClinicalFile;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\Prescription\Prescription;
use App\Models\Wallet;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

        $tx = $wallet->transactions()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'amount'      => $t->amount ?? null,
                'type'        => $t->type ?? ($t->transaction_type ?? null),
                'description' => $t->description ?? ($t->note ?? ($t->reason ?? null)),
                'date'        => $t->created_at,
            ]);

        return $this->success([
            'balance_total'       => $wallet->balance_total,
            'balance_promotional' => $wallet->balance_promotional,
            'balance_permanent'   => $wallet->balance_permanent,
            'transactions'        => $tx,
        ], '');
    }

    public function documents(Request $request, $patient): JsonResponse
    {
        $p = $this->find($request, $patient);

        $rows = ClinicalFile::forPatient($p->id)
            ->latest('captured_at')
            ->limit(100)
            ->get()
            ->map(fn ($f) => [
                'id'          => $f->id,
                'title'       => $f->title,
                'file_type'   => $f->file_type,
                'procedure'   => $f->procedure,
                'tooth'       => $f->tooth_number,
                'captured_at' => $f->captured_at,
                'filename'    => $f->original_filename,
            ]);

        return $this->success($rows, '');
    }

    /**
     * POST /api/v1/patients/{patient}/documents
     * Upload a document for a patient. Mirrors web PatientDocumentController::store().
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

        $file = $request->file('file');
        $path = $file->store("patients/{$p->id}/documents", 'public');

        $doc = $p->documents()->create([
            'uploaded_by'   => Auth::id(),
            'category'      => $request->category,
            'title'         => $request->title,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'notes'         => $request->notes,
        ]);

        return $this->success(['document' => $doc], 'Document uploaded.');
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
                'receipts',
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
            'total_amount'    => $inv->total_amount,
            'paid_amount'     => $inv->paid_amount,
            'balance_due'     => $inv->balance_due,
            'notes'           => $inv->notes,
            'items'           => $inv->items->map(fn ($it) => [
                'description'  => $it->description,
                'tooth_number' => $it->tooth_number,
                'unit_price'   => $it->unit_price,
                'qty'          => $it->qty,
                'total'        => $it->total,
            ])->values(),
            'receipts'        => $inv->receipts->map(fn ($r) => [
                'number'    => $r->receipt_number,
                'amount'    => $r->amount,
                'mode'      => $r->payment_mode,
                'date'      => $r->receipt_date,
                'reference' => $r->reference_no,
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
