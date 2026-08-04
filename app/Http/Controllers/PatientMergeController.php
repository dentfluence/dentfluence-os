<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientMerge;
use App\Services\Patient\PatientMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Patient merge — web wizard (slice 3). Admin-only (routes carry `admin.only`;
 * each action re-checks as a controller backstop). All business logic lives in
 * PatientMergeService — this controller is transport only.
 */
class PatientMergeController extends Controller
{
    /** Demographic fields offered for side-by-side reconciliation (field => label). */
    private const DIFF_FIELDS = [
        'name'            => 'Full name',
        'date_of_birth'   => 'Date of birth',
        'gender'          => 'Gender',
        'phone'           => 'Phone',
        'alternate_phone' => 'Alternate phone',
        'email'           => 'Email',
        'address'         => 'Address',
        'area'            => 'Area',
        'city'            => 'City',
        'pincode'         => 'Pincode',
        'occupation'      => 'Occupation',
        'source'          => 'Source',
        'medical_alert'   => 'Medical alert',
    ];

    public function __construct(private PatientMergeService $merges)
    {
    }

    /** Storable (comparable) value for a field. */
    private function rawVal(Patient $p, string $field): ?string
    {
        if ($field === 'date_of_birth') {
            return $p->date_of_birth?->format('Y-m-d');
        }
        $v = $p->{$field};
        return is_null($v) ? null : (string) $v;
    }

    /** Human-readable value for a field. */
    private function displayVal(Patient $p, string $field): string
    {
        if ($field === 'date_of_birth') {
            return $p->date_of_birth?->format('d M Y') ?: '—';
        }
        if ($field === 'gender') {
            return $p->gender ? ucfirst($p->gender) : '—';
        }
        $v = $p->{$field};
        return ($v === null || $v === '') ? '—' : (string) $v;
    }

    /** Fields where master & loser disagree, with both values (display + raw). */
    private function computeDiffs(Patient $master, Patient $loser): array
    {
        $out = [];
        foreach (self::DIFF_FIELDS as $field => $label) {
            $mr = $this->rawVal($master, $field);
            $lr = $this->rawVal($loser, $field);
            if ((string) $mr === (string) $lr) {
                continue;
            }
            $out[] = [
                'field'      => $field,
                'label'      => $label,
                'master'     => $this->displayVal($master, $field),
                'loser'      => $this->displayVal($loser, $field),
                'master_raw' => $mr ?? '',
                'loser_raw'  => $lr ?? '',
            ];
        }
        return $out;
    }

    /** The merge screen for a chosen master (surviving) patient. */
    public function create(Patient $patient)
    {
        abort_unless(Auth::user()?->isAdminRole(), 403);
        abort_if($patient->isMerged(), 404);

        return view('patients.merge', ['master' => $patient]);
    }

    /** AJAX: preview what a merge of {loser_id} into this master would move. */
    public function preview(Request $request, Patient $patient)
    {
        abort_unless(Auth::user()?->isAdminRole(), 403);

        $request->validate(['loser_id' => ['required', 'integer', 'exists:patients,id']]);
        $loser = Patient::find((int) $request->loser_id);

        if (! $loser || $loser->id === $patient->id) {
            return response()->json(['ok' => false, 'message' => 'Pick a different record to merge in.'], 422);
        }
        if ($loser->isMerged() || $patient->isMerged()) {
            return response()->json(['ok' => false, 'message' => 'One of these records was already merged.'], 422);
        }

        return response()->json([
            'ok'    => true,
            'loser' => [
                'id'         => $loser->id,
                'name'       => $loser->name,
                'patient_id' => $loser->patient_id,
                'phone'      => $loser->phone,
            ],
            'preview' => $this->merges->preview($loser),
            'diffs'   => $this->computeDiffs($patient, $loser),
        ]);
    }

    /** Execute the merge. */
    public function store(Request $request, Patient $patient)
    {
        abort_unless(Auth::user()?->isAdminRole(), 403);

        $data = $request->validate([
            'loser_id' => ['required', 'integer', 'exists:patients,id'],
            'reason'   => ['required', 'string', 'min:5', 'max:500'],
            'password' => ['required', 'string'],
            'choices'  => ['nullable', 'array'],
        ]);

        if (! Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password. Merge cancelled.'])->withInput();
        }

        $loser = Patient::find((int) $data['loser_id']);
        if (! $loser || $loser->id === $patient->id) {
            return back()->withErrors(['loser_id' => 'Cannot merge a record into itself.'])->withInput();
        }

        // Reconciliation: keep only real overrides (a choice that differs from the
        // master's current value), and restrict to known demographic fields.
        $fieldChoices = [];
        foreach ((array) ($data['choices'] ?? []) as $field => $val) {
            if (! array_key_exists($field, self::DIFF_FIELDS)) {
                continue;
            }
            if ((string) $val !== (string) $this->rawVal($patient, $field)) {
                $fieldChoices[$field] = $val;
            }
        }
        // If the loser's full name was chosen, carry its name parts too, so the
        // display name and its components stay consistent.
        if (array_key_exists('name', $fieldChoices)) {
            $fieldChoices['first_name']  = $loser->first_name;
            $fieldChoices['middle_name'] = $loser->middle_name;
            $fieldChoices['last_name']   = $loser->last_name;
        }

        try {
            $record = $this->merges->merge($patient, $loser, $fieldChoices, Auth::id(), 'manual: '.$data['reason']);
        } catch (\Throwable $e) {
            return back()->withErrors(['loser_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('patients.show', $patient)->with(
            'success',
            "Merged {$loser->name} into this record."
                .($record->retired_patient_id ? " {$record->retired_patient_id} archived." : '')
        );
    }

    /**
     * Undo a merge — the bounded safety net (Final Design §1), not a general
     * rollback. Same password-re-confirmation bar as the merge it reverses;
     * PatientMergeService::undo() itself is the authoritative gate on whether
     * this is actually still allowed (window + zero-activity-since).
     */
    public function undo(Request $request, Patient $patient, PatientMerge $merge)
    {
        abort_unless(Auth::user()?->isAdminRole(), 403);
        abort_if($merge->surviving_patient_id !== $patient->id, 404);

        $data = $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($data['password'], Auth::user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password. Undo cancelled.']);
        }

        try {
            $this->merges->undo($merge, Auth::id());
        } catch (\Throwable $e) {
            return back()->withErrors(['undo' => $e->getMessage()]);
        }

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Merge undone — the record has been restored as a separate patient.');
    }
}
