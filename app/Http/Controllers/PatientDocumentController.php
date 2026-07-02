<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PatientDocumentController extends Controller
{
    public function store(Request $request, Patient $patient)
    {
        $request->validate([
            'file'     => 'required|file|max:20480|mimes:jpg,jpeg,png,pdf,dcm,doc,docx',
            'category' => 'required|string|max:100',
            'title'    => 'nullable|string|max:255',
            'notes'    => 'nullable|string|max:2000',
        ]);

        $file = $request->file('file');
        $path = $file->store("patients/{$patient->id}/documents", 'public');

        $doc = $patient->documents()->create([
            'uploaded_by'   => Auth::id(),
            'category'      => $request->category,
            'title'         => $request->title,
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'notes'         => $request->notes,
        ]);

        return response()->json(['success' => true, 'document' => $doc]);
    }

    public function destroy(Patient $patient, PatientDocument $document)
    {
        abort_unless($document->patient_id === $patient->id, 403);

        Storage::disk('public')->delete($document->path);
        $document->delete();

        return response()->json(['success' => true]);
    }
}
