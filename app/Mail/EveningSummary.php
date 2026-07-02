<?php

namespace App\Mail;

use App\Models\User;
use App\Models\CommunicationQueue;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * EveningSummary — Phase 5 Communication OS
 *
 * Sent at 6pm to each staff member.
 * Shows: what was done today, what's still open, leads won/lost.
 *
 * Also has a manager variant (isManager=true) with team-level summary.
 */
class EveningSummary extends Mailable
{
    use Queueable, SerializesModels;

    public string $staffName;
    public bool   $isManager;
    public int    $doneTodayCount;     // comms closed today
    public int    $openCount;          // still open (assigned to this staff)
    public int    $overdueCount;       // overdue for this staff
    public int    $wonToday;           // leads converted today
    public int    $attemptsMade;       // attempts logged today
    public array  $openItems;          // sample of open items
    public string $date;

    // Manager-level fields
    public array  $teamSummary;        // per-staff breakdown (manager only)
    public int    $teamWon;
    public int    $teamBreaches;

    public function __construct(User $staff, bool $isManager = false)
    {
        $this->staffName = $staff->name;
        $this->isManager = $isManager;
        $today           = Carbon::today();

        // Comms closed today by this staff
        $this->doneTodayCount = CommunicationQueue::where('assigned_to', $staff->name)
            ->where('status', 'closed')
            ->whereDate('updated_at', $today)
            ->count();

        // Still open
        $this->openCount = CommunicationQueue::where('assigned_to', $staff->name)
            ->where('status', '!=', 'closed')
            ->count();

        // Overdue
        $this->overdueCount = CommunicationQueue::where('assigned_to', $staff->name)
            ->where('status', '!=', 'closed')
            ->where('is_overdue', true)
            ->count();

        // Attempts today
        $this->attemptsMade = CommunicationQueue::where('assigned_to', $staff->name)
            ->whereDate('last_attempt_at', $today)
            ->count();

        // Leads won today
        $this->wonToday = Lead::where('stage', 'converted')
            ->where('assigned_to', $staff->name)
            ->whereDate('updated_at', $today)
            ->count();

        // Sample of open items (top 8)
        $this->openItems = CommunicationQueue::where('assigned_to', $staff->name)
            ->where('status', '!=', 'closed')
            ->orderByRaw("FIELD(status,'overdue','pending','waiting_for_patient')")
            ->limit(8)
            ->get(['person_name', 'status', 'follow_up_date', 'priority'])
            ->toArray();

        $this->date = $today->format('l, d F Y');

        // Manager-level data
        if ($isManager) {
            $this->teamSummary = CommunicationQueue::where('status', '!=', 'closed')
                ->whereNotNull('assigned_to')
                ->selectRaw('assigned_to, COUNT(*) as open, SUM(is_overdue) as overdue, SUM(sla_breached) as breached')
                ->groupBy('assigned_to')
                ->get()
                ->toArray();

            $this->teamWon = Lead::where('stage', 'converted')
                ->whereDate('updated_at', $today)
                ->count();

            $this->teamBreaches = CommunicationQueue::where('sla_breached', true)
                ->where('status', '!=', 'closed')
                ->count();
        } else {
            $this->teamSummary = [];
            $this->teamWon     = 0;
            $this->teamBreaches = 0;
        }
    }

    public function envelope(): Envelope
    {
        $emoji = $this->doneTodayCount >= 5 ? '✅' : ($this->overdueCount > 0 ? '⚠️' : '📊');
        return new Envelope(
            subject: "{$emoji} Today: {$this->doneTodayCount} done · {$this->openCount} open · {$this->wonToday} won — " . Carbon::today()->format('d M'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digest.evening-summary',
        );
    }
}
