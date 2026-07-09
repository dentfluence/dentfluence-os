<?php

namespace App\Services\Presentations;

/**
 * ToothLocationDescriber — deterministic FDI tooth-number → plain-English
 * location. Pure data, no AI. Built specifically to fix a real bug: the AI
 * summary once described two teeth in different locations (e.g. 42 + 46) as
 * if they shared one location ("your bottom front teeth"), because it
 * averaged instead of describing each tooth individually. This class can't
 * make that mistake — it's arithmetic, not language generation.
 *
 * FDI notation: first digit = quadrant (1/2 = upper, 3/4 = lower — permanent;
 * 5/6 = upper, 7/8 = lower — primary/deciduous). Second digit = position from
 * the midline: 1-3 = incisors/canine ("front"), 4-8 = premolars/molars ("back").
 */
class ToothLocationDescriber
{
    /** @return array{number:string, upper:bool, front:bool, label:string}|null */
    public function describe(string|int $toothNumber): ?array
    {
        $number = trim((string) $toothNumber);
        if (! preg_match('/^([1-8])([1-8])$/', $number, $m)) {
            return null; // not a standard FDI two-digit tooth number — describe nothing rather than guess
        }

        $quadrant = (int) $m[1];
        $position = (int) $m[2];

        $upper = in_array($quadrant, [1, 2, 5, 6], true);
        $front = $position <= 3;

        $label = ($upper ? 'upper' : 'lower') . ' ' . ($front ? 'front' : 'back');

        return ['number' => $number, 'upper' => $upper, 'front' => $front, 'label' => $label];
    }

    /**
     * Handles a field that may hold multiple comma-separated tooth numbers
     * (e.g. "42, 46") — describes EACH one individually and never merges them
     * into one shared location, even if that makes the sentence longer.
     */
    public function describeMany(?string $toothNumbers): array
    {
        if (blank($toothNumbers)) {
            return [];
        }

        $numbers = array_filter(array_map('trim', explode(',', $toothNumbers)));

        return array_values(array_filter(array_map(fn ($n) => $this->describe($n), $numbers)));
    }

    /**
     * A natural phrase for a (possibly multi-tooth) field. Falls back to just
     * naming the tooth numbers, with no location claim, if none parse as
     * standard FDI numbers — silence is safer than a guess.
     */
    public function phraseFor(?string $toothNumbers): string
    {
        $described = $this->describeMany($toothNumbers);

        if (empty($described)) {
            return blank($toothNumbers) ? '' : "tooth {$toothNumbers}";
        }

        if (count($described) === 1) {
            return "tooth {$described[0]['number']} ({$described[0]['label']})";
        }

        // Multiple teeth — check whether they actually share one location
        // before claiming they do.
        $sameLabel = count(array_unique(array_column($described, 'label'))) === 1;

        if ($sameLabel) {
            $numbers = implode(' and ', array_column($described, 'number'));
            return "teeth {$numbers} ({$described[0]['label']})";
        }

        // Different locations — describe each one, never average.
        return implode(' and ', array_map(fn ($d) => "tooth {$d['number']} ({$d['label']})", $described));
    }
}
