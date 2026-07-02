<?php

namespace App\Services\Billing;

use App\Models\BillingAuditLog;
use App\Models\Invoice;
use App\Models\RoleBillingPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies / removes a header-level MANUAL discount on an invoice, with full
 * accountability: permission + role limit are enforced, the resolved amount is
 * stored on the invoice, and every action is written to the (hash-chained)
 * billing audit log recording old total, discount, new total, reason and user.
 *
 * This lives alongside the coupon layer — an invoice can carry both a coupon
 * discount and a manual discount at the same time. Invoice::recalculate() already
 * subtracts the stored manual_discount_amount.
 */
class ManualDiscountService
{
    /**
     * Apply a manual discount to an invoice.
     *
     * @param  string  $type    'flat' | 'percentage'
     * @param  float   $value   ₹ amount (flat) or % (percentage)
     * @param  string  $reason  mandatory justification
     * @throws ValidationException  when permission/limit rules are violated
     */
    public function apply(Invoice $invoice, string $type, float $value, string $reason, User $user): Invoice
    {
        if (! in_array($type, ['flat', 'percentage'], true)) {
            throw ValidationException::withMessages(['manual_discount_type' => 'Invalid discount type.']);
        }
        if ($value <= 0) {
            throw ValidationException::withMessages(['manual_discount_value' => 'Discount value must be greater than zero.']);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['manual_discount_reason' => 'A reason is required for a manual discount.']);
        }
        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['invoice' => 'Paid or cancelled invoices cannot be discounted.']);
        }

        // Discount base = items net (pre-tax, after any per-line/header % discount).
        $base = (float) $invoice->subtotal;
        if ($base <= 0) {
            throw ValidationException::withMessages(['invoice' => 'Cannot apply a discount to a zero-value invoice.']);
        }

        // Resolve the rupee amount actually deducted.
        $amount = $type === 'percentage'
            ? round($base * $value / 100, 2)
            : round(min($value, $base), 2); // a flat discount can never exceed the bill

        // Effective percentage — used for limit checks regardless of entry type.
        $effectivePct = round($amount / $base * 100, 2);

        $this->assertAllowed($user, $type, $value, $amount, $effectivePct);

        return DB::transaction(function () use ($invoice, $type, $value, $amount, $reason, $user) {
            $oldTotal = (float) $invoice->total_amount;

            $invoice->update([
                'manual_discount_type'          => $type,
                'manual_discount_value'         => $value,
                'manual_discount_amount'        => $amount,
                'manual_discount_reason'        => $reason,
                'manual_discount_authorized_by' => $user->id,
                'manual_discount_applied_by'    => $user->id,
                'manual_discount_at'            => now(),
            ]);

            $invoice->recalculate();
            $invoice->refresh();

            BillingAuditLog::record(
                'apply_manual_discount',
                $invoice,
                sprintf(
                    'Manual %s discount %s → Rs. %s off. Total Rs. %s → Rs. %s. Reason: %s',
                    $type,
                    $type === 'percentage' ? $value . '%' : 'Rs. ' . number_format($value, 2),
                    number_format($amount, 2),
                    number_format($oldTotal, 2),
                    number_format((float) $invoice->total_amount, 2),
                    $reason
                ),
                $user->id,
                $invoice->invoice_number
            );

            return $invoice;
        });
    }

    /**
     * Remove an existing manual discount (also permission-gated & audited).
     */
    public function remove(Invoice $invoice, User $user, string $reason = 'Manual discount removed'): Invoice
    {
        if (in_array($invoice->status, ['paid', 'cancelled'], true)) {
            throw ValidationException::withMessages(['invoice' => 'Paid or cancelled invoices cannot be changed.']);
        }
        // Removing requires the same permission as applying.
        $this->assertAllowed($user, 'flat', 0, 0, 0, /* removing */ true);

        return DB::transaction(function () use ($invoice, $user, $reason) {
            $oldAmount = (float) $invoice->manual_discount_amount;

            $invoice->update([
                'manual_discount_type'          => null,
                'manual_discount_value'         => 0,
                'manual_discount_amount'        => 0,
                'manual_discount_reason'        => null,
                'manual_discount_authorized_by' => null,
                'manual_discount_applied_by'    => null,
                'manual_discount_at'            => null,
            ]);

            $invoice->recalculate();
            $invoice->refresh();

            BillingAuditLog::record(
                'remove_manual_discount',
                $invoice,
                sprintf('Removed manual discount of Rs. %s. %s', number_format($oldAmount, 2), $reason),
                $user->id,
                $invoice->invoice_number
            );

            return $invoice;
        });
    }

    /**
     * Enforce role permission + numeric limit. Admin is always allowed & unlimited.
     * Throws ValidationException when the user isn't permitted or exceeds their cap.
     */
    private function assertAllowed(User $user, string $type, float $value, float $amount, float $effectivePct, bool $removing = false): void
    {
        // Admin (either system) bypasses all limits.
        if ($user->isAdminRole()) {
            return;
        }

        $role = $user->roleModel;
        if (! $role || ! $role->billingCan(RoleBillingPermission::MANUAL_DISCOUNT)) {
            throw ValidationException::withMessages([
                'manual_discount' => 'You do not have permission to apply manual discounts.',
            ]);
        }

        if ($removing) {
            return; // permission confirmed; nothing numeric to check
        }

        $limit = $role->billingLimit(RoleBillingPermission::MANUAL_DISCOUNT);
        // null value = unlimited within the allowed role
        if ($limit['value'] === null) {
            return;
        }

        if ($limit['type'] === 'percentage' && $effectivePct > (float) $limit['value']) {
            throw ValidationException::withMessages([
                'manual_discount_value' => 'Discount exceeds your limit of ' . rtrim(rtrim(number_format($limit['value'], 2), '0'), '.') . '%.',
            ]);
        }

        if ($limit['type'] === 'flat' && $amount > (float) $limit['value']) {
            throw ValidationException::withMessages([
                'manual_discount_value' => 'Discount exceeds your limit of Rs. ' . number_format($limit['value'], 2) . '.',
            ]);
        }
    }
}
