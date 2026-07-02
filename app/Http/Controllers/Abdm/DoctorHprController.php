<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Models\PractitionerIdentifier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Local HPR (Healthcare Professional Registry) capture for a clinician.
 *
 * Mirrors PatientAbhaController: staff record a doctor's HPR id + council details
 * and set verification status by hand. No live ABDM call. Saves onto the user's
 * hr_staff_profile and mirrors the HPR id into practitioner_identifiers so the FHIR
 * Practitioner mapper picks it up.
 */
class DoctorHprController extends Controller
{
    public const STATUSES = ['unlinked', 'pending', 'verified', 'failed'];

    public function edit(User $user)
    {
        // Make sure a profile row exists to bind the fields to.
        $profile = $user->hrProfile ?: $user->hrProfile()->create([]);

        return view('hr.staff.hpr', [
            'user'     => $user,
            'profile'  => $profile,
            'statuses' => self::STATUSES,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'hpr_id'                  => ['nullable', 'string', 'max:80'],
            'medical_council_name'    => ['nullable', 'string', 'max:150'],
            'registration_year'       => ['nullable', 'integer', 'min:1950', 'max:' . (date('Y'))],
            'hpr_verification_status' => ['required', 'in:' . implode(',', self::STATUSES)],
        ]);

        $profile = $user->hrProfile ?: $user->hrProfile()->create([]);
        $status  = $data['hpr_verification_status'];

        $linkedAt = $profile->hpr_linked_at;
        if ($status === 'verified' && ! $linkedAt) {
            $linkedAt = Carbon::now();
        }
        if ($status === 'unlinked') {
            $linkedAt = null;
        }

        $profile->update([
            'hpr_id'                  => $data['hpr_id'] ?: null,
            'medical_council_name'    => $data['medical_council_name'] ?: null,
            'registration_year'       => $data['registration_year'] ?: null,
            'hpr_verification_status' => $status,
            'hpr_linked_at'           => $linkedAt,
        ]);

        // Mirror HPR id into the practitioner identifier bundle (FHIR source).
        $query = PractitionerIdentifier::where('user_id', $user->id)
            ->where('identifier_type', PractitionerIdentifier::TYPE_HPR_ID);

        if (empty($data['hpr_id'])) {
            $query->delete();
        } else {
            $payload = [
                'user_id'         => $user->id,
                'identifier_type' => PractitionerIdentifier::TYPE_HPR_ID,
                'system_uri'      => 'https://hpr.abdm.gov.in',
                'value'           => $data['hpr_id'],
                'status'          => $status === 'verified' ? 'verified' : 'active',
                'source'          => 'manual',
                'verified_at'     => $status === 'verified' ? Carbon::now() : null,
            ];
            $existing = $query->first();
            $existing ? $existing->update($payload) : PractitionerIdentifier::create($payload);
        }

        return redirect()
            ->route('hr.staff.hpr.edit', $user)
            ->with('success', 'HPR details saved.');
    }
}
