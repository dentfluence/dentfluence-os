<?php

namespace App\Observers;

use App\Models\Finance\FinanceVendor;
use App\Models\User;

/**
 * UserVendorSyncObserver
 *
 * Mirrors every staff member into finance_vendors (vendor_type = 'staff') so
 * they show up in the "Vendor" dropdown on the Expense form — for petty cash,
 * reimbursements, and other one-off payouts to staff. Recurring monthly
 * salary should still be recorded via Finance > Payroll, not this.
 *
 * The mirrored row's user_id is the sync key: one row per staff member,
 * always up to date with their current name / active status.
 */
class UserVendorSyncObserver
{
    public function saved(User $user): void
    {
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
    }
}
