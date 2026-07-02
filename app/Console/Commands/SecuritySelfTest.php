<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceAuditLog;
use App\Models\Patient;
use App\Models\Prescription\PrescriptionAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * security:selftest  (Phase A — verification)
 * -------------------------------------------
 * Read-only, non-destructive checks that the Phase A security guarantees are
 * actually in force. Prints PASS / FAIL / WARN for each. Re-run any time.
 *
 *   php artisan security:selftest
 *
 * WARN means "couldn't fully check" (usually no data to sample) — not a failure.
 */
class SecuritySelfTest extends Command
{
    protected $signature = 'security:selftest';
    protected $description = 'Verify Phase A security controls (encryption, audit chains, MFA, headers, config).';

    private array $rows = [];
    private int $fail = 0;

    public function handle(): int
    {
        $this->line('');
        $this->info('Dentfluence — Phase A security self-test');
        $this->line('');

        $this->checkConfig();
        $this->checkPasswordPolicy();
        $this->checkEncryptionAtRest();
        $this->checkAuditChains();
        $this->checkAppendOnly();
        $this->check2fa();
        $this->checkRoutesAndHeaders();

        $this->table(['Check', 'Result', 'Detail'], $this->rows);

        if ($this->fail > 0) {
            $this->error("{$this->fail} check(s) FAILED — review above.");
            return self::FAILURE;
        }

        $this->info('All checks passed (warnings, if any, are non-blocking).');
        return self::SUCCESS;
    }

    /* ── individual checks ─────────────────────────────────────────────────── */

    private function checkConfig(): void
    {
        $this->record('Tokens expire (sanctum.expiration)', (int) config('sanctum.expiration') > 0,
            'expiration = ' . var_export(config('sanctum.expiration'), true));

        $this->record('Absolute session timeout set', (int) config('session.absolute_lifetime') > 0,
            'absolute_lifetime = ' . config('session.absolute_lifetime') . ' min');

        $cors = config('cors.allowed_origins');
        $this->record('CORS not wide-open (*)', is_array($cors) && ! in_array('*', $cors, true),
            'allowed_origins = ' . implode(',', (array) $cors));

        $this->record('Security config present', config('security.hsts') !== null,
            'force_https=' . var_export(config('security.force_https'), true) . ' hsts=' . var_export(config('security.hsts'), true));
    }

    private function checkPasswordPolicy(): void
    {
        $weak = Validator::make(['password' => 'abc'], ['password' => Password::defaults()]);
        $strong = Validator::make(['password' => 'Abcd1234x'], ['password' => Password::defaults()]);

        $this->record('Weak password rejected', $weak->fails(), "'abc' " . ($weak->fails() ? 'rejected' : 'ACCEPTED'));
        $this->record('Strong password accepted', $strong->passes(), "'Abcd1234x' " . ($strong->passes() ? 'accepted' : 'REJECTED'));
    }

    private function checkEncryptionAtRest(): void
    {
        // Find a patient row that has at least one encrypted column populated.
        $candidates = ['address', 'medical_alert', 'chief_complaint', 'abha_number'];
        $row = null;
        $col = null;

        foreach ($candidates as $c) {
            if (! Schema::hasColumn('patients', $c)) {
                continue;
            }
            $found = DB::table('patients')->whereNotNull($c)->where($c, '!=', '')->first(['id', $c]);
            if ($found) {
                $row = $found;
                $col = $c;
                break;
            }
        }

        if (! $row) {
            $this->record('PHI encrypted at rest', null, 'No patient PHI to sample yet (add a patient or run patients:encrypt-phi)');
            return;
        }

        $raw = $row->{$col};
        $isCipher = $this->looksEncrypted($raw);

        // And confirm the model transparently decrypts it back to readable text.
        $model = Patient::find($row->id);
        $readable = $model ? $model->{$col} : null;
        $decrypts = $readable !== null && $readable !== $raw;

        $this->record("PHI encrypted at rest (patients.$col)", $isCipher && $decrypts,
            $isCipher ? 'stored value is ciphertext, model decrypts OK' : 'stored value appears to be PLAINTEXT');
    }

    private function checkAuditChains(): void
    {
        foreach ([
            'audit_logs'              => AuditLog::class,
            'billing_audit_logs'      => BillingAuditLog::class,
            'prescription_audit_logs' => PrescriptionAuditLog::class,
            'finance_audit_log'       => FinanceAuditLog::class,
        ] as $table => $class) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'hash')) {
                $this->record("Audit chain: $table", null, 'table/hash column missing');
                continue;
            }
            $res = $class::verifyChain();
            $detail = "checked {$res['checked']} row(s)" . ($res['ok'] ? '' : " — first bad id {$res['first_bad_id']}");
            $this->record("Audit chain intact: $table", $res['ok'], $detail);
        }
    }

    private function checkAppendOnly(): void
    {
        $row = AuditLog::query()->orderBy('id')->first();
        if (! $row) {
            $this->record('Audit log is append-only', null, 'no audit rows to test against yet');
            return;
        }

        // Attempt a REAL change (so Eloquent actually fires the updating event)
        // inside a transaction we always roll back — fully non-destructive even
        // in the unexpected case the guard fails to fire.
        $blocked = false;
        DB::beginTransaction();
        try {
            $row->action = $row->action . '.selftest';
            $row->save();
        } catch (\Throwable $e) {
            $blocked = true;
        } finally {
            DB::rollBack();
        }

        $this->record('Audit log is append-only', $blocked,
            $blocked ? 'update correctly blocked' : 'update was NOT blocked');
    }

    private function check2fa(): void
    {
        $cols = Schema::hasColumn('users', 'two_factor_secret')
            && Schema::hasColumn('users', 'two_factor_recovery_codes')
            && Schema::hasColumn('users', 'two_factor_confirmed_at');

        $this->record('2FA columns present', $cols, $cols ? 'users table ready for MFA' : 'run migrate');
        $this->record('2FA routes registered',
            Route::has('two-factor.setup') && Route::has('two-factor.challenge'),
            'setup + challenge routes');

        $pkg = class_exists(\PragmaRX\Google2FAQRCode\Google2FA::class);
        $this->record('2FA package installed', $pkg, $pkg ? 'pragmarx/google2fa-qrcode' : 'composer require missing');
    }

    private function checkRoutesAndHeaders(): void
    {
        // Secure media + login throttle.
        $this->record('Secure media route present', Route::has('secure.media.file'), 'authenticated clinical-file route');

        $login = Route::getRoutes()->getByName('login.post');
        $throttled = $login && collect($login->gatherMiddleware())->contains(fn ($m) => str_contains((string) $m, 'throttle'));
        $this->record('Login is throttled', (bool) $throttled, $throttled ? 'throttle middleware on login.post' : 'no throttle found');

        // Web security-header + session-timeout middleware registered on the web group.
        $web = app('router')->getMiddlewareGroups()['web'] ?? [];
        $hasHeaders = in_array(\App\Http\Middleware\SecureWebHeaders::class, $web, true);
        $hasTimeout = in_array(\App\Http\Middleware\AbsoluteSessionTimeout::class, $web, true);
        $this->record('Web security headers middleware', $hasHeaders, 'SecureWebHeaders on web group');
        $this->record('Absolute-timeout middleware', $hasTimeout, 'AbsoluteSessionTimeout on web group');
    }

    /* ── helpers ───────────────────────────────────────────────────────────── */

    private function looksEncrypted(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param bool|null $ok  true=PASS, false=FAIL, null=WARN (couldn't check)
     */
    private function record(string $label, ?bool $ok, string $detail = ''): void
    {
        if ($ok === false) {
            $this->fail++;
        }
        $status = $ok === true ? 'PASS' : ($ok === false ? 'FAIL' : 'WARN');
        $this->rows[] = [$label, $status, $detail];
    }
}
