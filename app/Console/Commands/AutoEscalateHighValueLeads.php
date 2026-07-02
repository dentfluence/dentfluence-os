<?php

namespace App\Console\Commands;

use App\Models\CommunicationQueue;
use App\Models\CommActivityLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * AutoEscalateHighValueLeads — Phase 5 Communication OS
 *
 * Checks every 30 minutes (or on demand) for:
 *  1. Inbound communications with opportunity_value ≥ ₹30,000 that haven't
 *     been contacted (attempt_count = 0) within 2 hours of creation.
 *  2. Leads with lead_value ≥ ₹30,000 in stage new_lead/contacted that
 *     haven't been updated in 2 hours.
 *
 * On match:
 *  - Sets outcome = 'escalated' on the comm (if it's a CommunicationQueue item)
 *  - Sends an instant email alert to managers
 *  - Logs the escalation in comm_activity_logs
 *
 * Usage:
 *   php artisan comm:auto-escalate
 *   php artisan comm:auto-escalate --dry-run
 */
class AutoEscalateHighValueLeads extends Command
{
    protected $signature   = 'comm:auto-escalate {--dry-run : Preview escalations without sending}';
    protected $description = 'Auto-escalate ₹30k+ leads not contacted within 2 hours to manager';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $threshold = 30000;  // ₹30,000
        $hours     = 2;       // hours before escalation

        $this->line('');
        $this->line('  <fg=yellow;options=bold>⬆️  Auto-Escalate</> — ' . now()->format('D d M Y, H:i'));
        $this->line('');

        // ── 1. Communication queue: high-value, 0 attempts, created 2h+ ago ──
        $escalatableComms = CommunicationQueue::where('opportunity_value', '>=', $threshold)
            ->where('attempt_count', 0)
            ->where('status', '!=', 'closed')
            ->where('outcome', '!=', 'escalated')
            ->where('created_at', '<=', now()->subHours($hours))
            ->get();

        // ── 2. Leads: high-value, early stages, no update in 2h ──────────────
        $escalatableLeads = Lead::where('lead_value', '>=', $threshold)
            ->whereIn('stage', ['new_lead', 'contacted'])
            ->where(function ($q) use ($hours) {
                $q->whereNull('updated_at')
                  ->orWhere('updated_at', '<=', now()->subHours($hours));
            })
            ->get();

        $totalComms = $escalatableComms->count();
        $totalLeads = $escalatableLeads->count();
        $total      = $totalComms + $totalLeads;

        if ($total === 0) {
            $this->info('  ✓ No escalations needed right now.');
            $this->line('');
            return self::SUCCESS;
        }

        $this->line("  Found {$totalComms} comm(s) + {$totalLeads} lead(s) needing escalation.");
        $this->line('');

        if ($isDryRun) {
            foreach ($escalatableComms as $comm) {
                $this->line("  [DRY] Comm #{$comm->id}: {$comm->person_name} — ₹" . number_format($comm->opportunity_value));
            }
            foreach ($escalatableLeads as $lead) {
                $this->line("  [DRY] Lead #{$lead->id}: {$lead->name} — ₹" . number_format($lead->lead_value));
            }
            $this->line('');
            return self::SUCCESS;
        }

        // ── Mark comms as escalated ───────────────────────────────────────────
        foreach ($escalatableComms as $comm) {
            $comm->update([
                'outcome'        => 'escalated',
                'outcome_reason' => "Auto-escalated: ₹30k+ lead, no contact in {$hours}h",
                'sla_breached'   => true,
            ]);

            CommActivityLog::log(
                $comm->id,
                'auto_escalated',
                "Auto-escalated to manager — ₹" . number_format($comm->opportunity_value) . " lead, no contact in {$hours}h",
                ['rule' => "opportunity_value >= {$threshold} AND attempt_count = 0 AND age > {$hours}h"]
            );

            $this->line("  ⬆ Escalated Comm #{$comm->id}: {$comm->person_name}");
        }

        // ── Send escalation alert email to managers ───────────────────────────
        if ($totalComms > 0 || $totalLeads > 0) {
            $this->sendEscalationAlert($escalatableComms, $escalatableLeads, $threshold, $hours);
        }

        $this->line('');
        $this->info("  Done. {$total} item(s) escalated.");
        return self::SUCCESS;
    }

    /**
     * Send a simple plain-text escalation alert email to managers.
     */
    private function sendEscalationAlert($comms, $leads, int $threshold, int $hours): void
    {
        $managers = User::whereNotNull('email')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('role', 'admin')->orWhere('role', 'manager');
            })
            ->get(['email', 'name']);

        if ($managers->isEmpty()) {
            $this->warn('  No manager email found. Set manager role on a user.');
            return;
        }

        $subject  = '🔴 ESCALATION: ' . ($comms->count() + $leads->count()) . ' high-value lead(s) not contacted in ' . $hours . 'h';
        $body     = $this->buildEscalationBody($comms, $leads, $threshold, $hours);

        foreach ($managers as $manager) {
            try {
                Mail::raw($body, function ($message) use ($manager, $subject) {
                    $message->to($manager->email, $manager->name)
                            ->subject($subject);
                });
                $this->line("  ✉ Alert sent to {$manager->name}");
            } catch (\Exception $e) {
                $this->error("  ✗ Mail failed for {$manager->name}: " . $e->getMessage());
            }
        }
    }

    private function buildEscalationBody($comms, $leads, int $threshold, int $hours): string
    {
        $lines = [];
        $lines[] = "ESCALATION ALERT — " . now()->format('D d M Y, H:i');
        $lines[] = str_repeat('-', 50);
        $lines[] = "The following ₹" . number_format($threshold) . "+ leads have not been contacted in {$hours} hours.";
        $lines[] = "ACTION REQUIRED IMMEDIATELY.";
        $lines[] = '';

        if ($comms->count() > 0) {
            $lines[] = "COMMUNICATION QUEUE:";
            foreach ($comms as $comm) {
                $lines[] = "  • #{$comm->id} | {$comm->person_name} | {$comm->phone} | ₹" . number_format($comm->opportunity_value) . " | Assigned: " . ($comm->assigned_to ?: 'UNASSIGNED');
            }
            $lines[] = '';
        }

        if ($leads->count() > 0) {
            $lines[] = "LEADS:";
            foreach ($leads as $lead) {
                $lines[] = "  • #{$lead->id} | {$lead->name} | {$lead->phone} | ₹" . number_format($lead->lead_value) . " | Stage: {$lead->stage} | Assigned: " . ($lead->assigned_to ?: 'UNASSIGNED');
            }
            $lines[] = '';
        }

        $lines[] = str_repeat('-', 50);
        $lines[] = "Dentfluence · Auto-Escalation System";

        return implode("\n", $lines);
    }
}
