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
 * MorningBriefing — Phase 5 Communication OS
 *
 * Sent at 7am to each staff member.
 * Shows: their queue for today, overdue items, high-value leads needing action.
 *
 * Design: plain + scannable. No marketing, no fluff.
 */
class MorningBriefing extends Mailable
{
    use Queueable, SerializesModels;

    public string $staffName;
    public array  $todayQueue;       // comms due today
    public array  $overdueItems;     // past-due comms
    public int    $highValueCount;   // leads ≥ ₹30k needing action
    public int    $totalToday;       // total items for the day
    public string $date;

    public function __construct(User $staff)
    {
        $this->staffName = $staff->name;
        $today           = Carbon::today();

        // Today's queue for this staff member
        $todayComms = CommunicationQueue::where('assigned_to', $staff->name)
            ->where('status', '!=', 'closed')
            ->where(function ($q) use ($today) {
                $q->whereDate('follow_up_date', $today)
                  ->orWhereDate('due_at', $today)
                  ->orWhereDate('created_at', $today);
            })
            ->orderByRaw("FIELD(status,'overdue','pending','waiting_for_patient')")
            ->orderBy('priority', 'asc')   // high first (alphabetically before low)
            ->limit(15)
            ->get(['id', 'person_name', 'phone', 'purpose', 'status', 'priority', 'sla_breached', 'contact_type'])
            ->toArray();

        // Overdue (past follow_up_date, not closed)
        $overdueComms = CommunicationQueue::where('assigned_to', $staff->name)
            ->where('status', '!=', 'closed')
            ->where('is_overdue', true)
            ->limit(10)
            ->get(['id', 'person_name', 'follow_up_date', 'attempt_count'])
            ->toArray();

        // High-value leads needing action
        $this->highValueCount = Lead::where('lead_value', '>=', 30000)
            ->whereIn('stage', ['new_lead', 'contacted'])
            ->where('assigned_to', $staff->name)
            ->count();

        $this->todayQueue    = $todayComms;
        $this->overdueItems  = $overdueComms;
        $this->totalToday    = count($todayComms);
        $this->date          = $today->format('l, d F Y');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "📋 Your {$this->totalToday} calls today — " . Carbon::today()->format('d M'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digest.morning-briefing',
        );
    }
}
