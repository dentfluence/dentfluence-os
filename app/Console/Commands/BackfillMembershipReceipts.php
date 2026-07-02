<?php

namespace App\Console\Commands;

use App\Models\FinalBill;
use App\Models\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMembershipReceipts extends Command
{
    protected $signature   = 'billing:backfill-membership-receipts
                                {--dry-run : Preview without writing anything}';

    protected $description = 'Backfill InvoicePayment + Receipt + FinalBill for membership invoices
                              that are marked paid but were created before receipt generation was added.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find paid membership invoices with no payment records
        $invoices = Invoice::with(['items', 'payments', 'receipts', 'finalBill'])
            ->whereNotNull('membership_id')
            ->where('status', 'paid')
            ->whereDoesntHave('payments')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No invoices need backfilling. All good!');
            return 0;
        }

        $this->info("Found {$invoices->count()} invoice(s) needing backfill:");

        foreach ($invoices as $invoice) {
            $this->line("  • {$invoice->invoice_number}  patient_id={$invoice->patient_id}  total=₹{$invoice->total_amount}");
        }

        if ($dryRun) {
            $this->warn('Dry-run mode — nothing written.');
            return 0;
        }

        if (! $this->confirm('Proceed with backfill?', true)) {
            $this->info('Aborted.');
            return 0;
        }

        $fixed = 0;

        foreach ($invoices as $invoice) {
            DB::transaction(function () use ($invoice, &$fixed) {
                $amount    = (float) $invoice->total_amount;
                $patientId = $invoice->patient_id;
                $createdBy = $invoice->created_by ?? 1; // fallback to first user

                // 1. InvoicePayment (default cash — operator can edit if needed)
                $payment = InvoicePayment::create([
                    'invoice_id'   => $invoice->id,
                    'patient_id'   => $patientId,
                    'amount'       => $amount,
                    'payment_mode' => 'cash',   // default; edit if needed
                    'payment_date' => $invoice->invoice_date->toDateString(),
                    'notes'        => 'Backfilled — AOCP Membership enrollment',
                    'created_by'   => $createdBy,
                ]);

                // 2. Receipt
                if ($invoice->receipts->isEmpty()) {
                    Receipt::create([
                        'receipt_number'     => Receipt::nextNumber(),
                        'invoice_id'         => $invoice->id,
                        'invoice_payment_id' => $payment->id,
                        'patient_id'         => $patientId,
                        'amount'             => $amount,
                        'payment_mode'       => 'cash',
                        'receipt_date'       => $invoice->invoice_date->toDateString(),
                        'invoice_total'      => $amount,
                        'amount_paid_before' => 0,
                        'balance_after'      => 0,
                        'notes'              => 'Backfilled — AOCP Membership enrollment',
                        'created_by'         => $createdBy,
                    ]);
                }

                // 3. FinalBill
                if (! $invoice->finalBill) {
                    FinalBill::generateFromInvoice($invoice->fresh(), $createdBy);
                }

                // 4. FinanceTransaction
                $alreadyHasTx = \App\Models\FinanceTransaction::where('source_type', InvoicePayment::class)
                    ->where('source_id', $payment->id)
                    ->exists();

                if (! $alreadyHasTx) {
                    \App\Models\FinanceTransaction::create([
                        'type'             => 'income',
                        'direction'        => 'credit',
                        'source_type'      => InvoicePayment::class,
                        'source_id'        => $payment->id,
                        'amount'           => $amount,
                        'net_amount'       => $amount,
                        'payment_mode'     => 'cash',
                        'patient_id'       => $patientId,
                        'status'           => 'active',
                        'transaction_date' => $invoice->invoice_date->toDateString(),
                        'notes'            => 'Backfilled — AOCP Membership enrollment',
                        'created_by'       => $createdBy,
                    ]);
                }

                // 5. Recalculate invoice (now has a payment record, will stay paid)
                $invoice->refresh();
                $invoice->recalculate();

                $fixed++;
                $this->info("  ✓ Fixed {$invoice->invoice_number}");
            });
        }

        $this->info("\nDone. Backfilled {$fixed} invoice(s).");
        $this->warn('Note: payment_mode was set to "cash" by default. Check the billing page if the actual mode was different.');

        return 0;
    }
}
