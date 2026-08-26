<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActionOptionList;
use Database\Seeders\ActionOptionListSeeder;
use Illuminate\Console\Command;

/**
 * ADDITIVE-ONLY top-up of the call-outcome vocabulary.
 *
 * Why this exists (2026-08-26, Sumit):
 * The redesigned Today's Actions drawer asks reception one question first —
 * did the call connect? — and only then what the patient said. Those four
 * buttons (Answered / No Answer / Unable to Connect / Wrong Number) are
 * presentation buckets over the EXISTING ActionOptionList rows; a button is
 * only offered when the category actually has an option behind it, because
 * submitting an outcome with no configured row means no closes_task rule and
 * therefore no defined task status. Several categories were missing a
 * "could not connect" or "wrong number" option entirely.
 *
 * ActionOptionListSeeder now carries those rows, but the seeder uses
 * updateOrCreate and MUST NOT be re-run on a live database — it would
 * overwrite every label and sort order a clinic has edited in
 * Settings > Call Outcomes. This command reads the same definitions and
 * inserts ONLY rows whose (option_type, action_category, key) does not exist
 * yet. Existing rows are never read back, never updated, never reordered,
 * never deactivated.
 *
 * Idempotent — a second run reports "nothing to add".
 *
 * Usage:
 *   php artisan action-options:ensure-call-results            (dry run)
 *   php artisan action-options:ensure-call-results --apply     (insert)
 */
class EnsureCallResultOptions extends Command
{
    protected $signature = 'action-options:ensure-call-results {--apply : Actually insert the missing rows (otherwise dry-run)}';

    protected $description = "Insert any call-outcome options missing from a live database, without touching existing rows.";

    public function handle(): int
    {
        $apply   = (bool) $this->option('apply');
        $missing = [];

        foreach (ActionOptionListSeeder::callOutcomes() as $category => $options) {
            foreach ($options as $i => $opt) {
                $exists = ActionOptionList::query()
                    ->where('option_type', 'call_outcome')
                    ->where('action_category', $category)
                    ->where('key', $opt['key'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $missing[] = [$category, $opt, $i];
            }
        }

        if (! $missing) {
            $this->info('Nothing to add — every call-outcome option already exists.');

            return self::SUCCESS;
        }

        $this->table(
            ['Category', 'Key', 'Label', 'Closes task', 'Requires note'],
            array_map(fn (array $row) => [
                $row[0],
                $row[1]['key'],
                $row[1]['label'],
                ($row[1]['closes_task'] ?? true) ? 'yes' : 'no',
                ($row[1]['requires_notes'] ?? false) ? 'yes' : 'no',
            ], $missing)
        );

        if (! $apply) {
            $this->warn(count($missing) . ' option(s) would be added. Re-run with --apply to insert them.');

            return self::SUCCESS;
        }

        foreach ($missing as [$category, $opt, $i]) {
            ActionOptionList::create([
                'option_type'     => 'call_outcome',
                'action_category' => $category,
                'key'             => $opt['key'],
                'label'           => $opt['label'],
                'requires_notes'  => $opt['requires_notes'] ?? false,
                'closes_task'     => $opt['closes_task'] ?? true,
                'next_action_key' => $opt['next_action_key'] ?? null,
                'sort_order'      => $i,
                'is_active'       => true,
            ]);
        }

        $this->info('✅ Added ' . count($missing) . ' call-outcome option(s). No existing row was modified.');

        return self::SUCCESS;
    }
}
