<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\LabCase;
use App\Models\LabCasePrescription;
use App\Models\LabPrescriptionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Clinic-wide Lab Work board for the mobile app.
 *
 * READ:
 *   GET    /api/v1/lab/cases                        list
 *   GET    /api/v1/lab/summary                      KPI counts
 *   GET    /api/v1/lab/cases/{id}                   case detail
 *   GET    /api/v1/lab/templates                    prescription templates
 *
 * WRITE:
 *   POST   /api/v1/lab/cases                        create case
 *   PATCH  /api/v1/lab/cases/{id}/status/{to}       transition status
 *   POST   /api/v1/lab/cases/{id}/prescription      save/update prescription
 *   POST   /api/v1/lab/cases/{id}/attachments       upload attachment
 */
class LabController extends ApiController
{
    // ── READ ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $status   = (string) $request->query('status', 'open');
        $search   = trim((string) $request->query('search', ''));

        $query = LabCase::with(['patient:id,name,phone', 'vendor:id,name'])
            ->where('branch_id', $branchId);

        switch ($status) {
            case 'open':
                $query->whereIn('status', LabCase::OPEN_STATUSES);
                break;
            case 'complete':
                $query->where('status', 'complete');
                break;
            case 'rejected':
                $query->where('status', 'rejected');
                break;
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn ($p) => $p
                      ->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $query->orderByRaw('expected_return_date is null')
              ->orderBy('expected_return_date')
              ->orderByDesc('id');

        $limit = max(1, min((int) $request->query('limit', 20), 100));
        $page  = $query->paginate($limit)->appends($request->query());

        return $this->success(
            collect($page->items())->map(fn ($l) => $this->row($l))->values(),
            '',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ]
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $base = fn () => LabCase::where('branch_id', $branchId);

        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        return $this->success([
            'open_count'          => $base()->whereIn('status', LabCase::OPEN_STATUSES)->count(),
            'overdue_count'       => $base()->whereIn('status', LabCase::OPEN_STATUSES)
                ->whereNotNull('expected_return_date')
                ->whereDate('expected_return_date', '<', $today)->count(),
            'complete_this_month' => $base()->where('status', 'complete')
                ->whereDate('final_received_date', '>=', $monthStart)->count(),
            'rejected_count'      => $base()->where('status', 'rejected')->count(),
        ], '');
    }

    public function show(Request $request, $id): JsonResponse
    {
        $case = LabCase::with([
                'patient:id,name,phone',
                'vendor:id,name',
                'items',
                'prescription',
                'rating',
                'events.user:id,name',
            ])
            ->where('branch_id', $request->user()->branch_id)
            ->whereKey($id)->first();

        if (! $case) {
            return $this->error('Lab case not found.', [], 404);
        }

        return $this->success([
            'id'             => $case->id,
            'case_number'    => $case->case_number,
            'status'         => $case->status,
            'status_label'   => LabCase::STATUS_LABELS[$case->status] ?? ucfirst($case->status),
            'next_statuses'  => LabCase::STATUS_FLOW[$case->status] ?? [],
            'priority'       => $case->priority,
            'category'       => $case->work_category,
            'subtype'        => $case->work_subtype,
            'sent'           => $case->sent_date,
            'expected'       => $case->expected_return_date,
            'received'       => $case->final_received_date,
            'lab_cost'       => (float) $case->lab_cost,
            'payment_status' => $case->payment_status,
            'technician'     => $case->technician_name,
            'notes'          => $case->notes,
            'patient'        => $case->patient ? [
                'id'    => $case->patient->id,
                'name'  => $case->patient->name,
                'phone' => $case->patient->phone,
            ] : null,
            'vendor'         => $case->vendor ? [
                'id'   => $case->vendor->id,
                'name' => $case->vendor->name,
            ] : null,
            'items'          => $case->items->map(fn ($it) => [
                'tooth'     => $it->tooth_number,
                'work_type' => $it->work_type,
                'material'  => $it->material,
                'shade'     => $it->shade,
                'notes'     => $it->notes,
            ])->values(),
            'prescription'   => $case->prescription ? [
                'material'             => $case->prescription->material,
                'shade'                => $case->prescription->shade,
                'stump_shade'          => $case->prescription->stump_shade,
                'clinical_fields'      => $case->prescription->clinical_fields ?? [],
                'special_instructions' => $case->prescription->special_instructions,
                'summary'              => $case->prescription->summaryLine(),
            ] : null,
            'rating'         => $case->rating ? [
                'overall' => $case->rating->overall,
                'stars'   => \App\Models\LabCaseRating::stars($case->rating->overall),
                'label'   => $case->rating->overallLabel(),
            ] : null,
            'events'         => $case->events->sortByDesc('created_at')->values()->map(fn ($e) => [
                'type'        => $e->event_type,
                'from'        => $e->from_status,
                'to'          => $e->to_status,
                'description' => $e->description,
                'user'        => $e->user?->name,
                'at'          => $e->created_at,
            ])->values(),
        ], '');
    }

    /** GET /api/v1/lab/templates */
    public function templates(Request $request): JsonResponse
    {
        $query = LabPrescriptionTemplate::active()
            ->where('branch_id', $request->user()->branch_id)
            ->orderBy('name');

        if ($category = $request->query('category')) {
            $query->forCategory($category);
        }

        return $this->success(
            $query->get()->map(fn ($t) => $t->toPreset())->values(),
            ''
        );
    }

    /**
     * GET /api/v1/lab/form-options
     * Everything the mobile create-case form needs: work categories→subtypes
     * (LabCase::WORK_CATEGORIES — same single source of truth the web form
     * renders), active lab vendors, branch doctors, priorities.
     */
    public function formOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        $vendors = \App\Models\LabVendor::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
            ->values();

        $doctors = \App\Models\User::where('branch_id', $user->branch_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereIn('role', \App\Models\User::DOCTOR_ROLES)
                ->orWhere('name', 'like', 'Dr.%'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        return $this->success([
            'work_categories' => LabCase::WORK_CATEGORIES,
            'vendors'         => $vendors,
            'doctors'         => $doctors,
            'priorities'      => ['normal', 'urgent', 'asap'],
        ], '');
    }

    // ── WRITE ───────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/lab/cases
     * Create a new lab case (draft or order_placed).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'patient_id'             => 'required|exists:patients,id',
            'lab_vendor_id'          => 'nullable|exists:lab_vendors,id',
            'doctor_id'              => 'nullable|exists:users,id',
            'work_category'          => 'required|string|max:100',
            'work_subtype'           => 'nullable|string|max:100',
            'priority'               => 'nullable|in:normal,urgent,asap',
            'order_placed_date'      => 'nullable|date',
            'sent_date'              => 'nullable|date',
            'expected_return_date'   => 'nullable|date',
            'shade'                  => 'nullable|string|max:30',
            'technician_name'        => 'nullable|string|max:100',
            'estimated_cost'         => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string|max:2000',
            'status'                 => 'nullable|in:draft,order_placed',
            'items'                  => 'nullable|array|max:32',
            'items.*.tooth_number'   => 'nullable|string|max:10',
            'items.*.work_type'      => 'nullable|string|max:100',
            'items.*.material'       => 'nullable|string|max:100',
            'items.*.shade'          => 'nullable|string|max:30',
            'items.*.notes'          => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $case = LabCase::create([
                'branch_id'            => $user->branch_id,
                'patient_id'           => $data['patient_id'],
                'lab_vendor_id'        => $data['lab_vendor_id'] ?? null,
                'doctor_id'            => $data['doctor_id'] ?? $user->id,
                'work_category'        => $data['work_category'],
                'work_subtype'         => $data['work_subtype'] ?? null,
                'priority'             => $data['priority'] ?? 'normal',
                'status'               => $data['status'] ?? 'draft',
                'order_placed_date'    => $data['order_placed_date'] ?? null,
                'sent_date'            => $data['sent_date'] ?? null,
                'expected_return_date' => $data['expected_return_date'] ?? null,
                'shade'                => $data['shade'] ?? null,
                'technician_name'      => $data['technician_name'] ?? null,
                'estimated_cost'       => $data['estimated_cost'] ?? null,
                'notes'                => $data['notes'] ?? null,
                'created_by'           => $user->id,
            ]);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $case->items()->create([
                        'tooth_number' => $item['tooth_number'] ?? null,
                        'work_type'    => $item['work_type'] ?? null,
                        'material'     => $item['material'] ?? null,
                        'shade'        => $item['shade'] ?? null,
                        'notes'        => $item['notes'] ?? null,
                    ]);
                }
            }

            $case->logEvent('created', 'Case created via mobile app', ['by' => $user->name]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create case: ' . $e->getMessage(), [], 500);
        }

        return $this->success(
            ['id' => $case->id, 'case_number' => $case->case_number],
            'Lab case created.',
            201
        );
    }

    /**
     * PATCH /api/v1/lab/cases/{id}/status/{to}
     * Transition status, respecting STATUS_FLOW guard.
     */
    public function transition(Request $request, $id, string $to): JsonResponse
    {
        $user = $request->user();

        $case = LabCase::where('branch_id', $user->branch_id)->whereKey($id)->first();
        if (! $case) {
            return $this->error('Lab case not found.', [], 404);
        }

        if (! $case->canTransitionTo($to)) {
            return $this->error(
                "Cannot move from '{$case->status}' to '{$to}'.",
                ['allowed' => LabCase::STATUS_FLOW[$case->status] ?? []],
                422
            );
        }

        // Shared brain (2026-07-14) — web's canonical transition: correct date
        // columns, task chain close/create, notifications, and the lab Finance
        // expense on final_received. This path previously stamped non-fillable
        // columns, skipped the task chain, and never posted the AP expense.
        try {
            app(\App\Services\LabCaseTransitionService::class)->transition($case, $to, $user);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        $case->refresh();

        return $this->success([
            'status'       => $case->status,
            'status_label' => LabCase::STATUS_LABELS[$case->status] ?? $case->status,
            'next'         => LabCase::STATUS_FLOW[$case->status] ?? [],
        ], "Status updated to '{$to}'.");
    }

    /**
     * POST /api/v1/lab/cases/{id}/prescription
     * Create or update the structured prescription for a lab case.
     */
    public function prescriptionSave(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $case = LabCase::where('branch_id', $user->branch_id)->whereKey($id)->first();
        if (! $case) {
            return $this->error('Lab case not found.', [], 404);
        }

        $data = $request->validate([
            'template_id'              => 'nullable|exists:lab_prescription_templates,id',
            'material'                 => 'nullable|string|max:100',
            'shade'                    => 'nullable|string|max:30',
            'stump_shade'              => 'nullable|string|max:30',
            'clinical_fields'          => 'nullable|array',
            'special_instructions'     => 'nullable|string|max:2000',
            'suggestions_acknowledged' => 'boolean',
        ]);

        $rx = LabCasePrescription::updateOrCreate(
            ['lab_case_id' => $case->id],
            [
                'template_id'              => $data['template_id'] ?? null,
                'material'                 => $data['material'] ?? null,
                'shade'                    => $data['shade'] ?? null,
                'stump_shade'              => $data['stump_shade'] ?? null,
                'clinical_fields'          => $data['clinical_fields'] ?? [],
                'special_instructions'     => $data['special_instructions'] ?? null,
                'suggestions_acknowledged' => $data['suggestions_acknowledged'] ?? false,
                'created_by'               => $case->prescription ? $case->prescription->created_by : $user->id,
                'updated_by'               => $user->id,
            ]
        );

        return $this->success([
            'id'      => $rx->id,
            'summary' => $rx->summaryLine(),
        ], 'Prescription saved.');
    }

    /**
     * POST /api/v1/lab/cases/{id}/attachments
     * Upload a photo/scan/document to a lab case (multipart, field: "file").
     */
    public function attachmentStore(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $case = LabCase::where('branch_id', $user->branch_id)->whereKey($id)->first();
        if (! $case) {
            return $this->error('Lab case not found.', [], 404);
        }

        $request->validate([
            'file'        => 'required|file|max:20480|mimes:jpg,jpeg,png,gif,pdf,stl,dcm,zip,doc,docx',
            'description' => 'nullable|string|max:255',
        ]);

        $uploaded = $request->file('file');
        $disk     = 'private';
        $path     = $uploaded->store("lab-cases/{$case->id}/attachments", $disk);

        // Use ClinicalFile polymorphic table (same table as web upload)
        $file = \App\Models\ClinicalFile::create([
            'fileable_type' => LabCase::class,
            'fileable_id'   => $case->id,
            'branch_id'     => $user->branch_id,
            'uploaded_by'   => $user->id,
            'file_name'     => $uploaded->getClientOriginalName(),
            'file_path'     => $path,
            'file_type'     => $uploaded->getMimeType(),
            'file_size'     => $uploaded->getSize(),
            'description'   => $request->input('description'),
            'disk'          => $disk,
        ]);

        $case->logEvent('attachment', "File attached: {$file->file_name}", ['by' => $user->name]);

        return $this->success([
            'id'        => $file->id,
            'file_name' => $file->file_name,
            'file_size' => $file->file_size,
            'file_type' => $file->file_type,
        ], 'File attached.', 201);
    }

    // ── HELPERS ─────────────────────────────────────────────────────────────

    private function row(LabCase $l): array
    {
        return [
            'id'           => $l->id,
            'case_number'  => $l->case_number,
            'status'       => $l->status,
            'status_label' => LabCase::STATUS_LABELS[$l->status] ?? ucfirst($l->status),
            'priority'     => $l->priority,
            'category'     => $l->work_category,
            'subtype'      => $l->work_subtype,
            'sent'         => $l->sent_date,
            'expected'     => $l->expected_return_date,
            'lab_cost'     => (float) $l->lab_cost,
            'technician'   => $l->technician_name,
            'patient'      => $l->relationLoaded('patient') && $l->patient ? [
                'id'    => $l->patient->id,
                'name'  => $l->patient->name,
                'phone' => $l->patient->phone,
            ] : null,
            'vendor'       => $l->relationLoaded('vendor') && $l->vendor ? [
                'name' => $l->vendor->name,
            ] : null,
        ];
    }
}
