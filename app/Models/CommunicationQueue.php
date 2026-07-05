<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * CommunicationQueue — Communication List (universal inbox)
 *
 * Every clinic communication enters here first.
 * From here it routes to PRM Pipeline, Follow-ups, Calendar, Task, or Archive.
 *
 * PRM Update 2026-06-13: Added channel, comm_type, purpose, direction,
 * next_action, move_to, follow_up_date/time, created_by, last_modified_by.
 * Phase 1 2026-06-13: Added attempt tracking, SLA fields, mandatory outcome.
 * Status values: pending | waiting_for_patient | overdue | closed
 */
class CommunicationQueue extends Model
{
    use HasFactory;

    protected $table = 'communication_queue';

    protected $fillable = [
        'person_name',
        'phone',
        'whatsapp_number',
        'channel',
        'comm_type',
        'purpose',
        'direction',
        'next_action',
        'move_to',
        'status',
        'is_overdue',
        'overdue_since',
        'priority',
        'note',
        'tags',
        'assigned_to',
        'assigned_avatar',
        'due_at',
        'follow_up_date',
        'follow_up_time',
        'patient_id',
        'created_by',
        'last_modified_by',
        // Missed Calls full list (2026-07-05) — soft ignore / bulk dismiss audit
        'ignored_at',
        'ignored_by',
        'dismissed_at',
        'dismissed_by',
        // Phase 1 — attempt tracking + SLA + outcome
        'source_engine',
        'opportunity_value',
        'attempt_count',
        'last_attempt_at',
        'sla_deadline',
        'sla_breached',
        'outcome',
        'outcome_reason',
        'response_notes',
        // Phase 4 — B2B contact fields
        'contact_type',
        'contact_id',
        'b2b_subtype',
        'lab_case_id',
    ];

    protected $casts = [
        'tags'            => 'array',
        'is_overdue'      => 'boolean',
        'sla_breached'    => 'boolean',
        'due_at'          => 'datetime',
        'follow_up_date'  => 'date',
        'last_attempt_at' => 'datetime',
        'sla_deadline'    => 'datetime',
        'opportunity_value' => 'decimal:2',
        'ignored_at'      => 'datetime',
        'dismissed_at'    => 'datetime',
    ];

    // ── Lookup Tables ────────────────────────────────────────────────────

    public const CHANNELS = [
        'call'      => 'Call',
        'whatsapp'  => 'WhatsApp',
        'walk_in'   => 'Walk-in',
        'referral'  => 'Referral',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'website'   => 'Website',
        'email'     => 'Email',
        'other'     => 'Other',
    ];

    public const COMM_TYPES = [
        'new_lead'          => 'New Lead',
        'existing_patient'  => 'Existing Patient',
        'ongoing_treatment' => 'Ongoing Treatment',
        'vendor'            => 'Vendor',
        'lab'               => 'Lab',
        'doctor'            => 'Doctor',
        'staff'             => 'Staff',
        'other'             => 'Other',
        'spam'              => 'Spam',
    ];

    public const PURPOSES = [
        'appointment'       => 'Appointment',
        'treatment_inquiry' => 'Treatment Inquiry',
        'price_inquiry'     => 'Price Inquiry',
        'emergency'         => 'Emergency',
        'recall'            => 'Recall',
        'complaint'         => 'Complaint',
        'payment'           => 'Payment',
        'general_query'     => 'General Query',
        'other'             => 'Other',
        // "Not Interested" outcome closes the current recall but keeps the
        // patient in a long-horizon preventive cycle instead of the normal
        // 6-month no-visit cadence (OutcomeAutomationService, 2026-07-05).
        'recall_long_term'  => 'Long-term Recall',
    ];

    public const NEXT_ACTIONS = [
        'call_back'        => 'Call Back',
        'whatsapp'         => 'WhatsApp',
        'book_appointment' => 'Book Appointment',
        'send_estimate'    => 'Send Estimate',
        'send_location'    => 'Send Location',
        'wait'             => 'Wait',
        'close'            => 'Close',
    ];

    public const STATUSES = [
        'pending'             => 'Pending',
        'waiting_for_patient' => 'Waiting for Patient',
        'overdue'             => 'Overdue',
        'closed'              => 'Closed',
    ];

    public const PRIORITIES = [
        'high'   => 'High',
        'medium' => 'Medium',
        'low'    => 'Low',
    ];

    // Phase 4 — B2B constants ────────────────────────────────────────────────

    /**
     * Who this communication is with.
     * 'patient' = classic patient comm (patient_id used)
     * Others    = B2B (contact_id + contact_type used)
     */
    public const CONTACT_TYPES = [
        'patient'    => 'Patient',
        'lab'        => 'Lab',
        'vendor'     => 'Vendor / Supplier',
        'consultant' => 'Consultant / Referral Doctor',
    ];

    /**
     * B2B subtypes — what kind of B2B comm this is.
     */
    public const B2B_SUBTYPES = [
        'lab_case_status'     => 'Lab Case Status Update',
        'vendor_followup'     => 'Vendor Follow-up',
        'vendor_order'        => 'Order / Delivery Query',
        'consultant_referral' => 'Consultant Referral Note',
        'consultant_feedback' => 'Referral Feedback',
        'maintenance'         => 'Equipment Maintenance',
        'service'             => 'Service / AMC',
        'other'               => 'Other',
    ];

    /**
     * B2B-specific outcomes (supplement the standard OUTCOMES above).
     */
    public const B2B_OUTCOMES = [
        'case_received'      => '✓ Lab Case Received',
        'order_confirmed'    => '✓ Order Confirmed',
        'invoice_sent'       => '✓ Invoice / Quote Sent',
        'referral_confirmed' => '✓ Referral Confirmed',
        'resolved'           => '✓ Issue Resolved',
        'escalated'          => '⬆ Escalated',
        'no_response'        => '✗ No Response',
        'cancelled'          => '✗ Cancelled',
    ];

    // Phase 1 additions ──────────────────────────────────────────────────────

    public const SOURCE_ENGINES = [
        'manual'      => 'Manual',
        'inbound'     => 'Inbound Lead',
        'recall'      => 'Recall Engine',
        'opportunity' => 'Opportunity Engine',
        'b2b'         => 'B2B / External',
    ];

    /**
     * Outcomes — mandatory when closing a communication.
     * Staff MUST pick one before the record can be marked closed.
     */
    public const OUTCOMES = [
        'appointment_booked' => '✓ Appointment Booked',
        'treatment_started'  => '✓ Treatment Started',
        'follow_up_set'      => '↩ Follow-up Set',
        'not_interested'     => '✗ Not Interested',
        'unreachable'        => '✗ Unreachable (3+ attempts)',
        'lost'               => '✗ Lost — gave up',
        'escalated'          => '⬆ Escalated to Manager',
        'spam'               => '🚫 Spam / Wrong Number',
    ];

    /**
     * Recall Call Outcomes — PRE mobile Activity Completion Bottom Sheet
     * (2026-07-05). This is a richer, grouped vocabulary specifically for
     * the recall call-logging workflow (web + mobile). It EXTENDS rather
     * than replaces OUTCOMES above: `outcome` is a plain string column
     * (see 2026_06_13_200001 migration), so both vocabularies coexist
     * safely and nothing that reads the old OUTCOMES list breaks.
     * See OutcomeAutomationService for the trigger -> action mapping.
     */
    public const CALL_OUTCOMES_CONNECTED = [
        'appointment_booked'         => 'Appointment Booked',
        'will_call_back'             => 'Will Call Back',
        'wants_appt_next_week'       => 'Wants Appointment Next Week',
        'wants_appt_next_month'      => 'Wants Appointment Next Month',
        'will_visit_later'           => 'Will Visit Later',
        'under_treatment_elsewhere'  => 'Under Treatment Elsewhere',
        'treatment_done_elsewhere'   => 'Treatment Done Elsewhere',
        'financial_constraint'       => 'Financial Constraint',
        'family_will_decide'         => 'Family Will Decide',
        'busy_right_now'             => 'Busy Right Now',
        'not_interested'             => 'Not Interested',
        'shifted'                    => 'Shifted',
        'deceased'                   => 'Deceased',
        'other'                      => 'Other',
    ];

    public const CALL_OUTCOMES_NOT_CONNECTED = [
        'no_answer'       => 'No Answer',
        'busy'            => 'Busy',
        'switched_off'    => 'Switched Off',
        'out_of_coverage' => 'Out of Coverage',
        'rejected'        => 'Rejected',
        'wrong_number'    => 'Wrong Number',
        'invalid_number'  => 'Invalid Number',
    ];

    public const CALL_OUTCOMES_COMMUNICATION = [
        'whatsapp_sent' => 'WhatsApp Sent',
        'sms_sent'      => 'SMS Sent',
        'email_sent'    => 'Email Sent',
    ];

    /** All recall call outcomes, grouped for the mobile picker UI. */
    public static function callOutcomeGroups(): array
    {
        return [
            'Connected'     => self::CALL_OUTCOMES_CONNECTED,
            'Not Connected' => self::CALL_OUTCOMES_NOT_CONNECTED,
            'Communication' => self::CALL_OUTCOMES_COMMUNICATION,
        ];
    }

    /** Flat lookup (key => label) across all three call-outcome groups. */
    public static function allCallOutcomes(): array
    {
        return self::CALL_OUTCOMES_CONNECTED
            + self::CALL_OUTCOMES_NOT_CONNECTED
            + self::CALL_OUTCOMES_COMMUNICATION;
    }

    /**
     * SLA minutes per scenario.
     * inbound: 30 min (per architecture — high-value leads must be called fast)
     * high priority: 60 min
     * default: 24 hours
     */
    public const SLA_MINUTES = [
        'inbound' => 30,
        'high'    => 60,
        'default' => 1440,
    ];

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where(function ($q) {
            $q->where('is_overdue', true)->orWhere('status', 'overdue');
        });
    }

    public function scopeToday($query)
    {
        return $query->where(function ($q) {
            $q->whereDate('created_at', today())
              ->orWhereDate('follow_up_date', today())
              ->orWhereDate('due_at', today());
        });
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByChannel($query, ?string $channel)
    {
        return $channel ? $query->where('channel', $channel) : $query;
    }

    public function scopeByCommType($query, ?string $type)
    {
        return $type ? $query->where('comm_type', $type) : $query;
    }

    public function scopeByStatus($query, ?string $status)
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeByPriority($query, ?string $priority)
    {
        return $priority ? $query->where('priority', $priority) : $query;
    }

    public function scopeByOwner($query, ?string $owner)
    {
        return $owner ? $query->where('assigned_to', $owner) : $query;
    }

    /**
     * Excludes items a staff member has chosen to "Ignore" (soft, reversible
     * hide — see ignored_at/ignored_by). Applied by default everywhere the
     * missed-calls queue is read (dashboard preview + full list) unless the
     * caller explicitly asks to include ignored items.
     */
    public function scopeNotIgnored($query)
    {
        return $query->whereNull('ignored_at');
    }

    public function scopeOnlyIgnored($query)
    {
        return $query->whereNotNull('ignored_at');
    }

    // ── Computed Accessors ───────────────────────────────────────────────

    public function getChannelLabelAttribute(): string
    {
        return self::CHANNELS[$this->channel] ?? ucfirst(str_replace('_', ' ', $this->channel ?? ''));
    }

    public function getCommTypeLabelAttribute(): string
    {
        return self::COMM_TYPES[$this->comm_type] ?? ucfirst(str_replace('_', ' ', $this->comm_type ?? ''));
    }

    public function getPurposeLabelAttribute(): string
    {
        return self::PURPOSES[$this->purpose] ?? ucfirst(str_replace('_', ' ', $this->purpose ?? '—'));
    }

    public function getNextActionLabelAttribute(): string
    {
        return self::NEXT_ACTIONS[$this->next_action] ?? ucfirst(str_replace('_', ' ', $this->next_action ?? '—'));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status ?? '');
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'pending'             => 'cl-badge--warning',
            'waiting_for_patient' => 'cl-badge--info',
            'overdue'             => 'cl-badge--danger',
            'closed'              => 'cl-badge--success',
            default               => 'cl-badge--secondary',
        };
    }

    public function getPriorityBadgeClassAttribute(): string
    {
        return match ($this->priority) {
            'high'   => 'cl-badge--danger',
            'medium' => 'cl-badge--warning',
            'low'    => 'cl-badge--secondary',
            default  => 'cl-badge--secondary',
        };
    }

    public function getChannelIconAttribute(): string
    {
        return match ($this->channel) {
            'call'      => '📞',
            'whatsapp'  => '💬',
            'walk_in'   => '🚶',
            'referral'  => '👥',
            'instagram' => '📸',
            'facebook'  => '📘',
            'website'   => '🌐',
            'email'     => '✉️',
            default     => '📋',
        };
    }

    // ── Phase 1 Helpers ──────────────────────────────────────────────────

    /**
     * Record one contact attempt (call, WhatsApp, visit, etc.)
     * Increments attempt_count, stamps last_attempt_at, updates response_notes,
     * checks SLA breach, then writes an activity log entry.
     *
     * Usage: $comm->logAttempt('Tried calling, no answer');
     */
    public function logAttempt(string $notes = ''): void
    {
        $this->attempt_count  = ($this->attempt_count ?? 0) + 1;
        $this->last_attempt_at = now();

        if ($notes) {
            $this->response_notes = $notes;
        }

        // Move pending → waiting_for_patient on first attempt
        if ($this->status === 'pending') {
            $this->status = 'waiting_for_patient';
        }

        $this->checkSla(); // may flip sla_breached
        $this->save();

        CommActivityLog::log(
            $this->id,
            'attempt',
            "Attempt #{$this->attempt_count}" . ($notes ? ": {$notes}" : ''),
            ['attempt_count' => $this->attempt_count, 'notes' => $notes]
        );
    }

    /**
     * Set sla_deadline based on source_engine + priority.
     * Call this once on record creation.
     */
    public function setSlaDeadline(): void
    {
        $minutes = match(true) {
            $this->source_engine === 'inbound' => self::SLA_MINUTES['inbound'],  // 30 min
            $this->priority === 'high'         => self::SLA_MINUTES['high'],     // 60 min
            default                            => self::SLA_MINUTES['default'],  // 24 h
        };

        $this->sla_deadline = now()->addMinutes($minutes);
    }

    /**
     * Check if SLA is breached; sets sla_breached = true if overdue.
     * Called on every attempt and on SLA check command (Phase 5).
     */
    public function checkSla(): void
    {
        if ($this->sla_deadline
            && now()->gt($this->sla_deadline)
            && $this->status !== 'closed'
            && !$this->sla_breached
        ) {
            $this->sla_breached = true;
        }
    }

    /**
     * Human-readable SLA status for display.
     * Returns: 'OK — X min left' | 'BREACHED — X ago' | 'No SLA'
     */
    public function getSlaStatusAttribute(): string
    {
        if (!$this->sla_deadline) {
            return 'No SLA';
        }

        if ($this->status === 'closed') {
            return 'Closed';
        }

        if ($this->sla_breached || now()->gt($this->sla_deadline)) {
            return 'Breached — ' . $this->sla_deadline->diffForHumans(now(), true) . ' ago';
        }

        return 'OK — ' . now()->diffForHumans($this->sla_deadline, true) . ' left';
    }

    /**
     * Outcome label for display.
     */
    public function getOutcomeLabelAttribute(): string
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst(str_replace('_', ' ', $this->outcome ?? '—'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Recompute is_overdue flag based on follow_up_date or due_at.
     */
    public function recalculateOverdue(): void
    {
        $checkDate = $this->follow_up_date ?? $this->due_at;

        if ($checkDate && Carbon::parse($checkDate)->isPast() && $this->status !== 'closed') {
            $this->is_overdue    = true;
            $this->overdue_since = Carbon::parse($checkDate)->diffForHumans(now(), true);
            if ($this->status === 'pending') {
                $this->status = 'overdue';
            }
        } else {
            $this->is_overdue    = false;
            $this->overdue_since = null;
        }
    }

    // ── Phase 4 Scopes ───────────────────────────────────────────────────

    /** All B2B communications (lab + vendor + consultant) */
    public function scopeB2b($query)
    {
        return $query->where('contact_type', '!=', 'patient')
                     ->orWhere('source_engine', 'b2b');
    }

    /** Filter by contact_type (lab|vendor|consultant) */
    public function scopeByContactType($query, ?string $type)
    {
        return $type ? $query->where('contact_type', $type) : $query;
    }

    /** Filter by b2b_subtype */
    public function scopeByB2bSubtype($query, ?string $subtype)
    {
        return $subtype ? $query->where('b2b_subtype', $subtype) : $query;
    }

    // ── Phase 4 Helper ───────────────────────────────────────────────────

    /**
     * Auto-close this comm with a given outcome (used by LabCaseObserver).
     * Only closes if not already closed.
     */
    public function autoClose(string $outcome, string $reason = ''): void
    {
        if ($this->status === 'closed') {
            return;
        }

        $this->status         = 'closed';
        $this->outcome        = $outcome;
        $this->outcome_reason = $reason ?: 'Auto-closed by system';
        $this->save();

        CommActivityLog::log(
            $this->id,
            'auto_closed',
            "Auto-closed: {$reason}",
            ['outcome' => $outcome]
        );
    }

    // ── Missed Calls: Ignore / Dismiss (2026-07-05) ─────────────────────────

    /**
     * Soft, reversible exclude — this specific queue item never reappears in
     * the missed-calls queue (dashboard preview or full list) until unignored.
     * Does NOT touch `status`: the underlying call is still "pending" if it
     * was pending; ignoring is a display-layer decision, not a lifecycle one.
     */
    public function ignore(?int $userId = null): void
    {
        $this->ignored_at = now();
        $this->ignored_by = $userId ?? auth()->id();
        $this->save();

        CommActivityLog::log($this->id, 'ignored', 'Excluded from missed-calls queue');
    }

    public function unignore(): void
    {
        $this->ignored_at = null;
        $this->ignored_by = null;
        $this->save();

        CommActivityLog::log($this->id, 'unignored', 'Restored to missed-calls queue');
    }

    /**
     * Bulk Dismiss — marks the item handled (status=closed, matching the
     * existing "closed" lifecycle state) and stamps who/when for audit,
     * distinct from a normal close so it's traceable back to this action.
     */
    public function dismiss(?int $userId = null, string $reason = 'Bulk dismissed from missed-calls queue'): void
    {
        $actorId = $userId ?? auth()->id();

        $this->status        = 'closed';
        $this->is_overdue     = false;
        $this->dismissed_at  = now();
        $this->dismissed_by  = $actorId;
        $this->last_modified_by = $actorId;
        $this->save();

        CommActivityLog::log($this->id, 'closed', $reason);
    }

    public function ignoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by');
    }

    public function dismissedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dismissed_by');
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /** Lab vendor (when contact_type = 'lab') */
    public function labVendor(): BelongsTo
    {
        return $this->belongsTo(LabVendor::class, 'contact_id');
    }

    /** Finance vendor (when contact_type = 'vendor') */
    public function financeVendor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Finance\FinanceVendor::class, 'contact_id');
    }

    /** Linked lab case (when b2b_subtype = 'lab_case_status') */
    public function labCase(): BelongsTo
    {
        return $this->belongsTo(LabCase::class, 'lab_case_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(CommActivityLog::class, 'comm_id')->orderByDesc('logged_at');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastModifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }
}
