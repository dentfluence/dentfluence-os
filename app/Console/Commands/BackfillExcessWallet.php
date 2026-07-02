<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Console\Command;

/**
 * One-time backfill: convert historic OVERPAYMENTS into wallet credit.
 *
 * Chunk 3 makes excess payments auto-credit the wallet at payment time, but any
 * invoice that was overpaid BEFORE that feature went live still shows as
 * "over 100% collected" with the surplus uncredited. This command finds those
 * invoices and credits the difference.
 *
 * Idempotent: it skips any invoice whose excess was already credited (detected by
 * the "Excess payment from {invoice_number}" wallet note), so it is safe to re-run.
 *
 * Usage:
 *   php artisan billing:backfill-excess-wallet            (dry run — shows what it would do)
 *   php artisan billing:backfill-excess-wallet --apply    (actually credit the wallets)
 */
class BackfillExcessWallet extends Command
{
    protected $signature = 'billing:backfill-excess-wallet {--apply : Actually write wallet credits (otherwise dry-run)}';

    protected $description = 'Credit historic invoice overpayments to patient wallets (excess = paid - total).';

    public function handle(WalletService $walletService): int
    {
        $apply = (bool) $this->option('apply');

        // Overpaid, non-cancelled invoices.
        $invoices = Invoice::whereColumn('paid_amount', '>', 'total_amount')
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No overpaid invoices found. Nothing to do.');
            return self::SUCCESS;
        }

        $count = 0;
        $total = 0.0;

        foreach ($invoices as $invoice) {
            $excess = round((float) $invoice->paid_amount - (float) $invoice->total_amount, 2);
            if ($excess < 0.01) {
                continue;
            }

            $note = 'Excess payment from ' . $invoice->invoice_number;

            // Skip if this excess was already credited (idempotency guard).
            $already = WalletTransaction::where('patient_id', $invoice->patient_id)
                ->where('notes', $note)
                ->exists();

            if ($already) {
                $this->line("• {$invoice->invoice_number}: already credited — skipped.");
                continue;
            }

            $this->line("• {$invoice->invoice_number}: patient #{$invoice->patient_id} excess Rs. " . number_format($excess, 2)
                . ($apply ? ' — CREDITING' : ' — would credit'));

            if ($apply) {
                $walletService->deposit(
                    patientId:   $invoice->patient_id,
                    amount:      $excess,
                    paymentMode: 'adjustment',
                    notes:       $note,
                    createdBy:   null,
                    source:      'advance',
                );
            }

            $count++;
            $total += $excess;
        }

        $this->newLine();
        if ($apply) {
            $this->info("Done. Credited {$count} invoice(s), total Rs. " . number_format($total, 2) . ' to wallets.');
        } else {
            $this->warn("Dry run: {$count} invoice(s) would be credited, total Rs. " . number_format($total, 2) . ".");
            $this->warn('Re-run with --apply to actually write the credits.');
        }

        return self::SUCCESS;
    }
}
