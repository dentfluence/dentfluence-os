<?php

namespace App\Http\Controllers;

use App\Models\ConsentPurpose;
use App\Models\Patient;
use App\Services\ConsentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * ConsentController
 * -----------------
 * Two jobs:
 *   1. Admin — manage the catalogue of consent purposes (the things patients
 *      can agree to). This screen can be richer; it's for clinic admins.
 *   2. Per-patient — capture/withdraw a patient's consents and view their
 *      tamper-evident history. The capture screen is kept dead-simple for
 *      front-desk staff.
 *
 * All the real work lives in ConsentService; this controller just validates
 * input and picks the view.
 */
class ConsentController extends Controller
{
    public function __construct(private ConsentService $consent) {}

    // ── Admin: purpose catalogue ───────────────────────────────────────────

    public function index()
    {
        $purposes = ConsentPurpose::orderBy('sort_order')->orderBy('name')->get();
        return view('consent.purposes', compact('purposes'));
    }

    public function storePurpose(Request $request)
    {
        $data = $this->validatePurpose($request);
        ConsentPurpose::create($data);

        return back()->with('success', 'Consent purpose added.');
    }

    public function updatePurpose(Request $request, ConsentPurpose $purpose)
    {
        $data = $this->validatePurpose($request, $purpose);

        // If the wording changed, bump the version so prior consents can be
        // flagged for re-consent.
        if (isset($data['description']) && $data['description'] !== $purpose->description) {
            $data['version'] = $purpose->version + 1;
        }

        $purpose->update($data);

        return back()->with('success', 'Consent purpose updated.');
    }

    public function togglePurpose(ConsentPurpose $purpose)
    {
        $purpose->update(['active' => ! $purpose->active]);

        return back()->with('success', $purpose->active ? 'Purpose activated.' : 'Purpose retired.');
    }

    // ── Per-patient: capture & withdraw ─────────────────────────────────────

    public function patient(Patient $patient)
    {
        $state = $this->consent->stateFor($patient);
        return view('consent.patient', compact('patient', 'state'));
    }

    public function updatePatient(Request $request, Patient $patient)
    {
        // Minors need a parent/guardian recorded as the consenting party (DPDP 5.5).
        $isMinor = $patient->isMinor();

        $request->validate([
            'granted'               => ['array'],
            'granted.*'             => ['integer'],
            'notes'                 => ['nullable', 'string', 'max:1000'],
            'guardian_name'         => [$isMinor ? 'required' : 'nullable', 'string', 'max:120'],
            'guardian_relationship' => [$isMinor ? 'required' : 'nullable', 'string', 'max:60'],
        ]);

        // Checkboxes: any purpose id present in `granted` = consent given;
        // every other active purpose = withdrawn.
        $grantedIds = array_map('intval', $request->input('granted', []));

        $decisions = $this->consent->purposes()
            ->mapWithKeys(fn (ConsentPurpose $p) => [$p->id => in_array($p->id, $grantedIds, true)])
            ->all();

        $changed = $this->consent->setMany($patient, $decisions, [
            'method'                => 'web',
            'notes'                 => $request->input('notes'),
            'on_behalf_of'          => $isMinor ? 'guardian' : 'self',
            'guardian_name'         => $isMinor ? $request->input('guardian_name') : null,
            'guardian_relationship' => $isMinor ? $request->input('guardian_relationship') : null,
        ]);

        return back()->with('success', $changed
            ? "Consent updated ({$changed} change(s) recorded)."
            : 'No changes — consent was already up to date.');
    }

    public function trail(Patient $patient)
    {
        $logs  = $patient->consentLogs()->with('purpose', 'capturedBy')->orderByDesc('id')->get();
        $valid = $this->consent->verifyChain($patient);

        return view('consent.trail', compact('patient', 'logs', 'valid'));
    }

    // ── Validation ───────────────────────────────────────────────────────────

    private function validatePurpose(Request $request, ?ConsentPurpose $purpose = null): array
    {
        return $request->validate([
            'key' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('consent_purposes', 'key')->ignore($purpose?->id),
            ],
            'name'              => ['required', 'string', 'max:120'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'category'          => ['required', Rule::in(['clinical', 'communication', 'data_sharing', 'research', 'general'])],
            'is_mandatory'      => ['sometimes', 'boolean'],
            'requires_explicit' => ['sometimes', 'boolean'],
            'retention_days'    => ['nullable', 'integer', 'min:0'],
            'sort_order'        => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
