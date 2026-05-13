<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientNoteController extends Controller
{
    public function store(Request $request, Patient $patient)
    {
        $request->validate([
            'note'      => 'required|string|max:2000',
            'note_type' => 'nullable|string|max:100',
        ]);

        $patient->notes()->create([
            'note'       => $request->note,
            'note_type'  => $request->note_type ?? 'general',
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Note added.');
    }

    public function destroy(Patient $patient, PatientNote $note)
    {
        abort_unless($note->patient_id === $patient->id, 403);

        $note->delete();

        return redirect()
            ->route('patients.show', $patient)
            ->with('success', 'Note deleted.');
    }
}
