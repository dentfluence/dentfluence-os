<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\RoleBillingPermission;
use Illuminate\Database\Seeder;

/**
 * Seeds sensible default billing-action permissions per role.
 *
 * Admin is intentionally NOT seeded — Role::billingCan()/billingLimit() give the
 * admin an unlimited bypass in code. Everyone else gets explicit rules here.
 * These are safe to re-run (updateOrCreate) and can be edited later from the UI.
 *
 * Run: php artisan db:seed --class=RoleBillingPermissionSeeder
 */
class RoleBillingPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // role slug => [action_key => [is_allowed, limit_value, limit_type]]
        $matrix = [
            Role::MANAGER => [
                RoleBillingPermission::MANUAL_DISCOUNT    => [true, 15, 'percentage'], // up to 15%
                RoleBillingPermission::WALLET_ADJUSTMENT  => [true, null, null],
                RoleBillingPermission::WALLET_REFUND      => [true, null, null],
                RoleBillingPermission::INVOICE_EDIT       => [true, null, null],
                RoleBillingPermission::ADVANCE_ADJUSTMENT => [true, null, null],
            ],
            Role::DOCTOR => [
                RoleBillingPermission::MANUAL_DISCOUNT    => [true, null, null],       // unlimited
                RoleBillingPermission::WALLET_ADJUSTMENT  => [true, null, null],
                RoleBillingPermission::WALLET_REFUND      => [true, null, null],
                RoleBillingPermission::INVOICE_EDIT       => [true, null, null],
                RoleBillingPermission::ADVANCE_ADJUSTMENT => [true, null, null],
            ],
            Role::ACCOUNTS => [
                RoleBillingPermission::MANUAL_DISCOUNT    => [false, null, null],
                RoleBillingPermission::WALLET_ADJUSTMENT  => [true, null, null],
                RoleBillingPermission::WALLET_REFUND      => [true, null, null],
                RoleBillingPermission::INVOICE_EDIT       => [true, null, null],
                RoleBillingPermission::ADVANCE_ADJUSTMENT => [true, null, null],
            ],
            Role::FRONT_DESK => [
                RoleBillingPermission::MANUAL_DISCOUNT    => [false, null, null],
                RoleBillingPermission::WALLET_ADJUSTMENT  => [false, null, null],
                RoleBillingPermission::WALLET_REFUND      => [false, null, null],
                RoleBillingPermission::INVOICE_EDIT       => [false, null, null],
                RoleBillingPermission::ADVANCE_ADJUSTMENT => [true, null, null], // may accept advances
            ],
            Role::ASSISTANT => [
                RoleBillingPermission::MANUAL_DISCOUNT    => [false, null, null],
                RoleBillingPermission::WALLET_ADJUSTMENT  => [false, null, null],
                RoleBillingPermission::WALLET_REFUND      => [false, null, null],
                RoleBillingPermission::INVOICE_EDIT       => [false, null, null],
                RoleBillingPermission::ADVANCE_ADJUSTMENT => [false, null, null],
            ],
        ];

        foreach ($matrix as $slug => $actions) {
            $role = Role::where('slug', $slug)->first();
            if (! $role) {
                $this->command?->warn("Role '{$slug}' not found — skipped.");
                continue;
            }

            foreach ($actions as $actionKey => [$isAllowed, $limitValue, $limitType]) {
                RoleBillingPermission::updateOrCreate(
                    ['role_id' => $role->id, 'action_key' => $actionKey],
                    ['is_allowed' => $isAllowed, 'limit_value' => $limitValue, 'limit_type' => $limitType],
                );
            }
        }
    }
}
