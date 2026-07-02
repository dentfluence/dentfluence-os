<?php

namespace App\Services;

use App\Models\DataRequest;
use App\Models\Patient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * DataRightsService (DPDP 5.2)
 * ----------------------------
 * The brain for patient-rights requests. Handles creating requests, moving
 * them through the workflow, and the two that have real side-effects:
 *   - ACCESS  -> compile an export of the patient's data
 *   - ERASURE -> anonymise the patient's identifying data (kept within the
 *                limits of clinical/financial record-retention law)
 */
class DataRightsService
{
    /** Days the clinic gives itself to resolve a request (SLA). Tune as needed. */
    public const SLA_DAYS = 30;

    /** Create a new request with a reference + due date. */
    public function create(Patient $patient, string $type, array $data = []): DataRequest
    {
        $now = Carbon::now();

        return DataRequest::create([
            'reference'      => $this->nextReference($now),
            'patient_id'     => $patient->id,
            'branch_id'      => $patient->branch_id ?? Auth::user()?->branch_id,
            'type'           => $type,
            'status'         => 'pending',
            'details'        => $data['details'] ?? null,
            'requested_via'  => $data['requested_via'] ?? 'web',
            'requester_name' => $data['requester_name'] ?? null,
            'requested_at'   => $now,
            'due_at'         => $now->copy()->addDays(self::SLA_DAYS),
            'payload'        => $data['payload'] ?? null,
        ]);
    }

    public function assign(DataRequest $request, ?int $userId): DataRequest
    {
        $request->update([
            'assigned_to' => $userId,
            'status'      => $request->status === 'pending' ? 'in_progress' : $request->status,
        ]);
        return $request;
    }

    /** Mark a request resolved. For "nominee", also writes the nominee to the patient. */
    public function complete(DataRequest $request, ?string $resolution = null): DataRequest
    {
        if ($request->type === 'nominee') {
            $this->applyNominee($request);
        }

        $request->update([
            'status'      => 'completed',
            'resolution'  => $resolution ?? $request->resolution,
            'resolved_by' => Auth::id(),
            'resolved_at' => Carbon::now(),
        ]);
        return $request;
    }

    public function reject(DataRequest $request, string $reason): DataRequest
    {
        $request->update([
            'status'      => 'rejected',
            'resolution'  => $reason,
            'resolved_by' => Auth::id(),
            'resolved_at' => Carbon::now(),
        ]);
        return $request;
    }

    /**
     * ACCESS right — compile the patient's data into a plain array for export.
     * Kept to the core record + consent state + activity counts; deep clinical
     * exports can be added per data type later.
     */
    public function compileAccessExport(Patient $patient): array
    {
        $patient->loadMissing(['consents.purpose']);

        return [
            'generated_at' => Carbon::now()->toDateTimeString(),
            'patient'      => collect($patient->getAttributes())
                ->except(['created_by', 'updated_at', 'created_at', 'deleted_at'])
                ->all(),
            'consents'     => $patient->consents->map(fn ($c) => [
                'purpose' => $c->purpose->name ?? $c->consent_purpose_id,
                'status'  => $c->status,
                'on_behalf_of' => $c->on_behalf_of,
                'updated_at' => optional($c->updated_at)->toDateTimeString(),
            ])->all(),
            'activity'     => [
                'appointments'    => $patient->appointments()->count(),
                'consultations'   => method_exists($patient, 'consultations') ? $patient->consultations()->count() : null,
                'treatment_plans' => method_exists($patient, 'treatmentPlans') ? $patient->treatmentPlans()->count() : null,
            ],
            'nominee'      => [
                'name'         => $patient->nominee_name,
                'relationship' => $patient->nominee_relationship,
                'contact'      => $patient->nominee_contact,
            ],
        ];
    }

    /**
     * ERASURE right — anonymise the patient's identifying fields.
     *
     * DESTRUCTIVE to personal data: this overwrites name/contact/address with
     * redaction markers. Clinical and financial records are deliberately KEPT
     * (statutory retention), just de-linked from a real identity. Only call
     * this from a deliberate, confirmed admin action.
     */
    public function anonymisePatient(Patient $patient): void
    {
        $code = $patient->patient_id ?? $patient->id;

        $patient->name        = 'REDACTED (' . $code . ')';
        $patient->first_name  = 'REDACTED';
        $patient->middle_name = null;
        $patient->last_name   = null;
        $patient->phone               = null;
        $patient->alternate_phone     = null;
        $patient->email               = null;
        $patient->address             = null;
        $patient->area                = null;
        $patient->city                = null;
        $patient->pincode             = null;
        $patient->emergency_contact_name   = null;
        $patient->emergency_contact_number = null;
        $patient->nominee_name        = null;
        $patient->nominee_relationship = null;
        $patient->nominee_contact     = null;
        $patient->save();
    }

    /** Save the nominee details carried on a nominee request to the patient. */
    protected function applyNominee(DataRequest $request): void
    {
        $p = $request->payload ?? [];
        $patient = $request->patient;
        if (! $patient) {
            return;
        }
        $patient->nominee_name         = $p['nominee_name'] ?? $patient->nominee_name;
        $patient->nominee_relationship = $p['nominee_relationship'] ?? $patient->nominee_relationship;
        $patient->nominee_contact      = $p['nominee_contact'] ?? $patient->nominee_contact;
        $patient->save();
    }

    /** DR-2026-0001 style reference, sequential within the year. */
    protected function nextReference(Carbon $now): string
    {
        $year = $now->year;
        $seq  = DataRequest::withTrashed()->whereYear('created_at', $year)->count() + 1;
        return sprintf('DR-%d-%04d', $year, $seq);
    }
}
