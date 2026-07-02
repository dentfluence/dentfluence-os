<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Modules\PracticeProtocols\Models\PracticeProtocol;
use App\Modules\PracticeProtocols\Models\PracticeProtocolMaterial;
use Illuminate\Database\Seeder;

/**
 * Starter set of practice protocols.
 *
 * All content here is original and generic — common front-of-house / surgery
 * routines any clinic runs. Edit or delete freely; this is just a head start.
 *
 * Idempotent: keyed on (title + role_id), so re-running won't duplicate.
 */
class PracticeProtocolSeeder extends Seeder
{
    public function run(): void
    {
        // Pick a sensible "created_by" — first admin, else any user.
        $creatorId = User::where('role', 'admin')->value('id')
                     ?? User::query()->value('id');

        // Resolve role ids by slug once.
        $roleId = fn (string $slug) => Role::where('slug', $slug)->value('id');

        // branch_id = null → applies to all branches.
        $protocols = [
            [
                'title'             => 'Open surgery and switch on equipment',
                'description'       => 'Unlock, turn on compressor, suction, lights and chairs; confirm everything is ready before first patient.',
                'role_slug'         => Role::ASSISTANT,
                'category'          => 'clinical',
                'frequency'         => 'daily',
                'default_due_time'  => '08:30',
                'priority'          => 'high',
                'requires_evidence' => false,
                'steps'             => [
                    'Unlock the surgery and switch on the lights',
                    'Turn on compressor and suction motor',
                    'Power up dental chairs and units',
                    'Confirm water and air flow at each unit',
                ],
            ],
            [
                'title'             => 'Run autoclave test cycle and log result',
                'description'       => 'Run the daily steriliser test cycle and record the outcome.',
                'role_slug'         => Role::ASSISTANT,
                'category'          => 'decon',
                'frequency'         => 'daily',
                'default_due_time'  => '08:45',
                'priority'          => 'urgent',
                'requires_evidence' => true,
                'steps'             => [
                    'Load and start the test cycle',
                    'Wait for the cycle to complete',
                    'Check the indicator result',
                    'Record pass/fail and attach a photo of the printout',
                ],
            ],
            [
                'title'             => 'Check and reply to overnight enquiries',
                'description'       => 'Review missed calls, messages and web enquiries from overnight and respond.',
                'role_slug'         => Role::FRONT_DESK,
                'category'          => 'reception',
                'frequency'         => 'daily',
                'default_due_time'  => '09:00',
                'priority'          => 'medium',
                'requires_evidence' => false,
                'steps'             => [],
            ],
            [
                'title'             => 'End-of-day cash and card reconciliation',
                'description'       => 'Reconcile the day\'s takings against the system and record any discrepancy.',
                'role_slug'         => Role::ACCOUNTS,
                'category'          => 'admin',
                'frequency'         => 'daily',
                'default_due_time'  => '18:00',
                'priority'          => 'high',
                'requires_evidence' => true,
                'steps'             => [
                    'Total cash in the till and count the float',
                    'Compare card machine totals to the system',
                    'Note and explain any difference',
                    'Attach a photo of the reconciliation sheet',
                ],
            ],
            [
                'title'             => 'Weekly stock level check and reorder',
                'description'       => 'Walk the stock cupboards, note low items and place reorders.',
                'role_slug'         => Role::MANAGER,
                'category'          => 'maintenance',
                'frequency'         => 'weekly',
                'weekday'           => 1, // Monday
                'default_due_time'  => '10:00',
                'priority'          => 'medium',
                'requires_evidence' => false,
                'steps'             => [],
            ],
            [
                'title'             => 'Monthly fire-safety walk-around and log',
                'description'       => 'Check extinguishers, exits and alarms; record the check.',
                'role_slug'         => Role::MANAGER,
                'category'          => 'maintenance',
                'frequency'         => 'monthly',
                'day_of_month'      => 1,
                'default_due_time'  => '09:30',
                'priority'          => 'high',
                'requires_evidence' => true,
                'steps'             => [
                    'Check each fire extinguisher is present and in date',
                    'Confirm fire exits are clear and unlocked',
                    'Test the alarm panel shows no faults',
                    'Record the check and attach a photo',
                ],
            ],
        ];

        foreach ($protocols as $i => $p) {
            $roleId_ = $roleId($p['role_slug']);
            if (! $roleId_) {
                continue; // role not seeded yet — skip safely
            }

            $protocol = PracticeProtocol::updateOrCreate(
                ['title' => $p['title'], 'role_id' => $roleId_],
                [
                    'description'       => $p['description'],
                    'branch_id'         => null,
                    'category'          => $p['category'],
                    'frequency'         => $p['frequency'],
                    'weekday'           => $p['weekday'] ?? null,
                    'day_of_month'      => $p['day_of_month'] ?? null,
                    'default_due_time'  => $p['default_due_time'] ?? null,
                    'priority'          => $p['priority'],
                    'requires_evidence' => $p['requires_evidence'],
                    'is_active'         => true,
                    'sort_order'        => $i,
                    'created_by'        => $creatorId,
                ]
            );

            // Attach an SOP checklist if this protocol has steps.
            if (! empty($p['steps'])) {
                PracticeProtocolMaterial::updateOrCreate(
                    ['practice_protocol_id' => $protocol->id, 'type' => 'sop_steps'],
                    [
                        'title'      => 'Standard steps',
                        'body'       => $p['steps'],
                        'sort_order' => 0,
                    ]
                );
            }
        }

        $this->command->info('✅ Practice Protocols starter set seeded.');
    }
}
