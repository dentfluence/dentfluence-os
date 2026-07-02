<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\FollowUpNote;
use App\Models\Patient;
use App\Services\Communication\FollowUpRulesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * FollowUpController
 *
 * All actions are now backed by real DB via the FollowUp model.
 * Routes prefix: /communication/followup-engine
 */
class FollowUpController extends Controller
{
    // ── Views ──────────────────────────────────────────────────────────────────

    /**
     * Main calendar / index view.
     * GET /communication/followup-engine
     */
    public function index(Request $request)
    {
        $view = $request->get('view', 'week');

        // Stats bar
        $stats = $this->buildStats();

        // Today's list (sidebar)
        $todayList = $this->buildTodayList();

        // Overdue list (sidebar)
        $overdue = $this->buildOverdueList();

        // Calendar events grouped by date (for current week/month)
        $calendarEvents = $this->buildCalendarEvents($request);

        return view('communication.followup.index', compact(
            'view', 'stats', 'todayList', 'overdue', 'calendarEvents'
        ));
    }

    /**
     * Queue list view.
     * GET /communication/followup-engine/queue
     */
    public function queue(Request $request)
    {
        $filter = $request->get('filter', 'today');

        $query = FollowUp::with(['patient', 'lead'])->latest('due_date');

        $query = match ($filter) {
            'overdue'  => $query->overdue(),
            'upcoming' => $query->upcoming(),
            'all'      => $query,
            default    => $query->dueToday(), // today
        };

        $followUps = $query->get();

        // Group by date string for the view (date => [items])
        $grouped = $followUps->groupBy(fn ($f) => $f->due_date->toDateString())
            ->map(fn ($items) => $items->map(fn ($f) => $this->toCardArray($f))->values()->all())
            ->all();

        // Real counts for filter tabs
        $counts = [
            'today'    => FollowUp::dueToday()->count(),
            'overdue'  => FollowUp::overdue()->count(),
            'upcoming' => FollowUp::upcoming()->count(),
            'all'      => FollowUp::pending()->count(),
        ];

        return view('communication.followup.queue', compact('filter', 'grouped', 'counts'));
    }

    /**
     * Overdue list view.
     * GET /communication/followup-engine/overdue
     */
    public function overdue()
    {
        $overdue = $this->buildOverdueList();
        return view('communication.followup.overdue', compact('overdue'));
    }

    public function calendar()
    {
        return view('communication.followup.calendar');
    }

    public function recalls()
    {
        return view('communication.followup.recalls');
    }

    // ── Actions (POST) ─────────────────────────────────────────────────────────

    /**
     * Complete a follow-up.
     * POST /communication/followup-engine/{id}/complete
     */
    public function complete(Request $request, $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $request->validate([
            'completion_note' => 'nullable|string|max:1000',
        ]);

        $followUp->update([
            'status'          => 'completed',
            'completed_at'    => now(),
            'completed_by'    => Auth::id(),
            'completion_note' => $request->completion_note,
        ]);

        // Auto-trigger next follow-up rules if applicable
        if ($followUp->trigger_type && $followUp->trigger_value) {
            $context = [
                'patient_id'  => $followUp->patient_id,
                'lead_id'     => $followUp->lead_id,
                'assigned_to' => $followUp->assigned_to,
                'base_date'   => now()->toDateString(),
            ];

            // Phase 2, Slice 6 — rules consolidation. rules.single_engine ON routes
            // through the Rules-Engine-owned FollowUpRuleEngine (identical output);
            // OFF (default) uses the legacy service. Instant rollback = flag off.
            $nextRules = \App\Support\Features\Feature::enabled('rules.single_engine')
                ? app(\App\Services\Relationship\FollowUpRuleEngine::class)
                    ->resolve($followUp->trigger_type, $followUp->trigger_value, 'complete', $context)
                : (new FollowUpRulesService())
                    ->resolve($followUp->trigger_type, $followUp->trigger_value, 'complete', $context);

            foreach ($nextRules as $rule) {
                // Avoid duplicates: skip if same label + due_date + patient already exists
                $exists = FollowUp::where('patient_id', $rule['patient_id'])
                    ->where('label', $rule['label'])
                    ->where('due_date', $rule['due_date'])
                    ->where('status', 'pending')
                    ->exists();

                if (! $exists) {
                    FollowUp::create($rule);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Follow-up completed.']);
    }

    /**
     * Reschedule a follow-up.
     * POST /communication/followup-engine/{id}/reschedule
     */
    public function reschedule(Request $request, $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $request->validate([
            'due_date' => 'required|date|after_or_equal:today',
            'due_time' => 'nullable|string',
            'note'     => 'nullable|string|max:500',
        ]);

        $followUp->update([
            'due_date' => $request->due_date,
            'due_time' => $request->due_time ?? $followUp->due_time,
            'status'   => 'rescheduled',
            'note'     => $request->note ?? $followUp->note,
        ]);

        // Reset status to pending so it shows up in queue
        $followUp->update(['status' => 'pending']);

        return response()->json(['success' => true, 'message' => 'Follow-up rescheduled.']);
    }

    /**
     * Schedule a new follow-up manually.
     * POST /communication/followup-engine/schedule
     */
    public function schedule(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'label'      => 'required|string|max:255',
            'due_date'   => 'required|date',
            'due_time'   => 'nullable|string',
            'channel'    => 'nullable|in:call,whatsapp,clinic_visit,any',
            'priority'   => 'nullable|in:high,medium,low',
            'note'       => 'nullable|string|max:1000',
        ]);

        FollowUp::create([
            'patient_id'   => $request->patient_id,
            'label'        => $request->label,
            'due_date'     => $request->due_date,
            'due_time'     => $request->due_time ?? '10:00',
            'channel'      => $request->channel ?? 'call',
            'priority'     => $request->priority ?? 'medium',
            'note'         => $request->note,
            'trigger_type' => 'manual',
            'auto_created' => false,
            'assigned_to'  => Auth::id(),
            'status'       => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Follow-up scheduled.']);
    }

    /**
     * Add a note to a follow-up.
     * POST /communication/followup-engine/{id}/note
     */
    public function addNote(Request $request, $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        FollowUpNote::create([
            'follow_up_id' => $followUp->id,
            'user_id'      => Auth::id(),
            'note'         => $request->note,
        ]);

        return response()->json(['success' => true, 'message' => 'Note saved.']);
    }

    /**
     * Change the lead/patient status from this follow-up.
     * POST /communication/followup-engine/{id}/change-status
     */
    public function changeStatus(Request $request, $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $request->validate([
            'follow_up_status' => 'required|string|max:50',
        ]);

        // Update patient's follow_up_status if linked to a patient
        if ($followUp->patient_id) {
            $followUp->patient->update([
                'follow_up_status' => $request->follow_up_status,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Status updated.']);
    }

    /**
     * Convert a lead to patient from this follow-up.
     * POST /communication/followup-engine/{id}/convert
     * (Lead model not yet built — stores intent and returns success for now)
     */
    public function convertToPatient(Request $request, $id)
    {
        $followUp = FollowUp::findOrFail($id);

        // When Lead model is built, create Patient from Lead here.
        // For now, mark follow-up as completed so it leaves the queue.
        $followUp->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'completed_by' => Auth::id(),
            'completion_note' => 'Converted to patient.',
        ]);

        return response()->json(['success' => true, 'message' => 'Marked as converted. Wire Patient creation when Lead module is ready.']);
    }

    /**
     * Create a case/trigger from a follow-up.
     * POST /communication/followup-engine/{id}/create-case
     */
    public function createCase(Request $request, $id)
    {
        $followUp = FollowUp::findOrFail($id);

        $request->validate([
            'case_type' => 'nullable|string|max:100',
            'note'      => 'nullable|string|max:1000',
        ]);

        // Add a note recording the case creation intent
        FollowUpNote::create([
            'follow_up_id' => $followUp->id,
            'user_id'      => Auth::id(),
            'note'         => 'Case created: ' . ($request->case_type ?? 'General') . '. ' . ($request->note ?? ''),
        ]);

        return response()->json(['success' => true, 'message' => 'Case recorded.']);
    }

    // ── Private Helpers ────────────────────────────────────────────────────────

    /**
     * Build the stats array for the index view.
     */
    private function buildStats(): array
    {
        return [
            'total'     => FollowUp::count(),
            'due_today' => FollowUp::dueToday()->count(),
            'overdue'   => FollowUp::overdue()->count(),
            'completed' => FollowUp::completed()->count(),
            'upcoming'  => FollowUp::upcoming()->count(),
        ];
    }

    /**
     * Build today's list for the sidebar — same shape the view expects.
     */
    private function buildTodayList(): array
    {
        return FollowUp::with(['patient', 'lead'])
            ->dueToday()
            ->orderBy('due_time')
            ->get()
            ->map(fn ($f) => [
                'id'      => $f->id,
                'name'    => $f->subjectName(),
                'time'    => Carbon::parse($f->due_time)->format('h:i A'),
                'channel' => $f->channel,
                'tag'     => $f->label,
                'due_in'  => $f->due_date->diffForHumans(['parts' => 1, 'short' => true]),
                'overdue' => false,
                'avatar'  => $f->avatarInitials(),
                'color'   => $f->channelColor(),
            ])
            ->all();
    }

    /**
     * Build overdue list — same shape the view expects.
     */
    private function buildOverdueList(): array
    {
        return FollowUp::with(['patient', 'lead'])
            ->overdue()
            ->orderBy('due_date')
            ->get()
            ->map(fn ($f) => [
                'id'         => $f->id,
                'name'       => $f->subjectName(),
                'date'       => $f->due_date->format('d M, h:i A'),
                'overdue_by' => $f->due_date->diffForHumans(['parts' => 1]),
                'channel'    => $f->channel,
                'avatar'     => $f->avatarInitials(),
                'color'      => '#EF4444',
                'phone'      => $f->subjectPhone() ?? '',
            ])
            ->all();
    }

    /**
     * Build calendar events grouped by date string for the current week.
     */
    private function buildCalendarEvents(Request $request): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek   = Carbon::now()->endOfWeek();

        return FollowUp::with(['patient', 'lead'])
            ->whereBetween('due_date', [$startOfWeek, $endOfWeek])
            ->where('status', 'pending')
            ->orderBy('due_time')
            ->get()
            ->groupBy(fn ($f) => $f->due_date->toDateString())
            ->map(fn ($items) => $items->map(fn ($f) => $this->toCardArray($f))->values()->all())
            ->all();
    }

    /**
     * Convert a FollowUp model to the card array shape used by views.
     */
    private function toCardArray(FollowUp $f): array
    {
        return [
            'id'      => $f->id,
            'name'    => $f->subjectName(),
            'time'    => Carbon::parse($f->due_time)->format('h:i A'),
            'channel' => $f->channel,
            'type'    => $f->due_date->isPast() && $f->status === 'pending' ? 'overdue' : $f->trigger_type ?? 'follow_up',
            'color'   => $f->channelColor(),
            'label'   => $f->label,
            'avatar'  => $f->avatarInitials(),
            'priority'=> $f->priority,
        ];
    }
}
