<?php

namespace App\Support;

/**
 * Handover — the doctor's chairside message to the front desk (N-2).
 *
 * Shape (all keys optional; an all-empty handover is stored as NULL):
 *   collect_amount  int     rupees to collect now
 *   offer_aocp      bool    pitch the AOCP membership
 *   xray            bool    take an X-ray before the patient leaves
 *   book_in_days    int     book the next visit this many days out
 *   note            string  anything else, one line
 *
 * Three jobs, all here so the two forms, the two models and the notifier
 * never disagree about the shape:
 *   rules()      validation for a `handover[...]` request block
 *   normalize()  request/JSON → clean array or null
 *   summary()    clean array → the one line the desk reads
 */
final class Handover
{
    public const KEYS = ['collect_amount', 'offer_aocp', 'xray', 'book_in_days', 'note'];

    /** Validation rules, merged into the parent form's rule set. */
    public static function rules(): array
    {
        return [
            'handover'                => ['nullable', 'array'],
            'handover.collect_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'handover.offer_aocp'     => ['nullable', 'boolean'],
            'handover.xray'           => ['nullable', 'boolean'],
            'handover.book_in_days'   => ['nullable', 'integer', 'min:1', 'max:365'],
            'handover.note'           => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Clean a raw request / JSON block into the stored shape. Empty strings,
     * zeros and unticked boxes fall away; nothing left means NULL, so an
     * untouched form never stores an empty object.
     */
    public static function normalize(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $out = [];

        $amount = (int) round((float) ($raw['collect_amount'] ?? 0));
        if ($amount > 0) {
            $out['collect_amount'] = $amount;
        }

        if (filter_var($raw['offer_aocp'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $out['offer_aocp'] = true;
        }

        if (filter_var($raw['xray'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $out['xray'] = true;
        }

        $days = (int) ($raw['book_in_days'] ?? 0);
        if ($days > 0) {
            $out['book_in_days'] = $days;
        }

        $note = trim((string) ($raw['note'] ?? ''));
        if ($note !== '') {
            $out['note'] = mb_substr($note, 0, 200);
        }

        return $out ?: null;
    }

    /** "Collect ₹400 · Offer AOCP · X-ray · Book in 7 days · note" — or ''. */
    public static function summary(?array $handover): string
    {
        if (! $handover) {
            return '';
        }

        $parts = [];

        if (! empty($handover['collect_amount'])) {
            $parts[] = 'Collect ₹' . number_format((int) $handover['collect_amount']);
        }
        if (! empty($handover['offer_aocp'])) {
            $parts[] = 'Offer AOCP';
        }
        if (! empty($handover['xray'])) {
            $parts[] = 'X-ray';
        }
        if (! empty($handover['book_in_days'])) {
            $d = (int) $handover['book_in_days'];
            $parts[] = 'Book in ' . $d . ' ' . ($d === 1 ? 'day' : 'days');
        }
        if (! empty($handover['note'])) {
            $parts[] = $handover['note'];
        }

        return implode(' · ', $parts);
    }

    /** True when at least one instruction is set. */
    public static function isEmpty(?array $handover): bool
    {
        return self::normalize($handover) === null;
    }
}
