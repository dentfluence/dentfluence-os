<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientCommunication;
use App\Models\CommunicationQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientCommunicationController extends Controller
{
    /**
     * Return all communications for a patient as JSON (used by Alpine.js).
     *
     * PRM Update 2026-06-13: merges patient_communications records
     * with communication_queue records linked to this patient.
     * Same source data — no duplication.
     */
    public function index(Patient $patient)
    {
        // patient_communications (scheduled/system/manual channel logs)
        $legacy = $patient->communications()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'           => 'pc_' . $c->id,
                'type'         => $c->type,
                'direction'    => $c->direction,
                'is_auto'      => $c->is_auto,
                'status'       => $c->status,
                'subject'      => $c->subject,
                'message'      => $c->message,
                'scheduled_at' => $c->scheduled_at?->toIso8601String(),
                'sent_at'      => $c->sent_at?->toIso8601String(),
                'staff_name'   => $c->staff_name,
                'created_at'   => $c->created_at->toIso8601String(),
                '_source'      => 'patient_communications',
            ]);

        // communication_queue records linked to this patient
        $commQueue = CommunicationQueue::where('patient_id', $patient->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($c) => [
                'id'           => 'cq_' . $c->id,
                'type'         => $c->channel,      // call, whatsapp, walk_in, etc.
                'direction'    => $c->direction,
                'is_auto'      => false,
                'status'       => $c->status === 'closed' ? 'sent' : $c->status,
                'subject'      => $c->comm_type_label . ($c->purpose ? ' — ' . $c->purpose_label : ''),
                'message'      => $c->note,
                'scheduled_at' => null,
                'sent_at'      => $c->created_at->toIso8601String(),
                'staff_name'   => $c->assigned_to,
                'created_at'   => $c->created_at->toIso8601String(),
                '_source'      => 'communication_list',
                '_detail_url'  => route('communication.manager.show', $c->id),
            ]);

        // Merge and sort by created_at descending
        $all = $legacy->concat($commQueue)->sortByDesc('created_at')->values();

        return response()->json($all);
    }

    /**
     * Store a new manual communication log or scheduled entry.
     */
    public function store(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'type'         => ['required', 'in:call,whatsapp,email,sms'],
            'direction'    => ['required', 'in:outgoing,incoming'],
            'status'       => ['required', 'in:scheduled,sent,received,failed,cancelled'],
            'subject'      => ['nullable', 'string', 'max:255'],
            'message'      => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'sent_at'      => ['nullable', 'date'],
        ]);

        $comm = $patient->communications()->create([
            ...$data,
            'is_auto'    => false,
            'created_by' => Auth::id(),
            'staff_name' => Auth::user()?->name ?? 'Staff',
        ]);

        return response()->json([
            'id'           => $comm->id,
            'type'         => $comm->type,
            'direction'    => $comm->direction,
            'is_auto'      => $comm->is_auto,
            'status'       => $comm->status,
            'subject'      => $comm->subject,
            'message'      => $comm->message,
            'scheduled_at' => $comm->scheduled_at?->toIso8601String(),
            'sent_at'      => $comm->sent_at?->toIso8601String(),
            'staff_name'   => $comm->staff_name,
            'created_at'   => $comm->created_at->toIso8601String(),
        ], 201);
    }

    /**
     * Delete a communication record.
     */
    public function destroy(Patient $patient, PatientCommunication $communication)
    {
        abort_unless($communication->patient_id === $patient->id, 403);
        $communication->delete();
        return response()->json(['ok' => true]);
    }
}
