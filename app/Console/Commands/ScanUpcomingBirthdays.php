<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Services\Relationship\ActivityEngine;
use Illuminate\Console\Command;

/**
 * ScanUpcomingBirthdays — production hardening 2026-07-14.
 *
 * The 'birthday_3d' RulesEngine rule has been enabled since it was written,
 * but NOTHING in the app ever emitted 'birthday.approaching' — so it has never
 * fired and no birthday greeting task has ever been auto-created.
 *
 * Mirrors RunMembershipRenewalScan (membership:scan-expiring): a thin command
 * over a plain query, no new engine. Fires once per patient per year, on the
 * day their birthday enters the lead-time window. RulesEngine's 360-day
 * cooldown on birthday_3d then prevents any repeat within the same year.
 *
 * NOTE: this only EMITS the event — it sends nothing. The rule creates a
 * staff-facing "Birthday greeting" task; a human decides whether to send.
 *
 * Usage:
 *   php artisan birthdays:scan
 *   php artisan birthdays:scan --dry-run
 *   php artisan birthdays:scan --days=7
 */
class ScanUpcomingBirthdays extends Command
{
    protected $signature = 'birthdays:scan
        {--days= : Days of lead time before the birthday (default: config, 3)}
        {--dry-run : Preview only, no Activity logged}';

    protected $description = 'Fire birthday.approaching for patients whose birthday is N days away';

    public function handle(): int
    {
        $days     = (int) ($this->option('days') ?: config('relationship_rules.today_actions.birthday_days_ahead', 3));
        $isDryRun = (bool) $this->option('dry-run');
        $target   = now()->addDays($days);

        // Match on month+day so it works for every year of birth.
        $patients = Patient::query()
            ->whereNotNull('date_of_birth')
            ->whereMonth('date_of_birth', $target->month)
            ->whereDay('date_of_birth', $target->day)
            ->get();

        $this->line("Birthday scan — {$patients->count()} patient(s) with a birthday on {$target->format('d M')} ({$days} days ahead).");

        if ($isDryRun) {
            foreach ($patients as $p) {
                $this->line("  • #{$p->id} {$p->name} — {$p->date_of_birth?->format('d M Y')}");
            }

            return self::SUCCESS;
        }

        $logged = 0;

        foreach ($patients as $patient) {
            app(ActivityEngine::class)->log(
                subject:        $patient,
                event:          'birthday.approaching',
                actor:          null,
                metadata:       [
                    'patient_id'    => $patient->id,
                    'birthday'      => $patient->date_of_birth?->format('m-d'),
                    'days_ahead'    => $days,
                    'turning'       => $patient->date_of_birth ? $target->year - $patient->date_of_birth->year : null,
                ],
                relationshipId: $patient->relationship_id,
                description:    'Birthday in ' . $days . ' days',
            );

            $logged++;
        }

        $this->line("  \xE2\x9C\x93 {$logged} logged.");

        return self::SUCCESS;
    }
}
