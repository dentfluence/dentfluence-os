<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\StoreAppointmentRequest;
use App\Http\Requests\Api\V1\WalkInRequest;
use App\Http\Requests\Api\V1\BlockSlotRequest;
use App\Http\Resources\AppointmentResource;
use Illuminate\Support\Carbon;
use App\Models\Appointment;
use App\Models\Operatory;
use App\Models\Patient;
use App\Models\TreatmentCategory;
use App\Models\User;
use App\Services\AppointmentService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AppointmentController (API v1)
 * ------------------------------
 * Thin controller over AppointmentService. Branch-scoped, audited (the
 * Appointment model uses Auditable), and envelope-consistent — same shape as
 * the Patients endpoints.
 *
 *   GET   /api/v1/appointments               list (filters + paginate)
 *   GET   /api/v1/appointments/today         today's schedule (not paginated)
 *   GET   /api/v1/appointments/{id}          one appointment
 *   POST  /api/v1/appointments               book (existing patient)
 *   PATCH /api/v1/appointments/{id}/status   move through the lifecycle
 *   PATCH /api/v1/appointments/{id}/cancel   cancel with a reason
 */
class AppointmentController extends ApiController
{
    public function __construct(private AppointmentService $appointments) {}

    /** Paginated, filtered list. */
    public function index(Request $request): JsonResponse
    {
        $query = $this->appointments
            ->filteredQuery($request->user()->branch_id, $request->all());

        $limit = max(1, min((int) $request->query('limit', 30), 100));
        $page  = $query->paginate($limit)->appends($request->query());

        return $this->success(
            AppointmentResource::collection($page->items()),
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

    /** Today's schedule — handy single call for the day view. */
    public function today(Request $request): JsonResponse
    {
        $list = $this->appointments
            ->filteredQuery($request->user()->branch_id, ['scope' => 'today'])
            ->get();

        return $this->success(
            AppointmentResource::collection($list),
            '',
            200,
            ['counts' => $this->appointments->todayCounts($request->user()->branch_id)]
        );
    }

    /** One appointment. */
    public function show(Request $request, $appointment): JsonResponse
    {
        $model = $this->findInBranch($request, $appointment);

        return $this->success(new AppointmentResource($model), '');
    }

    /** Book a scheduled appointment for an existing patient. */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        // Branch isolation: the patient must belong to the caller's branch.
        $patient = Patient::whereKey($request->input('patient_id'))
            ->where('branch_id', $request->user()->branch_id)
            ->first();

        if (! $patient) {
            return $this->error('Patient not found in your branch.', [], 404);
        }

        $appointment = $this->appointments->create($request->validated(), $request->user());

        return $this->success(
            new AppointmentResource($appointment),
            'Appointment booked.',
            201
        );
    }

    /** Advance / change the appointment status. */
    public function updateStatus(Request $request, $appointment): JsonResponse
    {
        $model = $this->findInBranch($request, $appointment);

        $data = $request->validate([
            'status' => ['required', 'in:scheduled,checkin,in_chair,checkout,done,cancelled,no_show'],
        ]);

        $updated = $this->appointments->updateStatus($model, $data['status'], $request->user());

        return $this->success(
            new AppointmentResource($updated),
            'Status updated.',
            200,
            ['counts' => $this->appointments->todayCounts($request->user()->branch_id)]
        );
    }

    /** Cancel with a reason. */
    public function cancel(Request $request, $appointment): JsonResponse
    {
        $model = $this->findInBranch($request, $appointment);

        $data = $request->validate([
            'cancel_reason'   => ['required', 'string', 'max:500'],
            'cancelled_party' => ['required', 'in:patient,clinic'],
        ]);

        $cancelled = $this->appointments->cancel($model, $data['cancel_reason'], $data['cancelled_party'], $request->user());

        return $this->success(new AppointmentResource($cancelled), 'Appointment cancelled.');
    }

    /** Walk-in: an immediately checked-in appointment (existing or new patient). */
    public function walkIn(WalkInRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['patient_id'])) {
            $inBranch = Patient::whereKey($data['patient_id'])
                ->where('branch_id', $request->user()->branch_id)
                ->exists();
            if (! $inBranch) {
                return $this->error('Patient not found in your branch.', [], 404);
            }
        }

        $appointment = $this->appointments->createWalkIn($data, $request->user());

        return $this->success(
            new AppointmentResource($appointment),
            'Walk-in checked in.',
            201
        );
    }

    /** Block a doctor's time slot. */
    public function blockSlot(BlockSlotRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['end_time'] = $data['end_time']
            ?? Carbon::parse($data['start_time'])
                ->addMinutes($data['duration_minutes'] ?? 30)
                ->format('H:i');

        $slot = $this->appointments->blockSlot($data, $request->user());

        return $this->success([
            'id'         => $slot->id,
            'doctor_id'  => $slot->doctor_id,
            'block_date' => $slot->block_date instanceof Carbon
                ? $slot->block_date->format('Y-m-d')
                : (string) $slot->block_date,
            'start_time' => substr($slot->start_time, 0, 5),
            'end_time'   => substr($slot->end_time, 0, 5),
            'reason'     => $slot->reason,
        ], 'Slot blocked.', 201);
    }

    /** Blocked slots in a date range, for the calendar. */
    public function blockedSlots(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $from = $request->query('date_from') ?? $request->query('date') ?? today()->toDateString();
        $to   = $request->query('date_to') ?? $request->query('date') ?? $from;

        $slots = $this->appointments->blockedSlotsInRange($branchId, $from, $to)
            ->map(fn ($s) => [
                'id'          => $s->id,
                'doctor_id'   => $s->doctor_id,
                'doctor_name' => $s->doctor?->name,
                'block_date'  => $s->block_date instanceof Carbon
                    ? $s->block_date->format('Y-m-d')
                    : (string) $s->block_date,
                'start_time'  => substr($s->start_time, 0, 5),
                'end_time'    => substr($s->end_time, 0, 5),
                'reason'      => $s->reason,
            ])
            ->values();

        return $this->success($slots, '');
    }

    /**
     * Options for the "Add appointment" form: branch doctors + treatment
     * categories (each with its treatments and default durations).
     */
    public function formOptions(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        $doctors = User::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereIn('role', User::DOCTOR_ROLES)
                ->orWhere('name', 'like', 'Dr.%'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        $categories = TreatmentCategory::active()
            ->orderBy('name')
            ->with(['treatments' => fn ($q) =>
                $q->select('id', 'treatment_category_id', 'name', 'default_duration_minutes')])
            ->get(['id', 'name'])
            ->map(fn ($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'treatments' => $c->treatments->map(fn ($t) => [
                    'id'       => $t->id,
                    'name'     => $t->name,
                    'duration' => $t->default_duration_minutes,
                ])->values(),
            ])
            ->values();

        $operatories = Operatory::forBranch($branchId)
            ->active()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])
            ->values();

        return $this->success([
            'doctors'              => $doctors,
            'treatment_categories' => $categories,
            'operatories'          => $operatories,
        ], 'Appointment form options');
    }

    /**
     * Resolve an appointment by id, scoped to the caller's branch. Cross-branch
     * or missing ids both get the same enveloped 404.
     */
    private function findInBranch(Request $request, $id): Appointment
    {
        $appointment = Appointment::with(['patient', 'doctor', 'treatmentCategory', 'treatment', 'operatory'])
            ->where('branch_id', $request->user()->branch_id)
            ->whereKey($id)
            ->first();

        if (! $appointment) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'message' => 'Appointment not found.',
                'errors'  => [],
            ], 404));
        }

        return $appointment;
    }
}
