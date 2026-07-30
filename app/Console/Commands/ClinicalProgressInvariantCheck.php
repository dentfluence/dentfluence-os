<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * progress:invariant-check — Architecture Invariant Guard for Clinical Progress.
 *
 * PERMANENT DENTFLUENCE ENGINEERING INVARIANT (Slice 2.4c/2.4d)
 * ------------------------------------------------------------
 * There shall be exactly ONE canonical derivation model for clinical progress.
 * No component outside DerivedProgressService may derive progress from the
 * captured clinical fact `work_outcome`.
 *
 * This guard exists because the alternative failure needs no bad intent, only
 * convenience. `treatment_plans.status` still exists, is indexed, is one join
 * shallower, and LOOKS authoritative. A developer adding a dashboard tile, a
 * report filter or an AI tool call will reach for it, and the number will be
 * plausible enough that nobody notices it disagrees.
 *
 * This system has already failed exactly this way three times:
 *   • treatment_plan_items.status         inert, but indexed and still read once
 *   • TreatmentPlanItemTooth::STATUS_COMPLETED   declared, never written
 *   • the Opportunity board read as clinical presentation truth (fixed in 2.2)
 *
 * Exit 0 = clean, Exit 1 = a violation exists. Run in CI / before a commit.
 */
class ClinicalProgressInvariantCheck extends Command
{
    protected $signature = 'progress:invariant-check {--path=app : Directory (relative to project root) to scan}';

    protected $description = 'Enforce the Clinical Progress Policy: progress may only be derived by DerivedProgressService.';

    /** The one canonical derivation point. */
    private const CANONICAL = [
        'app/Services/Clinical/DerivedProgressService.php',
    ];

    /**
     * Permitted non-derivation touchpoints for `work_outcome`.
     *
     * These CAPTURE or DECLARE the fact; they never derive progress from it.
     * Do not add readers here — a new entry means a second truth.
     */
    private const CAPTURE_POINTS = [
        'app/Models/TreatmentVisitItem.php',        // fillable + the three constants
        'app/Services/TreatmentVisitService.php',   // the writer (Slice 2.4b) + read-back payload
        'app/Console/Commands/ClinicalProgressInvariantCheck.php', // self (holds the patterns as strings)
    ];

    /** The captured clinical fact. Reading it outside the allow-list is a violation. */
    private const FACT = 'work_outcome';

    public function handle(): int
    {
        $base    = base_path();
        $relRoot = trim((string) $this->option('path'), '/\\') ?: 'app';
        $scanDir = $base.DIRECTORY_SEPARATOR.$relRoot;

        if (! is_dir($scanDir)) {
            $this->error("Scan directory not found: {$relRoot}");

            return self::FAILURE;
        }

        $allowed    = array_merge(self::CANONICAL, self::CAPTURE_POINTS);
        $violations = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($scanDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));

            if (in_array($relative, $allowed, true)) {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            // Fast reject before the (more expensive) tokenizer pass.
            if (! str_contains($contents, self::FACT)) {
                continue;
            }

            foreach ($this->factReadsInCode($contents) as $line) {
                $violations[] = $relative.':'.$line.'  '.trim($this->lineAt($contents, $line));
            }
        }

        if ($violations !== []) {
            $this->error('CLINICAL PROGRESS INVARIANT VIOLATED — progress derived outside DerivedProgressService:');
            $this->newLine();
            foreach ($violations as $v) {
                $this->line('  '.$v);
            }
            $this->newLine();
            $this->line('  Clinical progress must be read through App\Services\Clinical\DerivedProgressService.');
            $this->line('  See docs/patient-journey-v1_1-slice-2_4c-derivation-contract.md');

            return self::FAILURE;
        }

        $this->info('Clinical progress invariant holds — '.self::FACT.' is only read by the canonical service.');

        return self::SUCCESS;
    }

    /**
     * Line numbers where the captured fact is referenced in EXECUTABLE CODE.
     *
     * Comments and docblocks are excluded on purpose: documentation SHOULD name
     * the column it is describing, and a guard that punishes accurate comments
     * teaches people to write vague ones. PHP's own tokenizer decides what is
     * code, rather than a regex guessing at it.
     *
     * @return array<int,int>
     */
    private function factReadsInCode(string $source): array
    {
        $lines = [];

        foreach (token_get_all($source) as $token) {
            if (! is_array($token)) {
                continue;                       // punctuation — cannot contain the fact
            }

            [$id, $text, $line] = $token;

            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;                       // documentation, not a read
            }

            if (str_contains($text, self::FACT)) {
                $lines[] = $line;
            }
        }

        return array_values(array_unique($lines));
    }

    private function lineAt(string $source, int $line): string
    {
        return preg_split('/\R/', $source)[$line - 1] ?? '';
    }
}
