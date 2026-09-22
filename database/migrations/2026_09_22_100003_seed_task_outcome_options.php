<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task Manager V2 — T-1: the outcome vocabulary becomes clinic-editable.
 *
 * WHY THIS EXISTS: the first cut of the drawer flattened every PRE
 * call_outcome row into one list, so reception was shown 40+ options including
 * "Renewed on call" and "Booked pickup appointment" on a lab task. Asked to
 * pick from 40, a person under time pressure picks anything — you collect
 * outcome data and all of it is wrong. Worse, they learn that habit and it is
 * hard to unlearn later.
 *
 * WHY action_option_lists AND NOT A NEW TABLE: this table already carries
 * exactly the right columns — key, label, closes_task, requires_notes,
 * sort_order, is_active — and already has a Settings editing pattern. A second
 * table would be a second thing to keep in sync for no gain.
 *
 * WHY option_type = 'task_outcome' AND NOT 'call_outcome': Settings for the
 * Tasks module must not live inside the PRE settings page. "PRE engine che
 * task vegle, task manager che vegle" (CEO, 6 Sep) is a rule about the whole
 * module, not just the board. Sharing the option_type would also drop task
 * categories into the PRE board's own category list.
 *
 * SEEDED PER CATEGORY, not once: a clinic must be able to edit the lab list
 * without touching the call list. Six per category, not forty.
 *
 * closes_task = false means the task STAYS OPEN when that outcome is chosen.
 * For communication tasks the non-contact rule in TodayActionOptions still
 * overrides this in code, exactly as it does on the PRE side — a clinic can
 * edit labels freely, but "No answer closes the task" is not theirs to get
 * wrong (see migration 2026_09_12_000001).
 *
 * Idempotent: existing rows are left alone, so re-running never overwrites a
 * clinic's edits.
 */
return new class extends Migration
{
    /** [key, label, closes_task, requires_notes] */
    private const COMM_SET = [
        ['spoke_done',          'Spoke — handled',            true,  false],
        ['spoke_will_callback', 'Spoke — will call back',     false, true],
        ['no_answer',           'No answer',                  false, false],
        ['switched_off',        'Switched off / busy',        false, false],
        ['wrong_number',        'Wrong number',               true,  true],
        ['not_interested',      'Not interested',             true,  true],
    ];

    private const WORK_SET = [
        ['completed',        'Completed',                        true,  false],
        ['partially_done',   'Partially done',                   false, true],
        ['blocked_material', 'Blocked — material not available', false, true],
        ['blocked_vendor',   'Blocked — waiting on vendor',      false, true],
        ['blocked_patient',  'Blocked — waiting on patient',     false, true],
        ['not_required',     'Not required any more',            true,  true],
    ];

    private const LAB_SET = [
        ['completed',       'Completed',                    true,  false],
        ['sent_to_lab',     'Sent to lab',                  false, false],
        ['awaiting_lab',    'Waiting on lab',               false, true],
        ['trial_pending',   'Trial pending with patient',   false, true],
        ['remake_needed',   'Remake needed',                false, true],
        ['not_required',    'Not required any more',        true,  true],
    ];

    private const MAINTENANCE_SET = [
        ['completed',      'Done — service completed',       true,  false],
        ['vendor_visited', 'Vendor visited — part pending',  false, true],
        ['awaiting_vendor','Waiting on vendor visit',        false, true],
        ['quote_awaited',  'Quotation awaited',              false, true],
        ['deferred',       'Deferred — not urgent',          false, true],
        ['not_required',   'Not required any more',          true,  true],
    ];

    public function up(): void
    {
        $sets = [
            'call'        => self::COMM_SET,
            'whatsapp'    => self::COMM_SET,
            'follow_up'   => self::COMM_SET,
            'lab'         => self::LAB_SET,
            'maintenance' => self::MAINTENANCE_SET,
            'clinical'    => self::WORK_SET,
            'admin'       => self::WORK_SET,
            'other'       => self::WORK_SET,
        ];

        $now = now();

        foreach ($sets as $category => $rows) {
            foreach ($rows as $i => [$key, $label, $closes, $needsNote]) {
                $exists = DB::table('action_option_lists')
                    ->where('option_type', 'task_outcome')
                    ->where('action_category', $category)
                    ->where('key', $key)
                    ->exists();

                if ($exists) {
                    continue; // never overwrite a clinic's edit
                }

                DB::table('action_option_lists')->insert([
                    'option_type'     => 'task_outcome',
                    'action_category' => $category,
                    'key'             => $key,
                    'label'           => $label,
                    'closes_task'     => $closes,
                    'requires_notes'  => $needsNote,
                    'sort_order'      => $i,
                    'is_active'       => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Only the seeded rows go. Anything a clinic added themselves stays —
        // their data is not this migration's to remove.
        $seeded = array_unique(array_merge(
            array_column(self::COMM_SET, 0),
            array_column(self::WORK_SET, 0),
            array_column(self::LAB_SET, 0),
            array_column(self::MAINTENANCE_SET, 0),
        ));

        DB::table('action_option_lists')
            ->where('option_type', 'task_outcome')
            ->whereIn('key', $seeded)
            ->delete();
    }
};
