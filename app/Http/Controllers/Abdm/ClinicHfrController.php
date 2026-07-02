<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;

/**
 * Local HFR (Health Facility Registry) capture for the clinic (a Branch).
 *
 * Edits the primary branch's facility identity fields. No live ABDM call — staff
 * record the HFR id, facility type, geo-coordinates, and verification status by hand.
 * These map straight to the FHIR Organization the engine already produces.
 */
class ClinicHfrController extends Controller
{
    public const STATUSES = ['unlinked', 'pending', 'verified', 'failed'];

    public const FACILITY_TYPES = ['dental_clinic', 'clinic', 'hospital', 'diagnostic_centre'];

    /** The clinic we manage — primary branch (id 1), falling back to the first. */
    private function clinic(): Branch
    {
        return Branch::find(1) ?? Branch::firstOrFail();
    }

    public function edit()
    {
        return view('settings.clinic-hfr', [
            'branch'        => $this->clinic(),
            'statuses'      => self::STATUSES,
            'facilityTypes' => self::FACILITY_TYPES,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'hfr_id'                       => ['nullable', 'string', 'max:120'],
            'facility_type'                => ['nullable', 'in:' . implode(',', self::FACILITY_TYPES)],
            'facility_verification_status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'geo_lat'                      => ['nullable', 'numeric', 'between:-90,90'],
            'geo_lng'                      => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $branch = $this->clinic();
        $branch->update([
            'hfr_id'                       => $data['hfr_id'] ?: null,
            'facility_type'                => $data['facility_type'] ?: null,
            'facility_verification_status' => $data['facility_verification_status'],
            'geo_lat'                      => $data['geo_lat'] ?: null,
            'geo_lng'                      => $data['geo_lng'] ?: null,
        ]);

        return redirect()
            ->route('settings.clinic.hfr.edit')
            ->with('success', 'Health facility (HFR) details saved.');
    }
}
