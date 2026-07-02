<?php

namespace App\Console\Commands;

use App\Models\HrAttendance;
use App\Models\User;
use Illuminate\Console\Command;

class HrMarkAbsent extends Command
{
    protected $signature   = 'hr:mark-absent {--date= : Date to process (Y-m-d), defaults to today} {--dry-run : Preview without saving}';
    protected $description = 'Auto-mark absent any active staff who have not checked in by cutoff time.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::parse($this->option('date'))
            : now();

        $isDryRun = $this->option('dry-run');

        $this->info("HR Auto-Absent for {$date->format('Y-m-d')}" . ($isDryRun ? ' [DRY RUN]' : ''));

        // Get all active staff with HR profiles
        $allStaff = User::whereHas('hrProfile')
            ->where('is_active', true)
            ->pluck('id');

        // Staff who already have an attendance record for this date
        $alreadyMarked = HrAttendance::whereDate('date', $date)
            ->whereIn('user_id', $allStaff)
            ->pluck('user_id');

        // Staff with no record yet = not checked in
        $toMark = $allStaff->diff($alreadyMarked);

        if ($toMark->isEmpty()) {
            $this->info('All staff already have attendance records. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Marking {$toMark->count()} staff as absent...");

        if (! $isDryRun) {
            foreach ($toMark as $userId) {
                HrAttendance::create([
                    'user_id'         => $userId,
                    'date'            => $date->toDateString(),
                    'status'          => 'absent',
                    'check_in_method' => 'manual',
                    'marked_by'       => null, // system-generated
                    'notes'           => 'Auto-marked absent by system',
                ]);
            }
            $this->info("Done. {$toMark->count()} records created.");
        } else {
            $names = User::whereIn('id', $toMark)->pluck('name');
            $this->table(['Staff'], $names->map(fn($n) => [$n])->toArray());
        }

        return self::SUCCESS;
    }
}
