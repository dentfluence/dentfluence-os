<?php

namespace App\Http\Controllers\Abdm;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientIdentifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Local ABHA capture for a patient.
 *
 * This is the "ABHA-ready" data entry: staff type a patient's ABHA number/address
 * and set a verification status BY HAND. There is NO live ABDM call here — it just
 * stores the health-id details locally and mirrors them into patient_identifiers so
 * the FHIR engine picks them up automatically. When real ABDM verification is wired
 * later, it slots in behind the same fields.
 */
class PatientAbhaController extends Controller
{
    /** Status values a staff member can set manually (no gateway). */
    public const STATUSES = ['unlinked', 'pending', 'verified', 'failed', 'revoked'];

    /** Languages we support for patient-facing output (drives FHIR communication). */
    public const LANGUAGES = ['en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi'];

    public function edit(Patient $patient)
    {
        return view('patients.abha', [
            'patient'   => $patient,
            'statuses'  => self::STATUSES,
            'languages' => self::LANGUAGES,
        ]);
    }

    public function update(Request $request, Patient $patient)
    {
        $data = $request->validate([
            // 14 digits, optionally written with spaces/hyphens (e.g. 12-3456-7890-1234)
            'abha_number'              => ['nullable', 'string', 'regex:/^[0-9\s\-]{14,20}$/'],
            'abha_address'             => ['nullable', 'string', 'max:120'],
            'abha_verification_status' => ['required', 'in:' . implode(',', self::STATUSES)],
            'preferred_language'       => ['nullable', 'in:' . implode(',', array_keys(self::LANGUAGES))],
        ], [
            'abha_number.regex' => 'ABHA number must be the 14-digit health id (digits only, hyphens/spaces allowed).',
        ]);

        // Normalise the ABHA number to 14 digits, then to XX-XXXX-XXXX-XXXX display form.
        $normalized = null;
        if (! empty($data['abha_number'])) {
            $digits = preg_replace('/\D/', '', $data['abha_number']);
            if (strlen($digits) !== 14) {
                return back()
                    ->withErrors(['abha_number' => 'ABHA number must contain exactly 14 digits.'])
                    ->withInput();
            }
            $normalized = substr($digits, 0, 2) . '-' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4) . '-' . substr($digits, 10, 4);
        }

        $status = $data['abha_verification_status'];

        // Stamp the link time the first time it becomes "verified".
        $linkedAt = $patient->abha_linked_at;
        if ($status === 'verified' && ! $linkedAt) {
            $linkedAt = Carbon::now();
        }
        if ($status === 'unlinked') {
            $linkedAt = null;
        }

        $patient->update([
            'abha_number'              => $normalized,
            'abha_address'             => $data['abha_address'] ?: null,
            'abha_verification_status' => $status,
            'abha_linked_at'           => $linkedAt,
            'preferred_language'       => $data['preferred_language'] ?: null,
        ]);

        // Mirror into the polymorphic identifier bundle so FHIR + future ABDM see it.
        $this->syncIdentifier($patient, PatientIdentifier::TYPE_ABHA_NUMBER, $normalized, 'https://healthid.ndhm.gov.in', $status);
        $this->syncIdentifier($patient, PatientIdentifier::TYPE_ABHA_ADDRESS, $data['abha_address'] ?: null, 'https://healthid.ndhm.gov.in/address', $status);

        return redirect()
            ->route('patients.abha.edit', $patient)
            ->with('success', 'ABHA details saved.');
    }

    /**
     * Keep patient_identifiers in sync with the ABHA fields. Removes the row if the
     * value was cleared; otherwise upserts it.
     */
    private function syncIdentifier(Patient $patient, string $type, ?string $value, string $system, string $status): void
    {
        $query = PatientIdentifier::where('patient_id', $patient->id)->where('identifier_type', $type);

        if (empty($value)) {
            $query->delete();
            return;
        }

        $existing = $query->first();
        $payload = [
            'patient_id'      => $patient->id,
            'identifier_type' => $type,
            'system_uri'      => $system,
            'value'           => $value,
            'status'          => $status === 'verified' ? 'verified' : 'active',
            'source'          => 'manual',
            'verified_at'     => $status === 'verified' ? Carbon::now() : null,
        ];

        if ($existing) {
            $existing->update($payload);
        } else {
            PatientIdentifier::create($payload);
        }
    }
}
