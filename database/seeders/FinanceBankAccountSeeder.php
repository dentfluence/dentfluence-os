<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds demo bank accounts for clinic_id=1.
 * These accounts feed the payment instrument selector in expense / income forms.
 *
 * Run: php artisan db:seed --class=FinanceBankAccountSeeder
 */
class FinanceBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'account_name'    => 'HDFC Current Account',
                'bank_name'       => 'HDFC Bank',
                'account_number'  => 'XXXXXXXX4521',
                'ifsc_code'       => 'HDFC0001234',
                'branch'          => 'Pune - Kothrud',
                'account_type'    => 'current',
                'opening_balance' => 300000,
                'current_balance' => 285000,
                'is_primary'      => true,
                'upi_id'          => 'clinic@hdfcbank',
                // capabilities: NEFT, UPI, Cheque, Debit Card
                'notes'           => 'Primary operating account. Supports: NEFT, UPI, Cheque, Debit Card',
            ],
            [
                'account_name'    => 'SBI Savings Account',
                'bank_name'       => 'State Bank of India',
                'account_number'  => 'XXXXXXXX8832',
                'ifsc_code'       => 'SBIN0001056',
                'branch'          => 'Pune - Deccan',
                'account_type'    => 'savings',
                'opening_balance' => 50000,
                'current_balance' => 48500,
                'is_primary'      => false,
                'upi_id'          => 'clinic@sbi',
                // capabilities: NEFT, UPI, Cheque
                'notes'           => 'Secondary savings. Supports: NEFT, UPI, Cheque',
            ],
            [
                'account_name'    => 'ICICI OD Account',
                'bank_name'       => 'ICICI Bank',
                'account_number'  => 'XXXXXXXX1190',
                'ifsc_code'       => 'ICIC0002345',
                'branch'          => 'Pune - Aundh',
                'account_type'    => 'od',
                'opening_balance' => 0,
                'current_balance' => -25000,
                'is_primary'      => false,
                'upi_id'          => null,
                // capabilities: NEFT, Cheque only (OD — no UPI typically)
                'notes'           => 'Overdraft account. Supports: NEFT, Cheque',
            ],
            [
                'account_name'    => 'HDFC Credit Card',
                'bank_name'       => 'HDFC Bank',
                'account_number'  => 'XXXXXXXXXXXX9900',
                'ifsc_code'       => null,
                'branch'          => null,
                'account_type'    => 'cc',
                'opening_balance' => 0,
                'current_balance' => 0,
                'is_primary'      => false,
                'upi_id'          => null,
                // capabilities: Card only
                'notes'           => 'Credit card — limit ₹2,00,000. Supports: Credit Card',
            ],
        ];

        foreach ($accounts as $acc) {
            $exists = DB::table('finance_bank_accounts')
                ->where('clinic_id', 1)
                ->where('account_name', $acc['account_name'])
                ->exists();

            if (! $exists) {
                DB::table('finance_bank_accounts')->insert([
                    'clinic_id'        => 1,
                    'account_name'     => $acc['account_name'],
                    'bank_name'        => $acc['bank_name'],
                    'account_number'   => $acc['account_number'],
                    'ifsc_code'        => $acc['ifsc_code'],
                    'branch'           => $acc['branch'],
                    'account_type'     => $acc['account_type'],
                    'opening_balance'  => $acc['opening_balance'],
                    'current_balance'  => $acc['current_balance'],
                    'is_primary'       => $acc['is_primary'],
                    'is_active'        => true,
                    'upi_id'           => $acc['upi_id'],
                    'notes'            => $acc['notes'],
                    'created_by'       => 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        $this->command->info('✅ Bank accounts seeded.');
    }
}
