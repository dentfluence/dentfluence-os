<?php

namespace Database\Seeders;

use App\Models\RetentionPolicy;
use Illuminate\Database\Seeder;

/**
 * RetentionPolicySeeder (DPDP 5.4)
 * --------------------------------
 * Sensible default retention windows. All set to action = "report" so nothing
 * is ever auto-deleted until you change the action and enable purging.
 *
 * Run: php artisan db:seed --class=RetentionPolicySeeder
 * Idempotent (matched on data_type).
 */
class RetentionPolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['data_type' => 'audit_logs',        'name' => 'Audit logs',          'retain_days' => 1095, 'description' => 'Keep system audit logs for 3 years.'],
            ['data_type' => 'consent_logs',      'name' => 'Consent history',      'retain_days' => 2920, 'description' => 'Keep consent trail for 8 years (proof of lawful basis).'],
            ['data_type' => 'data_requests',     'name' => 'Resolved rights requests', 'retain_days' => 1825, 'description' => 'Keep completed/rejected DSARs for 5 years.'],
            ['data_type' => 'breaches',          'name' => 'Closed breaches',      'retain_days' => 1825, 'description' => 'Keep closed breach records for 5 years.'],
            ['data_type' => 'inactive_patients', 'name' => 'Inactive patients',    'retain_days' => 3650, 'description' => 'Flag patient records untouched for 10 years (review before any action).'],
        ];

        foreach ($policies as $p) {
            RetentionPolicy::updateOrCreate(
                ['data_type' => $p['data_type']],
                array_merge($p, ['action' => 'report', 'active' => true])
            );
        }
    }
}
