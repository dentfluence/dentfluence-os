<?php

namespace App\Support;

/**
 * DosageForm — the ONE place a dosage form is turned into its printed
 * abbreviation. Prescriptions are read at a glance; "Tab. Flexon" is how an
 * Indian Rx reads, not "Tablet Flexon" and not "Flexon 400+325mg · Tablet".
 *
 * Unknown forms are returned as-is (trimmed) rather than dropped — a clinic
 * that types "Mouthwash" should still see it, just unabbreviated. Blank in,
 * blank out, so the caller can safely concatenate.
 */
final class DosageForm
{
    /** Lowercased form => printed abbreviation. */
    private const ABBREVIATIONS = [
        'tablet'        => 'Tab.',
        'tab'           => 'Tab.',
        'capsule'       => 'Cap.',
        'cap'           => 'Cap.',
        'syrup'         => 'Syr.',
        'syr'           => 'Syr.',
        'suspension'    => 'Susp.',
        'injection'     => 'Inj.',
        'inj'           => 'Inj.',
        'ointment'      => 'Oint.',
        'cream'         => 'Cream',
        'gel'           => 'Gel',
        'paste'         => 'Paste',
        'drops'         => 'Drops',
        'drop'          => 'Drops',
        'mouthwash'     => 'M/W',
        'spray'         => 'Spray',
        'lotion'        => 'Lotion',
        'powder'        => 'Powder',
        'solution'      => 'Soln.',
        'sachet'        => 'Sachet',
        'inhaler'       => 'Inhaler',
        'lozenge'       => 'Loz.',
    ];

    public static function abbreviate(?string $form): string
    {
        $form = trim((string) $form);

        if ($form === '') {
            return '';
        }

        return self::ABBREVIATIONS[mb_strtolower($form)] ?? $form;
    }
}
