<?php

namespace App\Console\Commands;

use App\Mail\MorningBriefing;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * SendMorningBriefing — Phase 5 Communication OS
 *
 * Sends a personalised morning briefing to every active staff member at 7am.
 * Only sends to users who have the communication module enabled.
 *
 * Usage:
 *   php artisan comm:morning-briefing
 *   php artisan comm:morning-briefing --dry-run
 */
class SendMorningBriefing extends Command
{
    protected $signature   = 'comm:morning-briefing {--dry-run : Show who would receive without sending}';
    protected $description = 'Send 7am morning briefing emails to all active staff';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->line('');
        $this->line('  <fg=cyan;options=bold>📋 Morning Briefing</> — ' . now()->format('D d M Y, H:i'));
        $this->line('');

        // Get all staff with email addresses
        // Adjust the filter if you have a role/permission system
        $staff = User::whereNotNull('email')
            ->where('is_active', true)
            ->get();

        if ($staff->isEmpty()) {
            $this->warn('  No active staff with email addresses found.');
            return self::SUCCESS;
        }

        $sent  = 0;
        $skipped = 0;

        foreach ($staff as $user) {
            if (!$user->email) {
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY-RUN] Would send to: {$user->name} <{$user->email}>");
                continue;
            }

            try {
                Mail::to($user->email)->send(new MorningBriefing($user));
                $this->line("  ✓ Sent to {$user->name} ({$user->email})");
                $sent++;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed for {$user->name}: " . $e->getMessage());
            }
        }

        $this->line('');
        if (!$isDryRun) {
            $this->info("  Done. Sent: {$sent} · Skipped: {$skipped}");
        }

        return self::SUCCESS;
    }
}
