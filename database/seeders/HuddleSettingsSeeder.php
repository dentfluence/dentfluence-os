<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Huddle\Models\HuddleSetting;

class HuddleSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = 1;

        $defaults = [
            // ── Global (applies to all roles) ────────────────────────────────
            [
                'branch_id'   => $branchId,
                'role'        => null,
                'key'         => 'carry_forward_overdue_tasks',
                'value'       => true,
                'label'       => 'Carry Forward Overdue Tasks',
                'description' => 'Automatically carry overdue tasks to the next day\'s huddle board.',
            ],
            [
                'branch_id'   => $branchId,
                'role'        => null,
                'key'         => 'auto_lock_board_hour',
                'value'       => 20, // 8 PM
                'label'       => 'Auto-lock Board Hour',
                'description' => 'Hour (24h) after which the board is locked for edits.',
            ],

            // ── Doctor / Admin board columns ─────────────────────────────────
            [
                'branch_id'   => $branchId,
                'role'        => 'doctor',
                'key'         => 'visible_columns',
                'value'       => [
                    'today_flow',
                    'yesterday_flow',
                    'critical_alerts',
                    'tasks',
                    'lab',
                    'inventory',
                    'marketing',
                    'maintenance',
                    'quick_actions',
                ],
                'label'       => 'Visible Columns',
                'description' => 'Kanban columns shown on the Doctor Huddle board.',
            ],

            // ── Front Desk board columns ──────────────────────────────────────
            [
                'branch_id'   => $branchId,
                'role'        => 'front_desk',
                'key'         => 'visible_columns',
                'value'       => [
                    'today_flow',
                    'comms',
                    'tasks',
                    'yesterday_flow',
                    'quick_actions',
                ],
                'label'       => 'Visible Columns',
                'description' => 'Kanban columns shown on the Front Desk Huddle board.',
            ],

            // ── Assistant board columns ───────────────────────────────────────
            [
                'branch_id'   => $branchId,
                'role'        => 'assistant',
                'key'         => 'visible_columns',
                'value'       => [
                    'today_flow',
                    'assist_assignments',
                    'tasks',
                    'comments',
                ],
                'label'       => 'Visible Columns',
                'description' => 'Kanban columns shown on the Assistant Huddle board.',
            ],

            // ── Task proof requirements ───────────────────────────────────────
            [
                'branch_id'   => $branchId,
                'role'        => null,
                'key'         => 'task_proof_required_types',
                'value'       => ['sterilization'],
                'label'       => 'Task Types Requiring Proof',
                'description' => 'Task types that must have a photo uploaded before marking done.',
            ],

            // ── Escalation thresholds ─────────────────────────────────────────
            [
                'branch_id'   => $branchId,
                'role'        => null,
                'key'         => 'escalate_overdue_after_days',
                'value'       => 2,
                'label'       => 'Escalate After Days Overdue',
                'description' => 'Escalate a task to admin if overdue for this many days.',
            ],
        ];

        foreach ($defaults as $setting) {
            HuddleSetting::updateOrCreate(
                [
                    'branch_id' => $setting['branch_id'],
                    'role'      => $setting['role'],
                    'key'       => $setting['key'],
                ],
                [
                    'value'       => $setting['value'],
                    'label'       => $setting['label'],
                    'description' => $setting['description'],
                ]
            );
        }

        $this->command->info('✅ Huddle default settings seeded (' . count($defaults) . ' settings).');
    }
}
