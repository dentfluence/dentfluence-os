<?php

namespace App\Mail;

use App\Models\CommunicationQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

/**
 * SlaAlert — Phase 5 Communication OS
 *
 * Sent at 2pm to the clinic manager.
 * Lists all current SLA breaches + high-value leads overdue for contact.
 *
 * Design: plain + scannable. Manager needs to act, not read.
 */
class SlaAlert extends Mailable
{
    use Queueable, SerializesModels;

    public array  $breachedComms;
    public array  $highValueLeads;
    public int    $totalBreached;
    public string $date;

    public function __construct()
    {
        // All open SLA breaches
        $this->breachedComms = CommunicationQueue::where('sla_breached', true)
            ->where('status', '!=', 'closed')
            ->orderByRaw("FIELD(priority,'high','medium','low')")
            ->orderBy('sla_deadline')
            ->limit(30)
            ->get([
                'id', 'person_name', 'phone', 'assigned_to',
                'priority', 'sla_deadline', 'attempt_count',
                'source_engine', 'opportunity_value'
            ])
            ->toArray();

        // High-value leads (₹30k+) not contacted in 2+ hours
        $this->highValueLeads = \App\Models\Lead::where('lead_value', '>=', 30000)
            ->whereIn('stage', ['new_lead', 'contacted'])
            ->where(function ($q) {
                $q->whereNull('updated_at')
                  ->orWhere('updated_at', '<', now()->subHours(2));
            })
            ->orderByDesc('lead_value')
            ->limit(15)
            ->get(['id', 'name', 'phone', 'lead_value', 'assigned_to', 'lead_source', 'updated_at'])
            ->toArray();

        $this->totalBreached = count($this->breachedComms);
        $this->date          = Carbon::now()->format('l, d F Y — g:i A');
    }

    public function envelope(): Envelope
    {
        $urgent = count($this->highValueLeads);
        return new Envelope(
            subject: "⚠️ {$this->totalBreached} SLA breach" . ($this->totalBreached !== 1 ? 'es' : '') .
                ($urgent > 0 ? " + {$urgent} high-value lead" . ($urgent !== 1 ? 's' : '') . " at risk" : '') .
                ' — 2PM Alert',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digest.sla-alert',
        );
    }
}
