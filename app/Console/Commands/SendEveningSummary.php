<?php

namespace App\Console\Commands;

use App\Mail\EveningSummary;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * SendEveningSummary — Phase 5 Communication OS
 *
 * Sends a 6PM evening summary to every active staff member.
 * Managers additionally receive a team-level breakdown.
 *
 * Usage:
 *   php artisan comm:evening-summary
 *   php artisan comm:evening-summary --dry-run
 */
class SendEveningSummary extends Command
{
    protected $signature   = 'comm:evening-summary {--dry-run : Show who would receive without sending}';
    protected $description = 'Send 6PM evening summary emails to all active staff';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->line('');
        $this->line('  <fg=cyan;options=bold>📊 Evening Summary</> — ' . now()->format('D d M Y, H:i'));
        $this->line('');

        $staff = User::whereNotNull('email')
            ->where('is_active', true)
            ->get();

        if ($staff->isEmpty()) {
            $this->warn('  No active staff with email addresses found.');
            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($staff as $user) {
            if (!$user->email) {
                continue;
            }

            // Determine if this user is a manager
            $isManager = in_array($user->role ?? '', ['admin', 'manager']);

            if ($isDryRun) {
                $type = $isManager ? '(manager)' : '(staff)';
                $this->line("  [DRY-RUN] Would send to: {$user->name} <{$user->email}> {$type}");
                continue;
            }

            try {
                Mail::to($user->email)->send(new EveningSummary($user, $isManager));
                $this->line("  ✓ Sent to {$user->name}");
                $sent++;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed for {$user->name}: " . $e->getMessage());
            }
        }

        $this->line('');
        if (!$isDryRun) {
            $this->info("  Done. Sent to {$sent} staff members.");
        }

        return self::SUCCESS;
    }
}
