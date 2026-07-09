<?php

namespace App\Console\Commands;

use App\Models\Finance\FinanceVendor;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * finance:sync-staff-vendors
 *
 * One-off backfill: mirrors every existing user into finance_vendors
 * (vendor_type = 'staff') so they immediately appear in the Expense form's
 * Vendor dropdown. Going forward, UserVendorSyncObserver keeps this in sync
 * automatically on every User save — this command only needs to be run once
 * after the migration, or again if rows ever drift.
 */
class SyncStaffVendors extends Command
{
    protected $signature = 'finance:sync-staff-vendors';

    protected $description = 'Mirror every staff member into finance_vendors (vendor_type=staff) for the Expense form Vendor dropdown.';

    public function handle(): int
    {
        $users = User::all();
        $count = 0;

        foreach ($users as $user) {
            FinanceVendor::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'clinic_id'   => 1,
                    'vendor_name' => $user->name,
                    'vendor_type' => 'staff',
                    'phone'       => $user->phone,
                    'email'       => $user->email,
                    'is_active'   => (bool) $user->is_active,
                ]
            );
            $count++;
        }

        $this->info("Synced {$count} staff member(s) into finance_vendors.");
        return self::SUCCESS;
    }
}
