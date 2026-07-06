<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActionOptionList;
use Illuminate\Database\Seeder;

/**
 * Seeds call-outcome + dismiss-reason options.
 *
 * Call outcomes carry forward the existing config('relationship_rules')
 * defaults (birthday / payment_reminders keep their already-customised
 * sets) and add category-appropriate sets for the categories that were
 * silently sharing the generic fallback — see
 * docs/feature-specs/feature-spec-custom-call-outcomes.md, section 4.
 *
 * Re-runnable: uses updateOrCreate keyed on (option_type, action_category, key),
 * matching the unique index on the table, so running this seeder again after
 * a clinic has edited labels in Settings will NOT clobber their edits to
 * existing rows' `label`/`sort_order` — wait, it will, since updateOrCreate
 * overwrites the matched row. That's fine for the initial seed (idempotent
 * for reruns during development) but this seeder should NOT be re-run after
 * go-live once clinics start editing their own labels in Settings — new
 * categories/keys can be added safely by extending the arrays below and
 * re-running, since existing (option_type, action_category, key) combos
 * simply get re-saved with the same values unless this file itself changes.
 */
class ActionOptionListSeeder extends Seeder
{
    public function run(): void
    {
        $count = 0;

        foreach ($this->callOutcomes() as $category => $options) {
            foreach ($options as $i => $opt) {
                ActionOptionList::updateOrCreate(
                    [
                        'option_type'     => 'call_outcome',
                        'action_category' => $category,
                        'key'             => $opt['key'],
                    ],
                    [
                        'label'           => $opt['label'],
                        'requires_notes'  => $opt['requires_notes'] ?? false,
                        'closes_task'     => $opt['closes_task'] ?? true,
                        'next_action_key' => $opt['next_action_key'] ?? null,
                        'sort_order'      => $i,
                        'is_active'       => true,
                    ]
                );
                $count++;
            }
        }

        foreach ($this->dismissReasons() as $i => $opt) {
            ActionOptionList::updateOrCreate(
                [
                    'option_type'     => 'dismiss_reason',
                    'action_category' => null,
                    'key'             => $opt['key'],
                ],
                [
                    'label'          => $opt['label'],
                    'requires_notes' => $opt['requires_notes'] ?? false,
                    'closes_task'    => true,
                    'sort_order'     => $i,
                    'is_active'      => true,
                ]
            );
            $count++;
        }

        $this->command->info("✅ Action option lists seeded ({$count} rows).");
    }

    /**
     * @return array<string, array<int, array{key:string,label:string,requires_notes?:bool,closes_task?:bool,next_action_key?:string}>>
     */
    private function callOutcomes(): array
    {
        return [
            // Kept from config('relationship_rules.response_options.default') —
            // still the right fit for new_enquiries / lead_followups.
            'default' => [
                ['key' => 'connected_booked',         'label' => 'Connected — Appointment booked'],
                ['key' => 'connected_callback',        'label' => 'Connected — Will call back later'],
                ['key' => 'connected_not_interested',  'label' => 'Connected — Not interested'],
                ['key' => 'no_answer',                 'label' => 'No answer'],
                ['key' => 'busy',                       'label' => 'Line busy'],
                ['key' => 'wrong_number',               'label' => 'Wrong number'],
                ['key' => 'voicemail',                  'label' => 'Left voicemail'],
            ],

            // Kept exactly as-is from config — already category-specific.
            'birthday' => [
                ['key' => 'wished_happy',   'label' => 'Wished — patient was happy'],
                ['key' => 'wished_booked',  'label' => 'Wished — also booked appointment'],
                ['key' => 'no_answer',      'label' => 'No answer'],
                ['key' => 'not_reachable',  'label' => 'Not reachable'],
            ],
            'payment_reminders' => [
                ['key' => 'payment_promised', 'label' => 'Payment promised (date noted)', 'requires_notes' => true],
                ['key' => 'payment_made',      'label' => 'Payment made on call'],
                ['key' => 'dispute_raised',    'label' => 'Patient raised a dispute', 'requires_notes' => true],
                ['key' => 'no_answer',         'label' => 'No answer'],
            ],

            // New category-specific sets — see spec section 4 for the reasoning.
            'appointment_reminders' => [
                ['key' => 'confirmed_attendance', 'label' => 'Confirmed attendance'],
                ['key' => 'asked_reschedule',       'label' => 'Asked to reschedule'],
                ['key' => 'no_answer',              'label' => 'No answer'],
                ['key' => 'wrong_number',           'label' => 'Wrong number'],
            ],
            'follow_up_calls' => [
                ['key' => 'doing_well',   'label' => 'Patient doing well'],
                ['key' => 'has_concern',  'label' => 'Has a concern — noted', 'requires_notes' => true],
                ['key' => 'no_answer',    'label' => 'No answer'],
                ['key' => 'voicemail',    'label' => 'Left voicemail'],
            ],
            'recall_calls' => [
                ['key' => 'booked_recall',        'label' => 'Booked recall appointment'],
                ['key' => 'connected_callback',   'label' => 'Will call back'],
                ['key' => 'not_interested_now',   'label' => 'Not interested right now'],
                ['key' => 'no_answer',            'label' => 'No answer'],
                ['key' => 'wrong_number',         'label' => 'Wrong number'],
            ],
            'opportunities' => [
                ['key' => 'still_deciding',    'label' => 'Still deciding'],
                ['key' => 'booked_consultation','label' => 'Booked consultation'],
                ['key' => 'declined',           'label' => 'Declined', 'requires_notes' => true],
                ['key' => 'no_answer',          'label' => 'No answer'],
            ],
            'pending_estimates' => [
                ['key' => 'still_deciding',      'label' => 'Still deciding'],
                ['key' => 'ready_to_proceed',    'label' => 'Ready to proceed — booked'],
                ['key' => 'declined',            'label' => 'Declined', 'requires_notes' => true],
                ['key' => 'no_answer',           'label' => 'No answer'],
            ],
            'membership_renewals' => [
                ['key' => 'renewed_on_call',   'label' => 'Renewed on call'],
                ['key' => 'will_decide_by',    'label' => 'Will decide by [date]', 'requires_notes' => true],
                ['key' => 'not_renewing',      'label' => 'Not renewing', 'requires_notes' => true],
                ['key' => 'no_answer',         'label' => 'No answer'],
            ],
            'lab_ready' => [
                ['key' => 'booked_pickup',   'label' => 'Booked pickup appointment'],
                ['key' => 'will_collect_later', 'label' => 'Will collect later'],
                ['key' => 'no_answer',       'label' => 'No answer'],
            ],
        ];
    }

    /**
     * @return array<int, array{key:string,label:string,requires_notes?:bool}>
     */
    private function dismissReasons(): array
    {
        return [
            ['key' => 'already_handled',   'label' => 'Already handled elsewhere / not needed'],
            ['key' => 'duplicate',          'label' => 'Duplicate entry'],
            ['key' => 'unreachable_contact','label' => 'Patient no longer reachable / wrong contact on file'],
            ['key' => 'staff_error',        'label' => 'Added in error'],
            ['key' => 'other',              'label' => 'Other', 'requires_notes' => true],
        ];
    }
}
