<?php

namespace App\Console\Commands;

use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Services\Billing\PatientPaymentVoidService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair (S1, 25 Sep 2026).
 *
 * Before 28f9c04, cancelling an invoice that had been paid through a
 * patient-level payment (PAY- receipt) voided the allocation row and its
 * income but left the PAY- receipt itself live. That receipt then counts as
 * money received against nothing. Found on prod: PAY-2026-00002 (20 Sep,
 * wrong-date entry, re-recorded as RCP-2026-00253) and PAY-2026-00003 (test).
 *
 * An ORPHAN is a PAY- receipt that is not voided, settled at least one
 * invoice, and every one of whose allocations is already reversed, with no
 * active income left behind any of them. It is marked void exactly as
 * PatientPaymentVoidService does (voided_at + reason + audit row), with the
 * correction type "not received" because the reversal already removed the
 * income and created no credit. Nothing is deleted.
 *
 *   php artisan billing:void-orphan-payments            # report only
 *   php artisan billing:void-orphan-payments --apply --by=1
 */
class VoidOrphanPatientPayments extends Command
{
    protected $signature = 'billing:void-orphan-payments
                            {--apply : Write the changes (default is report only)}
                            {--by= : User id recorded as the one who voided}';

    protected $description = 'Mark PAY- receipts void whose invoice allocations were all reversed by an invoice cancel';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $by    = $this->option('by') ? (int) $this->option('by') : null;

        if ($apply && ! $by) {
            $this->error('--apply needs --by=<user id>.');
            return self::FAILURE;
        }

        $orphans = Receipt::whereNull('invoice_id')
            ->where(fn ($q) => $q->whereNull('receipt_kind')->orWhere('receipt_kind', '!=', 'advance'))
            ->whereNull('voided_at')
            ->get()
            ->filter(function (Receipt $r) {
                $allocs = InvoicePayment::withTrashed()->where('receipt_id', $r->id)->get();
                if ($allocs->isEmpty() || $allocs->contains(fn ($p) => $p->deleted_at === null)) {
                    return false;
                }
                $patientCredit = (float) (($r->allocation_breakdown ?? [])['patient_credit'] ?? 0);
                if ($patientCredit > 0.009) {
                    return false; // part of it is live credit - needs a human, not this command
                }

                return ! FinanceTransaction::where('source_type', InvoicePayment::class)
                    ->whereIn('source_id', $allocs->pluck('id'))
                    ->where('status', 'active')
                    ->exists();
            });

        if ($orphans->isEmpty()) {
            $this->info('No orphan patient payments found.');
            return self::SUCCESS;
        }

        foreach ($orphans as $r) {
            $this->line(sprintf('%s  patient %d  Rs %s  %s', $r->receipt_number, $r->patient_id,
                number_format((float) $r->amount, 2), $apply ? 'VOIDED' : 'would void'));
        }

        if (! $apply) {
            $this->warn('Report only. Re-run with --apply --by=<user id> to mark these void.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphans, $by) {
            foreach ($orphans as $r) {
                $reason = 'Orphan: its invoice allocation was already reversed by an invoice cancel (S1 repair 25 Sep 2026).';
                $r->update([
                    'voided_at'            => now(),
                    'void_reason'          => $reason,
                    'voided_by'            => $by,
                    'void_correction_type' => PatientPaymentVoidService::NOT_RECEIVED,
                ]);
                BillingAuditLog::record('void_patient_payment', $r, $reason, $by, $r->receipt_number);
            }
        });

        $this->info($orphans->count() . ' receipt(s) marked void.');
        return self::SUCCESS;
    }
}
