<?php

namespace Database\Seeders;

use App\Models\NotificationRule;
use App\Services\Notifications\NotificationCatalog;
use Illuminate\Database\Seeder;

/**
 * NotificationRuleSeeder — copies the catalogue's shipped defaults into
 * notification_rules so the Settings matrix has a row to edit for every
 * (event, role) the catalogue names.
 *
 * Idempotent and NON-destructive: a row the admin has already changed is
 * left exactly as it is; only missing rows are added. Safe to re-run after
 * every catalogue change.
 *
 *   php artisan db:seed --class=NotificationRuleSeeder
 */
class NotificationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $added = 0;

        foreach (NotificationCatalog::defaultRules() as $rule) {
            $created = NotificationRule::firstOrCreate(
                ['event_key' => $rule['event_key'], 'role' => $rule['role'], 'branch_id' => null],
                ['level' => $rule['level'], 'push' => $rule['push']]
            )->wasRecentlyCreated;

            if ($created) {
                $added++;
            }
        }

        $this->command?->info("notification_rules: {$added} default rule(s) added, existing rows untouched.");
    }
}
