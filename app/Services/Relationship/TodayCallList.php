<?php

namespace App\Services\Relationship;

use Illuminate\Support\Carbon;

/**
 * TodayCallList — ONE PATIENT, ONE ROW, ONE CALL (W-10 finish, 2026-09-11).
 *
 * TodayActionsEngine generates work by REASON (a category per reason). Reception
 * works by PATIENT: Sumit Firke has an appointment today, lab work ready and an
 * estimate to chase — that is one phone call, not three rows in three lists
 * with three separate closes. This service folds the engine's category groups
 * into patient rows for the web board:
 *
 *   row      = one patient (or lead; an item with neither is its own row)
 *   primary  = the patient's highest-ranked reason — the row sits where the
 *              clinic's existing order puts that reason (today's confirmations
 *              first, then yesterday's follow-ups, then the rest)
 *   others   = every further open reason, rendered as chips on the same row
 *   carried  = calls due EARLIER that are still open (yesterday's missed call,
 *              an old recall, an overdue estimate) — they ride on the patient's
 *              today row instead of appearing again somewhere else
 *
 * Pending Calls = patients who have NO row on Today and at least one overdue
 * item. A patient never appears on both boards.
 *
 * "Yesterday's Missed Calls" is no longer a board section: its rows are simply
 * queue calls due yesterday, i.e. overdue items, and are treated as such here.
 * The engine still emits the category unchanged for the Huddle and mobile API.
 *
 * Everything the engine produced is kept — this is grouping, deduping (the same
 * queue row reached through two categories is one reason) and presentation.
 * Nothing here queries the database.
 */
class TodayCallList
{
    /** Queue-backed categories share one record: the CommunicationQueue row. */
    private const QUEUE_BACKED = ['recall_calls', 'missed_calls_yesterday', 'logged_communications'];

    /** Internal automation-rule names must never surface to reception. */
    private const RULE_LABELS = [
        'implant_followup'                => 'Implant follow-up',
        'post_treatment_followup'         => 'Post-treatment follow-up',
        'recall_6months'                  => 'Six-month recall due',
        'membership_renewal_30d'          => 'Membership renewal due',
        'birthday_3d'                     => 'Birthday in 3 days',
        'opportunity_nudge_7d'            => 'Treatment decision follow-up',
        'estimate_followup_3d'            => 'Estimate follow-up',
        'missed_appointment_followup'     => 'Missed appointment follow-up',
        'lab_ready_call'                  => 'Lab work ready',
        'payment_overdue_3d'              => 'Payment follow-up',
        'presentation_callback_requested' => 'Call-back requested by patient',
        'case_opened_followup_2d'         => 'Case follow-up',
        'case_more_time_requested'        => 'Patient asked for more time',
    ];

    private const CHANNELS = [
        'call'     => ['ti-phone',          'Call'],
        'phone'    => ['ti-phone',          'Call'],
        'whatsapp' => ['ti-brand-whatsapp', 'WhatsApp'],
        'sms'      => ['ti-message-2',      'SMS'],
        'email'    => ['ti-mail',           'Email'],
        'visit'    => ['ti-building-store', 'In clinic'],
    ];

    private const PRIORITY_RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    /** Band rank of "Try again" — after every working band (TodayController::GROUP_ORDER). */
    private const RETRY_BAND_RANK = 4;

    /**
     * @param  array<string, array>  $todayGroups    category => items due today (Action Board mode, done rows annotated)
     * @param  array<string, array>  $overdueGroups  category => items due before today, still open
     * @param  array<string, array>  $catMeta        category => ['label','icon','group','group_rank','group_order']
     * @return array{
     *   rows: array<int, array>,        open patient rows for Today, in worked order
     *   doneRows: array<int, array>,    rows whose every reason was handled today
     *   pendingRows: array<int, array>, patients with only overdue work — the Pending Calls board
     *   carried: int,                   overdue reasons folded onto Today rows
     *   tabCounts: array<string,int>,   category => number of Today rows carrying it
     *   pendingTabCounts: array<string,int>,
     *   missedYesterday: int            overdue reasons that were due yesterday (all boards)
     * }
     */
    public function build(array $todayGroups, array $overdueGroups, Carbon $today, array $catMeta): array
    {
        $todayStr     = $today->toDateString();
        $yesterdayStr = $today->copy()->subDay()->toDateString();

        $todayItems   = [];
        $overdueItems = [];

        foreach ($todayGroups as $catKey => $items) {
            foreach ($items as $idx => $item) {
                $view = $this->decorate($item, $catKey, $idx, $today, $catMeta);

                // Yesterday's missed calls are overdue work by definition —
                // they belong on the patient's row as a carried reason, or on
                // Pending when the patient has nothing due today.
                if ($view['cat'] === 'missed_calls_yesterday' && ! $view['isDone']) {
                    $overdueItems[] = $view;
                    continue;
                }

                $todayItems[] = $view;
            }
        }

        foreach ($overdueGroups as $catKey => $items) {
            foreach ($items as $idx => $item) {
                // Mirror of TodayController::extractPendingItems(): only a dated,
                // open item that is past due is overdue. Categories without a
                // due_date (today's appointments, lab ready, …) are today-work
                // and are already in $todayGroups.
                $due = $item['due_date'] ?? null;
                if (! empty($item['done']) || ! $due || $due >= $todayStr) {
                    continue;
                }
                $overdueItems[] = $this->decorate($item, $catKey, 'o' . $idx, $today, $catMeta);
            }
        }

        // Worked order — the same tuple the board has always sorted by.
        $byRank = fn (array $a, array $b) => $a['sortKey'] <=> $b['sortKey'];
        usort($todayItems, $byRank);
        usort($overdueItems, $byRank);

        // ── Group by patient ─────────────────────────────────────────────
        $buckets = [];   // rowKey => ['open' => [], 'done' => [], 'carried' => []]
        $seen    = [];   // rowKey => subjectKey => true (dedupe)

        foreach ($todayItems as $view) {
            $key = $this->rowKey($view);
            $sub = $this->subjectKey($view);
            if ($sub !== null) {
                if (isset($seen[$key][$sub])) {
                    continue;
                }
                $seen[$key][$sub] = true;
            }
            $buckets[$key][$view['isDone'] ? 'done' : 'open'][] = $view;
        }

        $pendingBuckets  = [];
        $missedYesterday = 0;

        foreach ($overdueItems as $view) {
            $key = $this->rowKey($view);
            $sub = $this->subjectKey($view);
            if ($sub !== null) {
                if (isset($seen[$key][$sub])) {
                    continue; // same record already on this patient's row
                }
                $seen[$key][$sub] = true;
            }

            if ($view['dueSort'] === $yesterdayStr) {
                $missedYesterday++;
            }

            if (! empty($buckets[$key]['open']) || ! empty($buckets[$key]['done'])) {
                // The patient is being called today anyway — carry it.
                $buckets[$key]['carried'][] = $view;
            } else {
                $pendingBuckets[$key]['open'][] = $view;
            }
        }

        $rows     = [];
        $doneRows = [];
        $carried  = 0;

        foreach ($buckets as $key => $b) {
            $open      = array_merge($b['open'] ?? [], $b['carried'] ?? []);
            $carried  += count($b['carried'] ?? []);
            $done      = $b['done'] ?? [];

            if ($open) {
                usort($open, $byRank);
                $rows[] = $this->row($key, $open, $done);
            } elseif ($done) {
                $doneRows[] = $this->row($key, [], $done);
            }
        }

        $pendingRows = [];
        foreach ($pendingBuckets as $key => $b) {
            $open = $b['open'];
            usort($open, $byRank);
            $pendingRows[] = $this->row($key, $open, []);
        }

        usort($rows, $byRank);
        usort($doneRows, $byRank);
        usort($pendingRows, $byRank);

        return [
            'rows'             => $rows,
            'doneRows'         => $doneRows,
            'pendingRows'      => $pendingRows,
            'carried'          => $carried,
            'tabCounts'        => $this->tabCounts($rows),
            'pendingTabCounts' => $this->tabCounts($pendingRows),
            'missedYesterday'  => $missedYesterday,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Row assembly
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @param  array<int, array>  $open  open reasons, already in rank order (first = primary)
     * @param  array<int, array>  $done  reasons handled today
     */
    private function row(string $key, array $open, array $done): array
    {
        $lead   = $open[0] ?? $done[0];
        $isDone = $open === [];
        $all    = array_merge($open, $done);

        $primary = $lead;
        $others  = array_slice($open, 1);

        $categories = array_values(array_unique(array_map(fn ($i) => $i['groupKey'], $open ?: $done)));

        // The latest attempt on ANY reason of this call (one call, one log).
        $lastCall = null;
        foreach ($open as $v) {
            if (! empty($v['item']['last_call'])) {
                $lastCall = $v['item']['last_call'];
                break;
            }
        }
        $owner = $isDone ? ($primary['item']['done']['by'] ?? null) : ($lastCall['by'] ?? null);

        // THREE statuses (Sumit, 11 Sep): Open → Attempted → Done. Overdue is
        // a date, shown red in the Due column, not a status. An attempted row
        // (no answer, busy, call back later) leaves the queue's working order
        // and sinks into the "Try again" band at the bottom, keeping its own
        // rank there so retries happen in the same order as the first pass.
        $attempted = ! $isDone && $lastCall !== null;
        if ($isDone)          { $stCls = 'taw-st--done';  $stTxt = 'Done'; }
        elseif ($attempted)   { $stCls = 'taw-st--tried'; $stTxt = 'Attempted'; }
        else                  { $stCls = 'taw-st--open';  $stTxt = 'Open'; }

        $band    = $attempted ? 'retry' : $primary['band'];
        $sortKey = $primary['sortKey'];
        if ($attempted) {
            $sortKey[0] = self::RETRY_BAND_RANK;
        }

        $search = mb_strtolower(implode(' ', array_merge(
            [$primary['item']['patient_name'] ?? ''],
            array_map(fn ($i) => $i['doText'] . ' ' . $i['whyText'] . ' ' . $i['catLabel'], $all)
        )));

        return [
            'id'              => $key,
            'patient_name'    => $primary['item']['patient_name'] ?? 'Unknown',
            'patient_id'      => $primary['item']['patient_id'] ?? null,
            'lead_id'         => $primary['item']['lead_id'] ?? null,
            'relationship_id' => $primary['item']['relationship_id'] ?? null,
            'link'            => $primary['item']['link'] ?? '#',
            'phone'           => $primary['item']['meta']['phone'] ?? null,
            'priority'        => $this->highestPriority($open ?: $done),
            'primary'         => $primary,
            'others'          => $others,
            'items'           => $open,
            'doneItems'       => $done,
            'chips'           => array_map(fn ($i) => $this->chip($i, $primary), $others),
            'categories'      => $categories,
            'reasonCount'     => count($open),
            'band'            => $band,
            'sortKey'         => $sortKey,
            'owner'           => $owner,
            'stCls'           => $stCls,
            'stTxt'           => $stTxt,
            'isDone'          => $isDone,
            'attempted'       => $attempted,
            'lastCall'        => $lastCall,
            'done'            => $isDone ? ($primary['item']['done'] ?? null) : null,
            'search'          => $search,
        ];
    }

    /**
     * One chip on the row: what else this call covers. A reason of the SAME
     * category as the primary (a second unpaid invoice, a second lab case)
     * shows its distinguishing detail instead of repeating the label.
     */
    private function chip(array $view, array $primary): array
    {
        $item   = $view['item'];
        $money  = $view['cat'] === 'payment_reminders';
        $parts  = [];

        if ($money && ! empty($item['meta']['balance_due'])) {
            $parts[] = '₹' . number_format((float) $item['meta']['balance_due']);
        }
        if ($view['dueTxt'] !== '—') {
            $parts[] = $money ? 'due ' . $view['dueTxt'] : $view['dueTxt'];
        }
        $detail = implode(' · ', $parts);
        $same   = $view['groupKey'] === $primary['groupKey'];

        // A second reason of the same kind: say WHICH one, not the label
        // again — the amount for money, the note for a follow-up/recall.
        if ($same) {
            $why   = $money ? '' : trim((string) preg_replace('/\s*(?:—|-)\s*overdue by .*$/iu', '', $view['whyText']));
            $why   = $why !== '' ? mb_strimwidth($why, 0, 34, '…') : '';
            $text  = trim(implode(' · ', array_filter([$why, $detail], fn ($p) => $p !== '')));
            $label = '+ ' . ($text !== '' ? $text : $view['catLabel']);
        }

        return [
            'id'     => $view['itemId'],
            'cat'    => $view['groupKey'],
            'label'  => $same ? $label : $view['catLabel'],
            'detail' => $same ? '' : $detail,
            'over'   => $view['isOverdue'],
            'money'  => $money,
            'title'  => $view['doText'] . ($view['whyText'] ? ' — ' . $view['whyText'] : '') . ' · ' . $view['dueTip'],
        ];
    }

    private function highestPriority(array $views): string
    {
        $best = 'low';
        foreach ($views as $v) {
            $p = $v['item']['priority'] ?? 'low';
            if ((self::PRIORITY_RANK[$p] ?? 3) < (self::PRIORITY_RANK[$best] ?? 3)) {
                $best = $p;
            }
        }
        return $best;
    }

    /** category => rows carrying that reason. Counts PATIENTS, never reasons. */
    private function tabCounts(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            foreach ($row['categories'] as $cat) {
                $counts[$cat] = ($counts[$cat] ?? 0) + 1;
            }
        }
        return $counts;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Identity
    // ═══════════════════════════════════════════════════════════════════════

    /** Which row does this item belong to? Patient first, then lead, else alone. */
    private function rowKey(array $view): string
    {
        $item = $view['item'];
        if (! empty($item['patient_id'])) {
            return 'p' . $item['patient_id'];
        }
        if (! empty($item['lead_id'])) {
            return 'l' . $item['lead_id'];
        }
        return 'x_' . $view['itemId'];
    }

    /**
     * The record behind an item — mirrors the server's category → record map
     * (TodayController::QUEUE_BACKED_CATEGORIES / DISMISSIBLE_MODELS and the
     * drawer's subjectIdFor()). Two items with the same key are the same
     * record reached through two categories and must render once.
     */
    private function subjectKey(array $view): ?string
    {
        $item = $view['item'];
        $cat  = $item['category'] ?? $view['cat'];
        $meta = $item['meta'] ?? [];

        if (in_array($cat, self::QUEUE_BACKED, true)) {
            return isset($meta['comm_queue_id']) ? 'cq:' . $meta['comm_queue_id'] : null;
        }

        return match ($cat) {
            'follow_up_calls'                                  => isset($meta['follow_up_id']) ? 'fu:' . $meta['follow_up_id'] : null,
            'new_enquiries', 'lead_followups'                  => ! empty($item['lead_id']) ? 'lead:' . $item['lead_id'] : null,
            'tasks'                                            => isset($meta['id']) ? 'task:' . $meta['id'] : null,
            'opportunities', 'pending_estimates'               => isset($meta['id']) ? 'opp:' . $meta['id'] : null,
            'appointment_reminders', 'missed_appointments_yesterday' => isset($meta['id']) ? 'appt:' . $meta['id'] : null,
            default                                            => isset($meta['id']) ? $cat . ':' . $meta['id'] : null,
        };
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Presentation of ONE engine item (moved verbatim from the view, 2026-09-11)
    // ═══════════════════════════════════════════════════════════════════════

    private function decorate(array $item, string $catKey, int|string $idx, Carbon $today, array $catMeta): array
    {
        $meta        = $catMeta[$catKey] ?? [];
        $todayStr    = $today->toDateString();
        $tomorrowStr = $today->copy()->addDay()->toDateString();

        $done     = $item['done'] ?? null;
        $lastCall = $item['last_call'] ?? null;

        // ── WHEN ── the item's own due date, else the most meaningful date
        // its meta already carries. Never invented.
        $parse = function ($v) {
            try { return $v ? Carbon::parse($v) : null; }
            catch (\Throwable $e) { return null; }
        };
        $dueTxt = '—'; $dueCls = 'taw-due--none'; $dueTip = 'No date on record';
        $d    = $parse($item['due_date'] ?? null);
        $kind = 'due';
        if (! $d) {
            foreach ([['due_date', 'due'], ['follow_up_date', 'due'], ['appointment_date', 'appt'],
                      ['end_date', 'expires'], ['ready_since', 'ready'], ['visit_date', 'visit']] as $probe) {
                if (! empty($item['meta'][$probe[0]])) {
                    $d = $parse($item['meta'][$probe[0]]);
                    if ($d) { $kind = $probe[1]; break; }
                }
            }
        }
        if ($d) {
            $ds   = $d->toDateString();
            $long = $d->format('D, d M Y');
            if ($kind === 'due') {
                if ($ds === $todayStr)          { $dueTxt = 'Today';    $dueCls = ''; $dueTip = 'Due today'; }
                elseif ($ds === $tomorrowStr)   { $dueTxt = 'Tomorrow'; $dueCls = ''; $dueTip = 'Due ' . $long; }
                elseif ($ds < $todayStr)        { $dueTxt = $d->format('d M'); $dueCls = 'taw-due--over'; $dueTip = 'Overdue since ' . $long; }
                else                            { $dueTxt = $d->format('d M'); $dueCls = ''; $dueTip = 'Due ' . $long; }
            } else {
                $prefix = ['appt' => 'Appt', 'expires' => 'Expires', 'ready' => 'Ready', 'visit' => 'Visit'][$kind] ?? '';
                $when   = $ds === $todayStr ? 'today' : ($ds === $tomorrowStr ? 'tomorrow' : $d->format('d M'));
                $dueTxt = trim($prefix . ' ' . $when);
                $dueCls = ($kind === 'expires' && $ds < $todayStr) ? 'taw-due--over' : '';
                $dueTip = $prefix . ': ' . $long;

                // An appointment's TIME is the thing reception works to.
                if ($kind === 'appt' && ! empty($item['meta']['appointment_time'])) {
                    $apptTime = $parse($item['meta']['appointment_time']);
                    $timeTxt  = $apptTime ? $apptTime->format('g:i A') : $item['meta']['appointment_time'];
                    $dueTip  .= ' at ' . $timeTxt;

                    if ($ds === $todayStr || $ds === $tomorrowStr) {
                        $dueTxt = trim($prefix . ' ' . ($ds === $todayStr ? '' : 'tmrw') . ' ' . $timeTxt);
                    }
                }
            }
        }
        $isOverdue = $dueCls === 'taw-due--over';

        // ── CHANNEL ── real values only.
        $chKey = strtolower((string) ($item['meta']['channel'] ?? ''));
        if (($item['primary_action'] ?? null) === 'whatsapp') { $chKey = 'whatsapp'; }
        if ($chKey === '' || ! isset(self::CHANNELS[$chKey]))  { $chKey = 'call'; }

        $doText  = $this->humanise($item['suggested_action'] ?? '') ?: 'Call the patient';
        $whyText = $this->humanise($item['reason'] ?? '');

        $bandRank  = $meta['group_rank']  ?? 3;
        $bandOrder = $meta['group_order'] ?? 99;
        $prRank    = self::PRIORITY_RANK[$item['priority'] ?? 'low'] ?? 3;
        $dueSort   = $d ? $d->toDateString() : '9999-12-31';
        $timeSort  = (string) ($item['meta']['appointment_time'] ?? '99:99:99');

        return [
            'itemId'    => $catKey . '_' . $idx,
            'cat'       => $item['category'] ?? $catKey,
            'groupKey'  => $catKey,
            'catLabel'  => $meta['label'] ?? ucwords(str_replace('_', ' ', $catKey)),
            'catIcon'   => $meta['icon'] ?? 'ti-circle',
            'item'      => $item,
            'isDone'    => (bool) $done,
            'dueTxt'    => $dueTxt,
            'dueCls'    => $dueCls,
            'dueTip'    => $dueTip,
            'dueSort'   => $dueSort,
            'isOverdue' => $isOverdue,
            'chIcon'    => self::CHANNELS[$chKey][0],
            'chLabel'   => self::CHANNELS[$chKey][1],
            'doText'    => $doText,
            'whyText'   => $whyText,
            'band'      => $meta['group'] ?? 'other',
            'sortKey'   => [$bandRank, $bandOrder, $prRank, $dueSort, $timeSort],
            // The drawer payload: the engine item with the two display strings
            // cleaned. 'reason' / 'suggested_action' are shown, never submitted.
            'drawer'    => array_merge($item, [
                'reason'           => $whyText ?: ($item['reason'] ?? ''),
                'suggested_action' => $doText,
                'item_id'          => $catKey . '_' . $idx,
                'due_label'        => $dueTxt,
                'is_overdue'       => $isOverdue,
                'cat_label'        => $meta['label'] ?? ucwords(str_replace('_', ' ', $catKey)),
            ]),
        ];
    }

    private function humanise(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/\[\s*auto\s*\]\s*/i', '', $text);
        $text = preg_replace_callback('/rule\s*:\s*([a-z0-9_]+)/i', function ($m) {
            $key = strtolower($m[1]);
            return self::RULE_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
        }, $text);

        return trim($text);
    }
}
