<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * patients:invariant-check — Architecture Invariant Guard for the Patient
 * Creation Policy.
 *
 * PERMANENT DENTFLUENCE ENGINEERING INVARIANT
 * -------------------------------------------
 * No business workflow may create a Patient directly using Patient::create()
 * (or make/firstOrCreate/updateOrCreate/forceCreate), a relation ->create(),
 * or a raw DB::table('patients')->insert(). Every patient registration MUST
 * flow through PatientService::register() (or another service that delegates
 * to it), which is the single canonical mint point — the only place a TDC /
 * Patient Number is assigned (via the model boot).
 *
 * PERMITTED EXCEPTIONS ONLY:
 *   • Seeders, Factories, Test fixtures   (permanent — not scanned; they live
 *                                           in database/ and tests/)
 *   • Two TEMPORARY appointment paths     (until the Appointments module ships
 *                                           the agreed lifecycle)
 *
 * This command makes the rule automatically enforceable: run it in CI / before
 * a commit. Exit 0 = clean, Exit 1 = a violation exists.
 */
class PatientInvariantCheck extends Command
{
    protected $signature = 'patients:invariant-check {--path=app : Directory (relative to project root) to scan}';

    protected $description = 'Enforce the Patient Creation Policy: patients may only be minted via PatientService::register().';

    /** The one canonical mint point. */
    private const CANONICAL = [
        'app/Services/PatientService.php',
    ];

    /**
     * ⚠ TEMPORARY production exceptions — appointment booking still mints a
     * Patient + TDC at booking. These MUST be REMOVED from this whitelist when
     * the Appointments module implements:
     *     Appointment → Arrived → Registration → Patient Created → TDC Generated
     * (booking should create only an appointment lead; the patient/TDC are
     * minted at Registration). Do not add new entries here.
     */
    private const TEMP_APPOINTMENT = [
        'app/Http/Controllers/AppointmentController.php',
        'app/Services/AppointmentService.php',
    ];

    /** Diagnostic smoke-test commands (test fixtures: dummies in a rolled-back tx). */
    private const TEST_TOOLING = [
        'app/Console/Commands/PatientMergeSmokeTest.php',
        'app/Console/Commands/PatientRegisterSmokeTest.php',
        'app/Console/Commands/PatientInvariantCheck.php', // self (contains the patterns as strings)
    ];

    public function handle(): int
    {
        $base    = base_path();
        $relRoot = trim((string) $this->option('path'), '/\\') ?: 'app';
        $scanDir = $base.DIRECTORY_SEPARATOR.$relRoot;

        if (! is_dir($scanDir)) {
            $this->error("Scan directory not found: {$relRoot}");
            return self::FAILURE;
        }

        $whitelist = array_map(
            fn ($f) => $this->norm($f),
            array_merge(self::CANONICAL, self::TEMP_APPOINTMENT, self::TEST_TOOLING)
        );

        $patterns = [
            'Patient::create() (direct model create)' => [
                'regex'     => '/\bPatient::(create|forceCreate|make|firstOrCreate|updateOrCreate)\s*\(/',
                'lookahead' => false,
            ],
            'Patient relation ->create()' => [
                'regex'     => '/->\s*patients\(\)\s*->\s*create\s*\(/',
                'lookahead' => false,
            ],
            "raw DB::table('patients') insert" => [
                'regex'     => '/->\s*table\(\s*[\'"]patients[\'"]\s*\)\s*->\s*(insert|insertGetId|insertOrIgnore|updateOrInsert)/',
                'lookahead' => true, // allow the insert verb on the next line
            ],
        ];

        $violations = [];
        foreach ($this->phpFiles($scanDir) as $file) {
            $rel = $this->norm(substr($file, strlen($base) + 1));
            if (in_array($rel, $whitelist, true)) {
                continue;
            }

            $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
            foreach ($lines as $i => $line) {
                foreach ($patterns as $type => $cfg) {
                    $target = $cfg['lookahead'] ? $line.' '.($lines[$i + 1] ?? '') : $line;
                    if (preg_match($cfg['regex'], $target) && ! $this->isComment($line)) {
                        $violations[] = [
                            'file'    => $rel,
                            'line'    => $i + 1,
                            'type'    => $type,
                            'snippet' => trim($line),
                        ];
                    }
                }
            }
        }

        if (empty($violations)) {
            $this->info('✅ Patient Creation Policy: PASS — no unapproved patient-creation paths in '.$relRoot.'/.');
            $this->newLine();
            $this->printExceptions();
            return self::SUCCESS;
        }

        $this->error('❌ Patient Creation Policy: '.count($violations).' violation(s) found.');
        foreach ($violations as $v) {
            $this->newLine();
            $this->line("❌ {$v['type']}");
            $this->line("   File: {$v['file']}");
            $this->line("   Line: {$v['line']}");
            $this->line("   Code: {$v['snippet']}");
            $this->line('   Fix:  Register the patient via PatientService::register() (or a service that delegates to it).');
        }
        $this->newLine();
        $this->printExceptions();
        return self::FAILURE;
    }

    private function printExceptions(): void
    {
        $this->line('Approved exceptions (whitelisted):');
        $this->line('  Canonical mint point:');
        foreach (self::CANONICAL as $f) {
            $this->line("    • {$f}  (PatientService::register)");
        }
        $this->line('  ⚠ Temporary (REMOVE during the Appointments module):');
        foreach (self::TEMP_APPOINTMENT as $f) {
            $this->line("    • {$f}");
        }
        $this->line('  Test tooling (diagnostic commands):');
        foreach (self::TEST_TOOLING as $f) {
            $this->line("    • {$f}");
        }
        $this->line('  Permanent (not scanned): database/seeders, database/factories, tests/.');
    }

    /** Recursively yield every .php file under $dir. */
    private function phpFiles(string $dir): iterable
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                yield $file->getPathname();
            }
        }
    }

    /** True when the matched line is a comment (so doc/tech-debt mentions don't trip the guard). */
    private function isComment(string $line): bool
    {
        $t = ltrim($line);
        return str_starts_with($t, '//')
            || str_starts_with($t, '*')
            || str_starts_with($t, '/*')
            || str_starts_with($t, '#');
    }

    private function norm(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
