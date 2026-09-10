<?php

namespace App\Services\Relationship;

use App\Models\ActionOptionList;

/**
 * TodayActionOptions — the ONE definition of how a Today's Action can be
 * closed: the outcome list, which outcomes need a note, which ones actually
 * complete the action, and how the four contact-result buttons are grouped.
 *
 * Written 9 Sep 2026 (M-17). Before this, TodayController and the mobile
 * RelationshipController each carried byte-identical private copies of
 * buildResponseOptions() / buildNextActions() / buildRequiresNotesMap(), and
 * the two that mattered most — closesTaskMap() and callResults() — existed
 * ONLY on the web. That is exactly why the phone showed a flat list of five
 * outcomes where the web showed "did the call connect?" first: not a design
 * choice, just a builder nobody had copied across yet.
 *
 * Settings > Call Outcomes (ActionOptionList) stays the single source of
 * truth for labels, requires_notes and closes_task. Nothing here invents an
 * option; this only decides how existing ones are presented.
 */
class TodayActionOptions
{
    /**
     * Outcome keys that mean the call did NOT reach the patient. A key not
     * named here is a CONNECTED outcome by definition, so it lands in
     * "answered" and becomes a patient-response choice.
     */
    public const CONTACT_RESULT_KEYS = [
        'no_answer'         => ['no_answer', 'voicemail', 'not_reachable'],
        'unable_to_connect' => ['busy', 'switched_off', 'out_of_coverage', 'rejected'],
        'wrong_number'      => ['wrong_number', 'invalid_number'],
    ];

    /** Display order + labels for the four result buttons. */
    public const CONTACT_RESULTS = [
        'answered'          => 'Answered',
        'no_answer'         => 'No Answer',
        'unable_to_connect' => 'Unable to Connect',
        'wrong_number'      => 'Wrong Number',
    ];

    /** category => [outcome key => label] */
    public function responseOptions(): array
    {
        $merged = config('relationship_rules.response_options', []);

        $dbRows = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->active()
            ->get()
            ->groupBy('action_category');

        foreach ($dbRows as $category => $rows) {
            $merged[$category] = ActionOptionList::labelMap($rows);
        }

        return $merged;
    }

    /** outcome key => suggested next-action label. */
    public function nextActions(): array
    {
        $overrides = ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->whereNotNull('next_action_key')
            ->active()
            ->pluck('next_action_key', 'key')
            ->toArray();

        return array_merge(config('relationship_rules.next_actions', []), $overrides);
    }

    /** category => [key => true] for outcomes that require a note before submit. */
    public function requiresNotesMap(): array
    {
        $map = [];

        ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->where('requires_notes', true)
            ->active()
            ->get(['action_category', 'key'])
            ->each(function ($row) use (&$map) {
                $map[$row->action_category][$row->key] = true;
            });

        return $map;
    }

    /**
     * category => [key => bool] — whether logging this outcome completes the
     * action. Read straight off the column logAction() enforces, so a client
     * can tell staff what will happen BEFORE they save: "Marks this action
     * complete" vs "Attempt recorded — stays due for another try."
     * Presentation only; the server remains the authority.
     */
    public function closesTaskMap(): array
    {
        $map = [];

        ActionOptionList::query()
            ->where('option_type', 'call_outcome')
            ->active()
            ->get(['action_category', 'key', 'closes_task'])
            ->each(function ($row) use (&$map) {
                $map[$row->action_category][$row->key] = (bool) $row->closes_task;
            });

        return $map;
    }

    /**
     * category => result bucket => [outcome key => label].
     *
     * Reception records ONE thing first: did the call connect? Only then does
     * "what did the patient say?" make sense. A bucket with exactly one key
     * needs no second step — picking the button IS the outcome. A bucket with
     * none is not offered at all for that category, because inventing an
     * option the clinic has switched off would submit an outcome with no
     * closes_task rule behind it.
     *
     * @param  array|null  $responseOpts  pass one in to avoid a second query
     */
    public function callResults(?array $responseOpts = null): array
    {
        $responseOpts ??= $this->responseOptions();
        $out = [];

        foreach ($responseOpts as $category => $options) {
            $buckets = ['answered' => [], 'no_answer' => [], 'unable_to_connect' => [], 'wrong_number' => []];

            foreach ($options as $key => $label) {
                $bucket = 'answered';

                foreach (self::CONTACT_RESULT_KEYS as $name => $keys) {
                    if (in_array($key, $keys, true)) {
                        $bucket = $name;
                        break;
                    }
                }

                $buckets[$bucket][$key] = $label;
            }

            $out[$category] = $buckets;
        }

        return $out;
    }

    /** Dismiss reasons ("Not needed"), from the same Settings table. */
    public function dismissReasons()
    {
        return ActionOptionList::query()->dismissReasons()->get()->values();
    }
}
