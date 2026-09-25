<?php

namespace App\Services;

use App\Models\CouponCode;
use App\Models\CouponUsage;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validate a coupon code for a patient + invoice subtotal.
     * Returns array with 'valid', 'error' (if invalid), or coupon details.
     */
    public function validate(string $code, int $patientId, float $subtotal): array
    {
        $coupon = CouponCode::active()
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (!$coupon) {
            return ['valid' => false, 'error' => 'Invalid or expired coupon code.'];
        }

        if (!$coupon->canBeUsedByPatient($patientId)) {
            return ['valid' => false, 'error' => 'This coupon has already been used the maximum number of times for this patient.'];
        }

        if ($subtotal < $coupon->min_invoice_amount) {
            return [
                'valid' => false,
                'error' => 'Minimum invoice amount for this coupon is ₹' . number_format($coupon->min_invoice_amount, 0) . '.',
            ];
        }

        $discountAmount = $coupon->calculateDiscount($subtotal);

        return [
            'valid'          => true,
            'coupon_id'      => $coupon->id,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'discount_amount' => $discountAmount,
            'label'          => $coupon->discountLabel(),
            'description'    => $coupon->description,
        ];
    }

    /**
     * Apply coupon to an invoice: record usage + increment counter.
     * Call this inside a DB transaction after invoice is created.
     *
     * INT-05 (security audit 24 Sep 2026): the limits used to be checked
     * before the invoice transaction and never again, with no lock, so two
     * quick saves could both pass a "1 use" coupon. The coupon row is now
     * locked and both limits re-checked here; one invoice records one usage.
     */
    public function apply(int $couponId, int $patientId, int $invoiceId, float $discountAmount, ?int $createdBy = null): void
    {
        $coupon = CouponCode::whereKey($couponId)->lockForUpdate()->firstOrFail();

        if (CouponUsage::where('coupon_code_id', $couponId)->where('invoice_id', $invoiceId)->exists()) {
            return; // already recorded for this invoice
        }

        if ($coupon->max_uses_global > 0 && $coupon->uses_count >= $coupon->max_uses_global) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has reached its usage limit.']);
        }
        if ($coupon->max_uses_per_patient > 0
            && CouponUsage::where('coupon_code_id', $couponId)->where('patient_id', $patientId)->count() >= $coupon->max_uses_per_patient) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon has already been used the maximum number of times for this patient.']);
        }

        CouponUsage::create([
            'coupon_code_id'   => $couponId,
            'patient_id'       => $patientId,
            'invoice_id'       => $invoiceId,
            'discount_applied' => $discountAmount,
            'used_at'          => now(),
            'created_by'       => $createdBy,
        ]);

        $coupon->increment('uses_count');
    }

    /**
     * INT-05 — give the coupon back when its invoice is cancelled or deleted,
     * so the patient (and the global limit) can use it again.
     */
    public function releaseForInvoice(int $invoiceId): void
    {
        foreach (CouponUsage::where('invoice_id', $invoiceId)->get() as $usage) {
            $coupon = CouponCode::whereKey($usage->coupon_code_id)->lockForUpdate()->first();
            $usage->delete();
            if ($coupon && $coupon->uses_count > 0) {
                $coupon->decrement('uses_count');
            }
        }
    }

    /**
     * Resolve coupon from a billing form request.
     * Returns ['coupon_id' => int|null, 'coupon_discount' => float].
     */
    public function resolveFromRequest(
        ?string $code,
        int     $patientId,
        float   $subtotal
    ): array {
        if (!$code) {
            return ['coupon_id' => null, 'coupon_discount' => 0.0];
        }

        $result = $this->validate($code, $patientId, $subtotal);

        if (!$result['valid']) {
            return ['coupon_id' => null, 'coupon_discount' => 0.0];
        }

        return [
            'coupon_id'      => $result['coupon_id'],
            'coupon_discount' => $result['discount_amount'],
        ];
    }
}
