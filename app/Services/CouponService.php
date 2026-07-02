<?php

namespace App\Services;

use App\Models\CouponCode;
use App\Models\CouponUsage;

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
     */
    public function apply(int $couponId, int $patientId, int $invoiceId, float $discountAmount, ?int $createdBy = null): void
    {
        CouponUsage::create([
            'coupon_code_id'   => $couponId,
            'patient_id'       => $patientId,
            'invoice_id'       => $invoiceId,
            'discount_applied' => $discountAmount,
            'used_at'          => now(),
            'created_by'       => $createdBy,
        ]);

        CouponCode::where('id', $couponId)->increment('uses_count');
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
