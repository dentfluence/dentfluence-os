<?php

namespace App\Http\Controllers\Communication;

use App\Models\Appointment;
use App\Models\AppNotification;
use App\Models\CommunicationQueue;
use App\Models\FollowUp;
use App\Models\Patient;
use App\Models\Task;
use App\Models\User;
use App\Modules\Huddle\Models\HuddleTaskLog;
use App\Modules\Huddle\Repositories\HuddleBoardRepository;
use App\Services\Tasks\TaskOutcomeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class TaskController extends Controller
{
    /**
     * The staff work list.
     *
     * Rewritten for Task Manager V2. Two things changed and both matter:
     *
     * 1. FILTERING IS SERVER-SIDE. The old screen rendered four fixed buckets
     *    and shipped a search box, a staff dropdown, counter cards and a
     *    Daily/Weekly/Monthly control whose Alpine state nothing ever read —
     *    every one of those controls was dead. Reception had been clicking
     *    them for months. Filters now live in the query string, so a refresh
     *    keeps them and a filtered view can be linked to.
     *
     * 2. THE COUNTS AND THE ROWS COME FROM THE SAME QUERY. The card count and
     *    the list underneath it cannot disagree, because the cards are built
     *    by cloning the base query rather than by counting a different one.
     */
    public function index(Request $request)
    {
        $base = $this->scopedQuery();

        // Counts are always for the WHOLE scope, never the current view —
        // otherwise clicking "Overdue" would rewrite every other number.
        $counts = [
            'open'      => (clone $base)->open()->count(),
            'overdue'   => (clone $base)->open()->whereDate('due_date', '<', today())->count(),
            'today'     => (clone $base)->open()->whereDate('due_date', today())->count(),
            'week'      => (clone $base)->open()
                                ->whereBetween('due_date', [today(), today()->copy()->endOfWeek()])
                                ->count(),
            'done'      => (clone $base)->where('status', 'done')->count(),
            'unassigned'=> (clone $base)->open()->whereNull('assigned_to')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $filters = [
            'view'        => $request->get('view', 'open'),
            'q'           => trim((string) $request->get('q', '')),
            'assigned_to' => $request->get('assigned_to'),
            'category'    => $request->get('category'),
            'priority'    => $request->get('priority'),
            // "What is on for the 4th?" — a single day, closed work included,
            // because on a chosen day you want the whole picture, not just
            // what is still outstanding.
            'date'        => $request->get('date'),
        ];

        $query = $base->with(['assignedTo', 'patient', 'protocol.materials']);

        // A chosen date overrides the view entirely — the two answer different
        // questions and stacking them would show an empty screen and no reason.
        if ($filters['date']) {
            $query->whereDate('due_date', $filters['date']);
        } else {
        match ($filters['view']) {
            'overdue'   => $query->open()->whereDate('due_date', '<', today()),
            'today'     => $query->open()->whereDate('due_date', today()),
            'week'      => $query->open()->whereBetween('due_date', [today(), today()->copy()->endOfWeek()]),
            'done'      => $query->where('status', 'done'),
            'cancelled' => $query->where('status', 'cancelled'),
            'all'       => null,
            default     => $query->open(),
        };
        }

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                  ->orWhere('description', 'like', $term);
            });
        }

        // 'none' is not a user id — it is the question "what does nobody own?".
        // A task with no owner is a task nobody does, and until now there was
        // no way to find one.
        if ($filters['assigned_to'] === 'none') {
            $query->whereNull('assigned_to');
        } elseif ($filters['assigned_to']) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        if ($filters['category'])    $query->where('category', $filters['category']);
        if ($filters['priority'])    $query->where('priority', $filters['priority']);

        // Back-compat: HuddleController links here as ?status=escalated.
        // Keep that link working rather than silently landing on the open list.
        if ($request->get('status') === 'escalated') {
            $query->where('status', 'escalated');
        }

        // Practice Protocols filter, kept from the old screen.
        $source = $request->get('source');
        if ($source === 'protocol') $query->whereNotNull('practice_protocol_id');

        // Oldest promise first, then urgency. Closed views read newest first.
        if (in_array($filters['view'], ['done', 'cancelled'], true)) {
            $query->orderByDesc('updated_at');
        } else {
            $query->orderBy('due_date')
                  ->orderByRaw("FIELD(priority,'urgent','high','medium','low')");
        }

        $tasks = $query->paginate(50)->withQueryString();

        $users = User::where('branch_id', Auth::user()->branch_id)->orderBy('name')->get();

        // For the inline "book an appointment" form in the close drawer. Same
        // definition of a doctor the appointments screen uses — copied rather
        // than invented, so the two lists cannot disagree.
        $doctors = User::where('branch_id', Auth::user()->branch_id)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('tasks.index', compact('tasks', 'counts', 'filters', 'users', 'doctors', 'source'));
    }

    /**
     * Branch + reception-visibility + role scope, with no view filter applied.
     * Every count and every list on this screen starts here, so they can
     * never drift apart.
     */
    private function scopedQuery()
    {
        $query = Task::query()
            ->where('branch_id', Auth::user()->branch_id)
            ->visibleToReception(); // hides Automation record-tasks (CEO rule, 6 Sep)

        // Staff-level roles see only their own work; admin / doctors see all.
        $staffRoles = [
            User::ROLE_ASSISTANT,
            User::ROLE_FRONT_DESK,
            User::ROLE_ACCOUNTS,
        ];
        if (in_array(Auth::user()->role, $staffRoles)) {
            $query->where('assigned_to', Auth::id());
        }

        return $query;
    }

    // ── create (fallback page) ────────────────────────────────────────────────
    public function create()
    {
        $users = User::where('branch_id', Auth::user()->branch_id)->orderBy('name')->get();
        return view('tasks.create', compact('users'));
    }

    // ── store ─────────────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string|max:1000',
            'assigned_to'         => 'required|exists:users,id',
            'due_date'            => 'required|date',
            // The create form has always rendered a Due Time field and this
            // rule never existed, so every time a staff member typed was
            // dropped without a word. The column is fillable and other
            // producers write it, which is why some tasks showed a time and
            // hand-made ones never did.
            'due_time'            => 'nullable|date_format:H:i',
            'priority'            => 'required|in:urgent,high,medium,low',
            'category'            => 'required|in:clinical,admin,lab,follow_up,call,whatsapp,maintenance,other',
            'patient_id'          => 'nullable|exists:patients,id',
            // Communication categories with no linked patient — vendor/lab/
            // doctor/other contact instead. See feature-spec-manual-add-call.md.
            'contact_name'        => 'nullable|string|max:255',
            'contact_type'        => 'nullable|in:vendor,lab,consultant,other',
            // Maintenance / recurring fields
            // Built from the clinic's own list, not a frozen string. The old
            // hard-coded `in:` rule would have rejected every type a clinic
            // added in Settings.
            'maintenance_type'    => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Task::maintenanceTypeOptions()))],
            'is_recurring'        => 'boolean',
            'recurrence_interval' => 'nullable|integer|min:1|max:365',
            'recurrence_unit'     => 'nullable|in:days,weeks,months,years',
        ]);

        // ── Duplicate guard ─────────────────────────────────────────────────
        // TaskEngine::autoCreate(), ProtocolGenerationService and
        // InventoryService each dedupe their own output. Manual creation — the
        // one path two people use at the same desk — had no guard at all, so
        // the same call could sit on the board twice with two different
        // outcome trails.
        //
        // A WARNING, not a wall: same title, same owner, same day and still
        // open is usually a mistake, but a clinic can legitimately want two.
        // Sending force=1 creates it anyway.
        if (! $request->boolean('force')) {
            $duplicate = Task::where('branch_id', Auth::user()->branch_id)
                ->where('title', $data['title'])
                ->where('assigned_to', $data['assigned_to'])
                ->whereDate('due_date', $data['due_date'])
                ->open()
                ->first();

            if ($duplicate) {
                $message = 'An open task with this title is already on '
                    . ($duplicate->assignedTo?->name ?? 'someone')
                    . "'s list for " . $duplicate->due_date->format('d M') . '.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok'          => false,
                        'duplicate'   => true,
                        'existing_id' => $duplicate->id,
                        'message'     => $message . ' Send force=1 to create it anyway.',
                    ], 409);
                }

                // The create drawer is a plain form post, so a JSON body would
                // land on screen as raw text. Send the person back with what
                // they typed still in the fields and the drawer reopened.
                return back()
                    ->withInput()
                    ->with('duplicate_warning', $message);
            }
        }

        // Normalise: only save recurring fields when category=maintenance
        if (($data['category'] ?? '') !== 'maintenance') {
            $data['is_recurring']        = false;
            $data['recurrence_interval'] = null;
            $data['recurrence_unit']     = null;
            $data['maintenance_type']    = null;
        }

        // contact_name/contact_type aren't Task columns — they're only used
        // below to create the CommunicationQueue row for a Call/WhatsApp task
        // with no linked patient. Pull them out before Task::create().
        $contactName = $data['contact_name'] ?? null;
        $contactType = $data['contact_type'] ?? null;
        unset($data['contact_name'], $data['contact_type']);

        $task = Task::create([
            ...$data,
            'branch_id'  => Auth::user()->branch_id,
            'created_by' => Auth::id(),
            'status'     => 'pending',
        ]);

        // ── Communication categories → also create the record Today's
        // Actions actually reads (2026-07-08). A Task row alone never showed
        // up on the Action Board; Call/WhatsApp/Follow-up now additionally
        // create a FollowUp (patient linked) or CommunicationQueue (no
        // patient — vendor/lab/doctor/other) row, so "+ Add Call" on Today's
        // Actions and a Communication-category Task are the same action.
        // See docs/feature-specs/feature-spec-manual-add-call.md.
        if (in_array($task->category, Task::COMM_CATEGORIES, true)) {
            try {
                if ($task->patient_id) {
                    FollowUp::create([
                        'patient_id'   => $task->patient_id,
                        'label'        => $task->title,
                        'note'         => $task->description,
                        'trigger_type' => 'manual',
                        'due_date'     => $task->due_date,
                        'due_time'     => $task->due_time,
                        'channel'      => $task->category === 'whatsapp' ? 'whatsapp' : 'call',
                        'priority'     => $task->priority,
                        'status'       => 'pending',
                        'assigned_to'  => $task->assigned_to,
                        'auto_created' => false,
                    ]);
                } elseif ($task->category !== 'follow_up' && $contactName) {
                    // Follow-up is inherently about a specific patient — no
                    // contact-name fallback for that category (see spec).
                    CommunicationQueue::create([
                        'person_name'    => $contactName,
                        'phone'          => '',
                        'channel'        => $task->category === 'whatsapp' ? 'whatsapp' : 'call',
                        'comm_type'      => $contactType === 'consultant' ? 'doctor' : ($contactType ?? 'other'),
                        'contact_type'   => $contactType ?? 'other',
                        'source_engine'  => 'manual',
                        'status'         => 'pending',
                        'priority'       => $task->priority,
                        'note'           => $task->description,
                        'follow_up_date' => $task->due_date,
                        'assigned_to'    => optional(User::find($task->assigned_to))->name,
                        'created_by'     => Auth::id(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Task-to-CommunicationQueue/FollowUp sync failed: ' . $e->getMessage(), [
                    'task_id' => $task->id,
                ]);
            }
        }

        // ── Wire to Daily Huddle board ──────────────────────────────────────
        try {
            $assignedUser = User::find($task->assigned_to);
            if ($assignedUser) {
                $boardRepo = app(HuddleBoardRepository::class);
                $board = $boardRepo->findOrCreateForToday(
                    branchId: $task->branch_id,
                    role:     $assignedUser->role ?? 'staff',
                );
                HuddleTaskLog::firstOrCreate(
                    ['task_id' => $task->id, 'huddle_board_id' => $board->id],
                    ['status' => 'pending', 'carried_forward' => false]
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('HuddleTaskLog sync failed: ' . $e->getMessage());
        }
        // ───────────────────────────────────────────────────────────────────

        // ── Tell the assignee (and the manager) ───────────────────────────
        // Routed through NotificationDispatcher, not AppNotification::notify().
        // The direct call bypassed the whole engine: no notification_rules, no
        // Settings matrix, no dedupe, and — because the row was written with
        // push unset — no phone ever buzzed. The dispatcher resolves WHO from
        // the rules and queues the push itself.
        app(\App\Services\Notifications\NotificationDispatcher::class)->fire('task.assigned', [
            'title'        => 'New task assigned to you',
            'message'      => "\"{$task->title}\" — due {$task->due_date->format('d M Y')}.",
            'source'       => $task,
            'branch_id'    => $task->branch_id,
            'owner'        => $task->assigned_to,
            'action_url'   => route('tasks.index'),
            'action_label' => 'View Tasks',
        ]);
        // ────────────────────────────────────────────────────────────────────

        $task->load(['assignedTo', 'patient']);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'   => true,
                'task' => [
                    'id'               => $task->id,
                    'title'            => $task->title,
                    'description'      => $task->description,
                    'priority'         => $task->priority,
                    'due_date'         => $task->due_date->format('d M Y'),
                    'due_date_ts'      => $task->due_date->toDateString(),
                    'assigned_to'      => $task->assignedTo->name,
                    'patient_name'     => $task->patient?->name,
                    'category'         => $task->category,
                    'status'           => $task->status,
                    'is_recurring'     => $task->is_recurring,
                    'recurrence_label' => $task->recurrenceLabel(),
                ],
            ]);
        }

        return redirect()->route('tasks.index')->with('success', 'Task created.');
    }

    // ── markDone ──────────────────────────────────────────────────────────────
    /**
     * Complete a task, WITH the reason attached.
     *
     * The old version flipped status to 'done' and threw away what happened.
     * An outcome and an optional note now ride along, and the decision about
     * whether that outcome may actually close the task belongs to
     * TaskOutcomeService — send it "no answer" and you get an attempt back,
     * not a completed task.
     */
    public function markDone(Task $task, Request $request, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate([
            'outcome_key' => 'nullable|string|max:60',
            'note'        => 'nullable|string|max:1000',
            // Optional "what happens next", captured at the moment the work
            // closes. This is where follow-through is won or lost: asked here
            // it takes one line, asked tomorrow it never gets asked.
            'next'             => 'nullable|in:task,appointment',
            'next_title'       => 'nullable|string|max:255',
            'next_due_date'    => 'nullable|date|after_or_equal:today',
            // The follow-up is a real task, so its owner, type and priority are
            // chosen, not guessed. Each falls back to the closing task's value
            // when the drawer sends nothing, which keeps the API compatible
            // with the mobile app and with the older two-field payload.
            'next_assigned_to' => 'nullable|exists:users,id',
            'next_category'    => 'nullable|in:' . implode(',', array_keys(Task::CATEGORIES)),
            'next_priority'    => 'nullable|in:urgent,high,medium,low',
            // Set by the drawer AFTER the calendar has accepted the slot, so
            // by the time it arrives the appointment provably exists. The task
            // is never the thing that creates it.
            'appointment_id'   => 'nullable|exists:appointments,id',
        ]);

        // ── Evidence gate ────────────────────────────────────────────────────
        // Protocol tasks flagged "requires_evidence" cannot be completed until
        // proof has been attached. The board should instead call uploadEvidence.
        if ($task->requires_evidence) {
            $hasProof = HuddleTaskLog::where('task_id', $task->id)
                ->whereNotNull('proof_path')
                ->exists();

            if (! $hasProof) {
                return response()->json([
                    'ok'             => false,
                    'needs_evidence' => true,
                    'message'        => 'Please attach evidence before completing this task.',
                ], 422);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        $outcome = $outcomes->done($task, $data['outcome_key'] ?? null, $data['note'] ?? null);
        $task->refresh();

        // An outcome meaning the work never happened leaves the task open.
        if ($task->isOpen()) {
            HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'pending']);

            return response()->json([
                'ok'            => true,
                'closed'        => false,
                'status'        => $task->status,
                'attempt_label' => $task->attemptLabel(),
                'message'       => 'Logged as attempted — the task stays on the list.',
            ]);
        }

        HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'done']);

        // ── The booking this task produced ──────────────────────────────────
        // Stamped only on a genuine close. An outcome that leaves the task open
        // has already returned above, so a task can never be "converted" while
        // still being worked on.
        //
        // Both guards matter: an appointment from another branch would leak a
        // patient across clinics, and one for a different patient would make
        // the conversion figure a lie. Either way the link is simply not made —
        // the close still stands, because the work did happen.
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::find($data['appointment_id']);

            if ($appointment
                && (int) $appointment->branch_id === (int) $task->branch_id
                && (! $task->patient_id || (int) $appointment->patient_id === (int) $task->patient_id)
            ) {
                $task->appointment_id = $appointment->id;
                $task->save();
            } else {
                Log::warning('Task ' . $task->id . ' closed with an appointment link that did not match branch/patient; link skipped.');
            }
        }
        // ────────────────────────────────────────────────────────────────────

        // ── Chain: a follow-up task, created from the one just closed ───────
        // The patient and the branch are carried forward and are NOT negotiable:
        // the follow-up is about the same case by definition, and a task that
        // crosses branches has no owner anyone can find.
        //
        // The owner, type and priority ARE negotiable and come from the form.
        // They fall back to the closing task's values, which is what the drawer
        // pre-fills them with anyway — so the common path is unchanged and the
        // lab-follow-up-assigned-to-the-receptionist case is now fixable in one
        // dropdown instead of a second edit.
        $chained = null;
        if (($data['next'] ?? null) === 'task' && ! empty($data['next_title'])) {
            $assignee = $data['next_assigned_to'] ?? $task->assigned_to;

            // A follow-up cannot be handed to someone in another branch; that
            // would create work nobody in either branch sees on their board.
            if ($assignee && $assignee !== $task->assigned_to) {
                $ok = User::where('id', $assignee)
                    ->where('branch_id', $task->branch_id)
                    ->exists();
                if (! $ok) {
                    $assignee = $task->assigned_to;
                }
            }

            $chained = Task::create([
                'title'       => $data['next_title'],
                'description' => $data['note'] ?? null,
                'assigned_to' => $assignee,
                'created_by'  => Auth::id(),
                'branch_id'   => $task->branch_id,
                'patient_id'  => $task->patient_id,
                'due_date'    => $data['next_due_date'] ?? today()->addDay(),
                'priority'    => $data['next_priority'] ?? $task->priority,
                'category'    => $data['next_category'] ?? $task->category,
                'status'      => 'pending',
            ]);

            // Reassignment is a notification event everywhere else in the
            // module; a chained task handed to someone else is no different.
            if ($chained->assigned_to && $chained->assigned_to !== Auth::id()) {
                try {
                    app(\App\Services\Notifications\NotificationDispatcher::class)->fire('task.assigned', [
                        'title'        => 'Follow-up task assigned to you',
                        'message'      => "\"{$chained->title}\" — due {$chained->due_date->format('d M Y')}.",
                        'source'       => $chained,
                        'branch_id'    => $chained->branch_id,
                        'owner'        => $chained->assigned_to,
                        'action_url'   => route('tasks.index'),
                        'action_label' => 'View Tasks',
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Chained task notify failed: ' . $e->getMessage());
                }
            }
        }

        // ── Auto-spawn next occurrence for recurring/AMC tasks ───────────────
        $nextTask = null;
        if ($task->is_recurring && $task->recurrence_interval && $task->recurrence_unit) {
            try {
                $nextTask = $task->spawnNext();
            } catch (\Throwable $e) {
                \Log::warning('Task auto-spawn failed: ' . $e->getMessage());
            }
        }
        // ────────────────────────────────────────────────────────────────────

        // NOTE: 'next' => 'appointment' is handled entirely by the drawer, which
        // posts to AppointmentController@store BEFORE calling this endpoint —
        // so a clashing slot leaves the task open rather than closing work that
        // was never actually scheduled. Nothing to do here.

        return response()->json([
            'ok'             => true,
            'closed'         => true,
            'status'         => $task->status,
            'is_recurring'   => $task->is_recurring,
            'next_due_date'  => $nextTask?->due_date->format('d M Y'),
            'next_task_id'   => $nextTask?->id,
            'chained_task_id'=> $chained?->id,
            'appointment_id' => $task->appointment_id,
        ]);
    }

    // ── attempt ───────────────────────────────────────────────────────────────
    /** Work happened, the task is not finished. Stays on the list. */
    public function attempt(Task $task, Request $request, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate([
            'outcome_key' => 'nullable|string|max:60',
            'note'        => 'nullable|string|max:1000',
        ]);

        $outcomes->attempt($task, $data['outcome_key'] ?? null, $data['note'] ?? null);
        $task->refresh();

        return response()->json([
            'ok'            => true,
            'attempt_label' => $task->attemptLabel(),
        ]);
    }

    // ── reschedule ────────────────────────────────────────────────────────────
    /**
     * Move the task to a later date. A reason is required — a reschedule with
     * no reason is indistinguishable from avoidance, and the owner reading the
     * trail later needs to know which it was.
     */
    public function reschedule(Task $task, Request $request, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate([
            'due_date'    => 'required|date|after_or_equal:today',
            'note'        => 'required|string|max:1000',
            'outcome_key' => 'nullable|string|max:60',
        ]);

        $outcomes->reschedule($task, $data['due_date'], $data['note'], $data['outcome_key'] ?? null);
        $task->refresh();

        return response()->json([
            'ok'               => true,
            'due_date'         => $task->due_date->format('d M Y'),
            'reschedule_count' => $task->reschedule_count,
            'days_late'        => $task->daysLate(),
        ]);
    }

    // ── cancel ────────────────────────────────────────────────────────────────
    /**
     * Close a task without claiming the work was done. Marking such a task
     * 'done' would report work nobody did.
     */
    public function cancel(Task $task, Request $request, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $outcomes->cancel($task, $data['reason']);
        HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'done']);

        return response()->json(['ok' => true, 'status' => 'cancelled']);
    }

    // ── reopen ────────────────────────────────────────────────────────────────
    public function reopen(Task $task, Request $request, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        $outcomes->reopen($task, $data['note'] ?? null);
        HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'pending']);

        return response()->json(['ok' => true, 'status' => 'pending']);
    }

    // ── update ────────────────────────────────────────────────────────────────
    /**
     * Edit a task's details: title, description, priority, owner, type.
     *
     * DUE DATE IS DELIBERATELY NOT EDITABLE HERE. Moving a date is a
     * RESCHEDULE: it demands a reason, stamps original_due_date the first time,
     * counts itself, and leaves the overdue clock running. If the same move
     * were possible through a plain edit, every one of those guards could be
     * walked around and the backlog could be cleared by quietly pushing dates —
     * which is the single thing this module was rebuilt to stop.
     * Same for status: closing goes through markDone/cancel, never a field.
     */
    public function update(Task $task, Request $request, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority'    => 'required|in:urgent,high,medium,low',
            'assigned_to' => 'required|exists:users,id',
            'category'    => 'required|in:' . implode(',', array_keys(Task::CATEGORIES)),
        ]);

        $previousOwner = (int) $task->assigned_to;

        $task->update($data);

        // A handover is the one edit somebody else needs to hear about. The
        // others (a clearer title, a bumped priority) are visible on the board
        // already and do not deserve a notification each.
        if ((int) $task->assigned_to !== $previousOwner) {
            app(\App\Services\Notifications\NotificationDispatcher::class)->fire('task.assigned', [
                'title'        => 'Task handed over to you',
                'message'      => "\"{$task->title}\" — due {$task->due_date->format('d M Y')}.",
                'source'       => $task,
                'branch_id'    => $task->branch_id,
                'owner'        => $task->assigned_to,
                'dedupe_scope' => 'reassign:' . now()->timestamp,
                'action_url'   => route('tasks.index'),
                'action_label' => 'View Tasks',
            ]);
        }

        $task->refresh();

        return response()->json([
            'ok'   => true,
            'task' => [
                'id'             => $task->id,
                'title'          => $task->title,
                'category'       => $task->category,
                'category_label' => $task->categoryLabel(),
                'priority'       => $task->priority,
                'assigned_to'    => $task->assignedTo?->name,
            ],
            'message' => 'Task updated.',
        ]);
    }

    // ── show (drawer) ─────────────────────────────────────────────────────────
    /** Everything the row drawer needs: the task, its outcome list, its trail. */
    public function show(Task $task, TaskOutcomeService $outcomes)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $task->load(['assignedTo', 'patient', 'outcomes.user', 'appointment.doctor']);

        return response()->json([
            'ok' => true,
            'task' => [
                'id'               => $task->id,
                'title'            => $task->title,
                'description'      => $task->description,
                'category'         => $task->category,
                'category_label'   => $task->categoryLabel(),
                'priority'         => $task->priority,
                'status'           => $task->status,
                'due_date'         => $task->due_date->toDateString(),
                'due_date_label'   => $task->due_date->format('d M Y'),
                'assigned_to'      => $task->assignedTo?->name,
                // Raw id for the edit form's staff dropdown to prefill from
                // ('assigned_to' above is the display name).
                'assigned_to_id'   => $task->assigned_to,
                'patient_name'     => $task->patient?->name,
                // The close drawer books an appointment for this patient
                // against the calendar's own endpoint.
                'patient_id'       => $task->patient_id,
                'attempt_label'    => $task->attemptLabel(),
                'days_late'        => $task->daysLate(),
                'reschedule_count' => (int) $task->reschedule_count,
                'original_due'     => $task->original_due_date?->format('d M Y'),
                'requires_evidence'=> (bool) $task->requires_evidence,
                'is_open'          => $task->isOpen(),
                // The booking this task produced, if any. The drawer turns this
                // into a link so the closed task is one click from the chair it
                // filled — otherwise the link exists only in the database and
                // nobody ever sees it.
                'appointment'      => $task->appointment ? [
                    'id'     => $task->appointment->id,
                    'label'  => $task->appointment->appointment_date->format('d M Y')
                                . ', ' . \Carbon\Carbon::parse($task->appointment->appointment_time)->format('h:i A'),
                    'doctor' => $task->appointment->doctor?->name,
                    'url'    => route('appointments.index', ['date' => $task->appointment->appointment_date->toDateString()]),
                ] : null,
            ],
            'options'          => $outcomes->optionsFor($task),
            'non_closing_keys' => $outcomes->nonClosingKeysFor($task),
            'trail' => $task->outcomes->map(fn ($o) => [
                'action'  => $o->action,
                'summary' => $o->summary(),
                'user'    => $o->user?->name,
                'at'      => $o->created_at->format('d M, h:i A'),
            ])->values(),
        ]);
    }

    // ── uploadEvidence ──────────────────────────────────────────────────────────
    // Attach proof to a task and complete it in one step. Used by protocol tasks
    // that require evidence. Reuses the Huddle proof store (huddle_task_logs).
    public function uploadEvidence(Task $task, Request $request)
    {
        abort_if($task->branch_id !== Auth::user()->branch_id, 403);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $path = $request->file('proof')->store("tasks/evidence/{$task->id}", 'local');

        // Ensure a HuddleTaskLog exists for this task (protocol tasks may not have one yet).
        try {
            $assignedUser = User::find($task->assigned_to);
            $board = app(HuddleBoardRepository::class)->findOrCreateForToday(
                branchId: $task->branch_id,
                role:     $assignedUser->role ?? 'staff',
            );
            $log = HuddleTaskLog::firstOrCreate(
                ['task_id' => $task->id, 'huddle_board_id' => $board->id],
                ['status' => 'pending', 'carried_forward' => false],
            );
            $log->update([
                'proof_path'        => $path,
                'proof_uploaded_at' => now(),
                'status'            => 'done',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Task evidence log sync failed: ' . $e->getMessage());
        }

        $task->update(['status' => 'done', 'done_at' => now()]);
        HuddleTaskLog::where('task_id', $task->id)->update(['status' => 'done']);

        // Recurring protocols still auto-spawn their next occurrence.
        $nextTask = null;
        if ($task->is_recurring && $task->recurrence_interval && $task->recurrence_unit) {
            try {
                $nextTask = $task->spawnNext();
            } catch (\Throwable $e) {
                \Log::warning('Task auto-spawn failed: ' . $e->getMessage());
            }
        }

        return response()->json([
            'ok'            => true,
            'is_recurring'  => $task->is_recurring,
            'next_due_date' => $nextTask?->due_date->format('d M Y'),
        ]);
    }

    // ── escalate ──────────────────────────────────────────────────────────────
    /*
     * escalate() REMOVED 22 Sep. It wrote `is_escalated`, a column that has
     * never existed on the tasks table, so every call 500'd — and nothing
     * called it: no view, no test, no API. It was a route and a method and
     * nothing else.
     *
     * Not replaced. "Escalate" was never defined as a behaviour, and what it
     * was reaching for is already possible: raise the priority or hand the
     * task to someone else, both through Edit, both recorded.
     *
     * tasks.status still carries 'escalated' as a legacy value and
     * HuddleController still counts it, so existing rows keep rendering.
     */

    public function myTasks(\Illuminate\Http\Request $request)
    {
        // Always scoped to the logged-in user — regardless of role.
        // Admin on the main dashboard sees all; here they see only their own.
        $tasks = Task::with(['assignedTo', 'patient', 'protocol.materials'])
            ->where('assigned_to', Auth::id())
            ->visibleToReception() // Phase 3: hides System (Automation-record) tasks once tasks.human_system_split is on
            ->orderBy('due_date')
            ->get();

        $overdue  = $tasks->filter(fn($t) => $t->status === 'pending' && $t->due_date->lt(today()) && !$t->due_date->isToday());
        $today    = $tasks->filter(fn($t) => $t->due_date->isToday()  && $t->status !== 'done');
        $upcoming = $tasks->filter(fn($t) => $t->due_date->isFuture() && $t->status !== 'done');
        $done     = $tasks->filter(fn($t) => $t->status === 'done');

        return view('tasks.mine', compact('overdue', 'today', 'upcoming', 'done'));
    }

    /**
     * Kept as a route because older links and bookmarks point at it. The page
     * it used to render was a heading and a "TODO: list overdue tasks"
     * comment — a live URL that answered nothing. The list itself does this
     * properly now.
     */
    public function overdue()
    {
        return redirect()->route('tasks.index', ['view' => 'overdue']);
    }
}
