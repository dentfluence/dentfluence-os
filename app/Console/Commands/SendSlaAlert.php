<?php

namespace App\Console\Commands;

use App\Mail\SlaAlert;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * SendSlaAlert — Phase 5 Communication OS
 *
 * Sends a 2PM SLA breach alert to the clinic manager.
 * Identifies managers by role. Falls back to sending to all admin users
 * if no explicit manager role is configured.
 *
 * Usage:
 *   php artisan comm:sla-alert
 *   php artisan comm:sla-alert --email=manager@clinic.com
 */
class SendSlaAlert extends Command
{
    protected $signature   = 'comm:sla-alert {--email= : Override recipient email for testing}';
    protected $description = 'Send 2PM SLA breach alert to clinic manager';

    public function handle(): int
    {
        $this->line('');
        $this->line('  <fg=red;options=bold>⚠️  SLA Alert</> — ' . now()->format('D d M Y, H:i'));
        $this->line('');

        // Recipient: use --email override or find managers
        $overrideEmail = $this->option('email');

        if ($overrideEmail) {
            $recipients = collect([['email' => $overrideEmail, 'name' => 'Manager']]);
        } else {
            // Adjust this query to match your roles/permissions setup
            // Here we send to users with role 'admin' or 'manager'
            $recipients = User::whereNotNull('email')
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('role', 'admin')
                      ->orWhere('role', 'manager');
                })
                ->get(['email', 'name']);

            // Fallback: if no managers found, skip
            if ($recipients->isEmpty()) {
                $this->warn('  No manager/admin users found. Set COMM_MANAGER_EMAIL in .env or fix role assignments.');
                return self::SUCCESS;
            }
        }

        $mailable = new SlaAlert();

        // Show preview in terminal
        $this->line("  Breaches: <fg=red>{$mailable->totalBreached}</>");
        $this->line("  High-value leads at risk: <fg=red>" . count($mailable->highValueLeads) . "</>");
        $this->line('');

        foreach ($recipients as $manager) {
            try {
                Mail::to($manager['email'])->send($mailable);
                $this->line("  ✓ Sent to {$manager['name']} ({$manager['email']})");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: " . $e->getMessage());
            }
        }

        $this->line('');
        return self::SUCCESS;
    }
}
