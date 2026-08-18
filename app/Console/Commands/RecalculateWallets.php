<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;

/**
 * Re-sync cached wallet balances with the transaction ledger.
 *
 * WHY THIS EXISTS. Promotional credit expires with the CALENDAR, not with a
 * transaction. wallets.balance_promotional / balance_total are cached columns
 * only refreshed by Wallet::recalculate(), and Wallet::forPatient() self-heals
 * a single wallet when someone opens it. Nothing heals a wallet nobody opens —
 * so list screens, KPI totals and ORDER BY (which all read the cached columns
 * directly, across thousands of rows) keep quoting credit that lapsed months
 * ago, while the patient's own ledger page shows the correct, lower figure.
 *
 * That is exactly the mismatch reported on 2026-08-17: the Individual Credit
 * list showed Rs. 44,000 promotional for a patient whose ledger showed
 * Rs. 25,000, because Rs. 19,000 of it expired on 31 Jul.
 *
 * This changes NO ledger row. It only recomputes derived columns from
 * transactions that already exist, so it is safe to re-run at any time.
 *
 *   php artisan wallet:recalculate            # only wallets that can be wrong
 *   php artisan wallet:recalculate --all      # every wallet
 *   php artisan wallet:recalculate --dry-run  # report, change nothing
 */
class RecalculateWallets extends Command
{
    protected $signature = 'wallet:recalculate
                            {--all : Recalculate every wallet, not just stale ones}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Re-sync cached wallet balances with the ledger (drops lapsed promotional credit)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Wallet::query();

        if (! $this->option('all')) {
            // Narrow to wallets that CAN be wrong: they still show promotional
            // credit, and they hold at least one promotional credit that has
            // since lapsed. Everything else is already correct.
            $query->where('balance_promotional', '>', 0)
                ->whereIn('id', WalletTransaction::query()
                    ->select('wallet_id')
                    ->where('direction', 'credit')
                    ->where('credit_type', 'promotional')
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '<', today()));
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to do — no wallet is holding lapsed promotional credit.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Checking ' : 'Recalculating ') . $total . ' wallet(s)…');
        $bar = $this->output->createProgressBar($total);

        $changed      = 0;
        $promoDropped = 0.0;

        $query->chunkById(200, function ($wallets) use (&$changed, &$promoDropped, $dryRun, $bar) {
            foreach ($wallets as $wallet) {
                $before = (float) $wallet->balance_promotional;
                $after  = $wallet->availablePromotionalCredit();

                if (abs($before - $after) > 0.009) {
                    $changed++;
                    $promoDropped += ($before - $after);

                    if (! $dryRun) {
                        $wallet->recalculate();
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->line('  Wallets scanned:        ' . $total);
        $this->line('  Wallets ' . ($dryRun ? 'that would change' : 'corrected') . ': ' . $changed);
        $this->line('  Lapsed promotional credit removed from balances: Rs. '
            . number_format($promoDropped, 2));

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }
}
