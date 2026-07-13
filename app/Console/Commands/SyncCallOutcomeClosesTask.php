<?php

namespace App\Console\Commands;

use App\Models\ActionOptionList;
use Illuminate\Console\Command;

/**
 * One-time data patch: sets action_option_lists.closes_task to the correct
 * per-outcome value on rows that already exist in the database.
 *
 * Every call-outcome row shipped with closes_task defaulting to true and
 * nothing ever varied it — Today's Actions Log never auto-closed the row
 * (see TodayController::logAction()), so the column sat unused. 2026-07-10
 * it went live: a resolved outcome (booked, confirmed, declined...) now
 * auto-closes the row, a "needs retry" outcome (no answer, still deciding...)
 * leaves it open. ActionOptionListSeeder::callOutcomes() has the correct
 * values for a FRESH install, but that seeder must not be re-run once a
 * clinic has customised labels/order in Settings (updateOrCreate would
 * overwrite their edits) — this command only ever touches the closes_task
 * column, on rows matched by (action_category, key), so it's safe to run
 * on a live, already-customised database.
 *
 * Idempotent — re-running just re-applies the same map.
 *
 * Usage:
 *   php artisan action-options:sync-closes-task            (dry run)
 *   php artisan action-options:sync-closes-task --apply     (write changes)
 */
class SyncCallOutcomeClosesTask extends Command
{
    protected $signature = 'action-options:sync-closes-task {--apply : Actually write the changes (otherwise dry-run)}';

    protected $description = 'Set the correct closes_task value per call outcome so resolved calls auto-close on the Action Board.';

    /**
     * category => [key => closes_task]. Mirrors ActionOptionListSeeder's
     * callOutcomes() classification — keep the two in sync if outcomes change.
     */
    private const MAP = [
        'default' => [
            'connected_booked'        => true,
            'connected_callback'      => true,
            'connected_not_interested'=> true,
            'no_answer'               => false,
            'busy'                    => false,
            'wrong_number'            => true,
            'voicemail'               => false,
        ],
        'payment_reminders' => [
            'payment_promised' => true,
            'payment_made'     => true,
            'dispute_raised'   => true,
            'no_answer'        => false,
        ],
        'appointment_reminders' => [
            'confirmed_attendance' => true,
            'asked_reschedule'     => true,
            'no_answer'            => false,
            'wrong_number'         => true,
        ],
        'follow_up_calls' => [
            'doing_well'  => true,
            'has_concern' => true,
            'no_answer'   => false,
            'voicemail'   => false,
        ],
        'recall_calls' => [
            'booked_recall'      => true,
            'connected_callback' => true,
            'not_interested_now' => true,
            'no_answer'          => false,
            'wrong_number'       => true,
        ],
        'opportunities' => [
            'still_deciding'     => false,
            'booked_consultation'=> true,
            'declined'           => true,
            'no_answer'          => false,
        ],
        'pending_estimates' => [
            'still_deciding'   => false,
            'ready_to_proceed' => true,
            'declined'         => true,
            'no_answer'        => false,
        ],
        'membership_renewals' => [
            'renewed_on_call' => true,
            'will_decide_by'  => false,
            'not_renewing'    => true,
            'no_answer'       => false,
        ],
        'lab_ready' => [
            'booked_pickup'     => true,
            'will_collect_later'=> false,
            'no_answer'         => false,
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $changed = 0;
        $unchanged = 0;
        $missing = 0;

        foreach (self::MAP as $category => $outcomes) {
            foreach ($outcomes as $key => $closesTask) {
                $row = ActionOptionList::query()
                    ->where('option_type', 'call_outcome')
                    ->where('action_category', $category)
                    ->where('key', $key)
                    ->first();

                if (! $row) {
                    $this->line("• {$category}.{$key}: not found in DB — skipped.");
                    $missing++;
                    continue;
                }

                if ((bool) $row->closes_task === $closesTask) {
                    $unchanged++;
                    continue;
                }

                $this->line("• {$category}.{$key}: closes_task " . ($row->closes_task ? 'true' : 'false')
                    . ' → ' . ($closesTask ? 'true' : 'false') . ($apply ? ' — WRITING' : ' — would write'));

                if ($apply) {
                    $row->update(['closes_task' => $closesTask]);
                }

                $changed++;
            }
        }

        $this->newLine();
        if ($apply) {
            $this->info("Done. Updated {$changed} row(s), {$unchanged} already correct, {$missing} not found.");
        } else {
            $this->warn("Dry run: {$changed} row(s) would change, {$unchanged} already correct, {$missing} not found.");
            $this->warn('Re-run with --apply to actually write the changes.');
        }

        return self::SUCCESS;
    }
}
