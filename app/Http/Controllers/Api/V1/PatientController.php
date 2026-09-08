<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StorePatientRequest;
use App\Http\Requests\Api\V1\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PatientController (API v1)
 * --------------------------
 * The mobile / Tulip face of the Patients module. It is THIN: it only
 * authenticates, scopes to the caller's branch, and shapes responses. Every
 * query and write goes through the SAME PatientService the web pages use, so
 * behaviour can never drift between web and app.
 *
 *   GET    /api/v1/patients              list (search + filters + paginate)
 *   GET    /api/v1/patients/search       type-ahead suggestions
 *   GET    /api/v1/patients/{patient}    one patient
 *   POST   /api/v1/patients              create
 *   PUT    /api/v1/patients/{patient}    update (partial)
 *   POST   /api/v1/patients/{patient}/deactivate
 *
 * Audit logging is automatic — the Patient model uses the Auditable trait, so
 * every create/update/deactivate writes to audit_logs with device = "api".
 */
class PatientController extends ApiController
{
    public function __construct(private PatientService $patients) {}

    /** Paginated, filtered list — scoped to the caller's branch. */
    /**
     * The values the phone's filter sheet renders — the same lists the web
     * patient index builds for its dropdowns. The filter PARAMETERS themselves
     * (q, gender, area, membership, follow_up, source, family, age_min,
     * age_max, birthday_month, sort) already match the web exactly because
     * both screens go through PatientService::filteredQuery(); measured 8 Sep
     * (M-4). What the phone lacked was where the option VALUES come from.
     *
     *   GET /api/v1/patients/filter-options
     */
    public function filterOptions(Request $request): JsonResponse
    {
        return $this->success([
            'areas'      => $this->patients->distinctAreas($request->user()->branch_id)->values(),
            'sources'    => ['Google', 'Instagram', 'Facebook', 'Referral', 'Walk-In', 'Camp', 'Website', 'Other'],
            'membership' => ['active', 'expired', 'not_enrolled'],
            'follow_up'  => ['due', 'pending', 'completed'],
            'family'     => ['has_family', 'no_family'],
            'gender'     => ['male', 'female', 'other'],
            'sort'       => ['newest', 'oldest', 'name', 'name_desc'],
        ], 'Patient filter options');
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->patients
            ->filteredQuery($request->user()->branch_id, $request->all())
            ->with('tags');

        // Clamp page size so a client can never pull the whole table.
        $limit = max(1, min((int) $request->query('limit', 20), 100));
        $page  = $query->paginate($limit)->appends($request->query());

        return $this->success(
            PatientResource::collection($page->items()),
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

    /** Lightweight type-ahead used by mobile search bars. */
    public function search(Request $request): JsonResponse
    {
        $term = (string) ($request->query('q') ?? $request->query('search') ?? '');

        return $this->success(
            $this->patients->suggest($term, $request->user()->branch_id),
            ''
        );
    }

    /** One patient. */
    public function show(Request $request, $patient): JsonResponse
    {
        $model = $this->findInBranch($request, $patient);

        // Access trail (Phase A) — who opened which patient record, when.
        \App\Models\AuditLog::event('viewed', $request->user()->id, [], [
            'module'         => 'patients',
            'auditable_type' => Patient::class,
            'auditable_id'   => $model->id,
        ]);

        $model->load(['tags', 'referredPatient']);

        // Phase 3 Slice 5 — read-only family graph on the DETAIL endpoint only.
        // One FamilyLinkService call (no N+1); attached as a loaded relation so
        // PatientResource::whenLoaded gates it — list responses never carry it.
        $model->setRelation(
            'familyGraph',
            app(\App\Services\Patient\FamilyLinkService::class)->linksFor($model)
        );

        return $this->success(new PatientResource($model), '');
    }

    /** Create a patient. */
    public function store(StorePatientRequest $request): JsonResponse
    {
        $patient = $this->patients->register($request->validated(), $request->user());

        return $this->success(
            new PatientResource($patient->load('tags')),
            'Patient created.',
            201
        );
    }

    /** Update a patient (partial). */
    public function update(UpdatePatientRequest $request, $patient): JsonResponse
    {
        $model = $this->findInBranch($request, $patient);

        $model = $this->patients->updateFromInput($model, $request->validated());

        return $this->success(
            new PatientResource($model->load(['tags', 'referredPatient'])),
            'Patient updated.'
        );
    }

    /** Soft-deactivate a patient (reason required). */
    public function deactivate(Request $request, $patient): JsonResponse
    {
        $model = $this->findInBranch($request, $patient);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->patients->deactivate($model, $data['reason'], $request->user()->id);

        return $this->success(
            new PatientResource($model->fresh()->load('tags')),
            'Patient deactivated.'
        );
    }

    /**
     * Resolve a patient by id, scoped to the caller's branch.
     *
     * We do the lookup ourselves (instead of route-model binding) for two
     * reasons: branch isolation (a token for branch A asking for branch B's
     * patient gets the SAME answer as a non-existent id), and a clean enveloped
     * 404 that never leaks a framework stack trace or file paths.
     */
    private function findInBranch(Request $request, $id): Patient
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
