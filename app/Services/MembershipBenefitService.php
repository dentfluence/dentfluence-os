<?php

namespace App\Services;

use App\Models\Finance\FinancePatientMembership;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\MembershipBenefitLog;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * MembershipBenefitService
 *
 * Calculates what membership benefits apply to a given invoice.
 * Called by BillingController when building or saving an invoice
 * for a patient who has an active AOCP membership.
 *
 * Usage:
 *   $result = MembershipBenefitService::forPatient($patientId, $lineItems, $subtotal);
 *   // $result['discount']       — ₹ amount to deduct
 *   // $result['free_items']     — array of line item labels covered free
 *   // $result['membership_id']  — ID of the FinancePatientMembership record
 *   // $result['summary']        — human-readable string for display
 */
class MembershipBenefitService
{
    /**
     * Get active membership for patient (or null).
     */
    public static function getActive(int $patientId): ?FinancePatientMembership
    {
        return FinancePatientMembership::with('plan')
            ->where('patient_id', $patientId)
            ->active()
            ->latest('start_date')
            ->first();
    }

    /**
     * Calculate benefits to apply on an invoice.
     *
     * @param int   $patientId
     * @param array $lineItems   Each item: ['name' => string, 'amount' => float, 'qty' => int]
     * @param float $subtotal    Total before discounts
     *
     * @return array {
     *   active: bool,
     *   membership_id: int|null,
     *   plan_name: string,
     *   discount: float,         ← ₹ discount to apply
     *   free_items: array,       ← item names covered free (for display)
     *   pct_discount: float,     ← percentage discount (for display)
     *   summary: string,
     *   days_remaining: int,
     * }
     */
    public static function forPatient(int $patientId, array $lineItems = [], float $subtotal = 0): array
    {
        $empty = [
            'active'         => false,
            'membership_id'  => null,
            'plan_name'      => '',
            'discount'       => 0,
            'free_items'     => [],
            'pct_discount'   => 0,
            'summary'        => '',
            'days_remaining' => 0,
            'benefit_config' => null, // raw benefit list for JS client-side recalc
        ];

        $membership = self::getActive($patientId);
        if (!$membership || !$membership->plan) {
            return $empty;
        }

        $plan     = $membership->plan;
        $benefits = $plan->getBenefitList();
        $discount = 0.0;
        $freeItems = [];

        // ------------------------------------------------------------------
        // 1. Free items — match line item names against benefit flags + list
        // ------------------------------------------------------------------
        $freeTriggers = [];
        if ($benefits['free_consultation']) $freeTriggers[] = 'consultation';
        if ($benefits['free_xray'])         $freeTriggers[] = 'x-ray';
        if ($benefits['free_xray'])         $freeTriggers[] = 'xray';
        if ($benefits['free_scaling'])      $freeTriggers[] = 'scaling';

        // Merge explicitly listed free treatments (case-insensitive)
        foreach ($benefits['free_treatments'] as $ft) {
            $freeTriggers[] = strtolower(trim($ft));
        }

        foreach ($lineItems as $item) {
            $nameLower = strtolower($item['name'] ?? '');
            foreach ($freeTriggers as $trigger) {
                if (str_contains($nameLower, $trigger)) {
                    $itemAmount  = (float) ($item['amount'] ?? 0) * (int) ($item['qty'] ?? 1);
                    $discount   += $itemAmount;
                    $freeItems[] = $item['name'];
                    break; // don't double-count same item
                }
            }
        }

        // ------------------------------------------------------------------
        // 2. Percentage discount — applied to remaining subtotal after free
        // ------------------------------------------------------------------
        $pct = (float) ($benefits['discount_percent'] ?? 0);
        if ($pct > 0) {
            $remainingSubtotal = max(0, $subtotal - $discount);
            $pctAmount         = round($remainingSubtotal * ($pct / 100), 2);
            $discount         += $pctAmount;
        }

        // ------------------------------------------------------------------
        // 3. Summary string
        // ------------------------------------------------------------------
        $summaryParts = [];
        if (!empty($freeItems)) {
            $summaryParts[] = 'Free: ' . implode(', ', array_unique($freeItems));
        }
        if ($pct > 0) {
            $summaryParts[] = $pct . '% off remaining items';
        }
        $summary = empty($summaryParts)
            ? 'Membership active — no applicable benefits for this invoice'
            : implode(' + ', $summaryParts);

        return [
            'active'         => true,
            'membership_id'  => $membership->id,
            'plan_name'      => $plan->plan_name,
            'discount'       => round($discount, 2),
            'free_items'     => array_unique($freeItems),
            'pct_discount'   => $pct,
            'summary'        => $summary,
            'days_remaining' => $membership->days_remaining,
            'benefit_config' => $benefits, // raw for JS recalc when items change
        ];
    }

    /**
     * Enroll a patient in a membership plan.
     * Creates the FinancePatientMembership record and returns it.
     * Does NOT handle payment — that's done in BillingController.
     */
    public static function enroll(
        int $patientId,
        int $planId,
        float $amountPaid,
        ?int $createdBy = null,
        string $memberType = 'individual',
        ?int $familyHeadMembershipId = null,
        ?string $familyName = null,
        ?string $startDateInput = null   // optional backdated enrollment date (Y-m-d)
    ): FinancePatientMembership {
        $plan      = FinanceMembershipPlan::findOrFail($planId);
        // Allow backdated entries: use the supplied date if given, else today.
        $startDate = $startDateInput ? Carbon::parse($startDateInput)->startOfDay() : Carbon::today();

        // For add-on members: validity must match the family head's end date
        if ($memberType === 'addon' && $familyHeadMembershipId) {
            $head    = FinancePatientMembership::findOrFail($familyHeadMembershipId);
            $endDate = $head->end_date;
        } else {
            $endDate = match ($plan->duration) {
                'monthly'     => $startDate->copy()->addMonth(),
                'quarterly'   => $startDate->copy()->addMonths(3),
                'half_yearly' => $startDate->copy()->addMonths(6),
                'yearly'      => $startDate->copy()->addYear(),
                default       => $startDate->copy()->addYear(),
            };
        }

        // Cancel any existing active membership first
        FinancePatientMembership::where('patient_id', $patientId)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $record = FinancePatientMembership::create([
            'clinic_id'                  => 1,
            'patient_id'                 => $patientId,
            'plan_id'                    => $plan->id,
            'start_date'                 => $startDate,
            'end_date'                   => $endDate,
            'amount_paid'                => $amountPaid,
            'status'                     => 'active',
            'created_by'                 => $createdBy,
            'member_type'                => $memberType,
            'family_head_membership_id'  => $memberType === 'addon' ? $familyHeadMembershipId : null,
            'family_name'                => $familyName ?: null,
        ]);

        // Sync to Patient model columns so existing badges/filters keep working
        \App\Models\Patient::where('id', $patientId)->update([
            'membership_status'     => 'active',
            'membership_expires_at' => $endDate,
        ]);

        return $record;
    }

    /**
     * Enroll a patient AND create the full finance chain (invoice → item →
     * payment → receipt → final bill → finance transaction), identical to the
     * web BillingController@enrollMembership flow. Wrapped in a transaction so
     * mobile and web produce the same records atomically.
     *
     * Returns ['membership' => FinancePatientMembership, 'invoice' => Invoice].
     */
    public static function enrollWithFinance(
        int $patientId,
        int $planId,
        float $amountPaid,
        string $paymentMode,
        ?int $createdBy = null,
        string $memberType = 'individual',
        ?int $familyHeadMembershipId = null,
        ?string $familyName = null,
        ?string $enrollDate = null
    ): array {
        return \Illuminate\Support\Facades\DB::transaction(function () use (
            $patientId, $planId, $amountPaid, $paymentMode, $createdBy,
            $memberType, $familyHeadMembershipId, $familyName, $enrollDate
        ) {
            $date = $enrollDate ?: now()->toDateString();

            // 1. Enrollment record (+ patient column sync, cancels prior active)
            $membership = self::enroll(
                $patientId, $planId, $amountPaid, $createdBy,
                $memberType, $familyHeadMembershipId, $familyName, $date
            );
            $plan = $membership->plan;

            // 2. Membership invoice (fully paid at enrollment)
            $invoice = \App\Models\Invoice::create([
                'invoice_number' => \App\Models\Invoice::nextNumber(),
                'patient_id'     => $patientId,
                'invoice_date'   => $date,
                'due_date'       => $date,
                'subtotal'       => $amountPaid,
                'discount_pct'   => 0,
                'discount_amount'=> 0,
                'taxable_amount' => $amountPaid,
                'gst_amount'     => 0,
                'total_amount'   => $amountPaid,
                'paid_amount'    => $amountPaid,
                'balance_due'    => 0,
                'status'         => 'paid',
                'membership_id'  => $membership->id,
                'notes'          => 'AOCP Membership — ' . $plan->plan_name,
                'created_by'     => $createdBy,
            ]);

            // 3. Line item
            (new \App\Models\InvoiceItem([
                'invoice_id'  => $invoice->id,
                'description' => 'AOCP Membership: ' . $plan->plan_name,
                'unit_price'  => $amountPaid,
                'qty'         => 1,
                'disc_pct'    => 0,
                'disc_amount' => 0,
                'net_amount'  => $amountPaid,
                'gst_pct'     => 0,
                'gst_amount'  => 0,
                'total'       => $amountPaid,
                'sort_order'  => 1,
            ]))->save();

            // 4. Payment
            $payment = \App\Models\InvoicePayment::create([
                'invoice_id'   => $invoice->id,
                'patient_id'   => $patientId,
                'amount'       => $amountPaid,
                'payment_mode' => $paymentMode,
                'payment_date' => $date,
                'notes'        => 'Membership enrollment — ' . $plan->plan_name,
                'created_by'   => $createdBy,
            ]);

            // 5. Receipt
            \App\Models\Receipt::create([
                'receipt_number'     => \App\Models\Receipt::nextNumber(),
                'invoice_id'         => $invoice->id,
                'invoice_payment_id' => $payment->id,
                'patient_id'         => $patientId,
                'amount'             => $amountPaid,
                'payment_mode'       => $paymentMode,
                'receipt_date'       => $date,
                'invoice_total'      => $amountPaid,
                'amount_paid_before' => 0,
                'balance_after'      => 0,
                'notes'              => 'AOCP Membership — ' . $plan->plan_name,
                'created_by'         => $createdBy,
            ]);

            // 6. Final bill (invoice fully paid)
            \App\Models\FinalBill::generateFromInvoice($invoice, $createdBy);

            // 7. Finance mirror
            \App\Models\Finance\FinanceTransaction::create([
                'type'             => 'income',
                'direction'        => 'credit',
                'source_type'      => \App\Models\InvoicePayment::class,
                'source_id'        => $payment->id,
                'amount'           => $amountPaid,
                'net_amount'       => $amountPaid,
                'payment_mode'     => $paymentMode,
                'patient_id'       => $patientId,
                'status'           => 'active',
                'transaction_date' => $date,
                'notes'            => 'AOCP Membership — ' . $plan->plan_name,
                'created_by'       => $createdBy,
            ]);

            return ['membership' => $membership->fresh('plan'), 'invoice' => $invoice];
        });
    }

    // -------------------------------------------------------------------------
    // Benefit logging
    // -------------------------------------------------------------------------

    /**
     * Log a benefit that was availed on an invoice.
     *
     * Call this from BillingController after saving an invoice
     * that has membership discounts applied.
     *
     * @param int         $patientId
     * @param int         $membershipId
     * @param string      $benefitType   free_consultation|free_xray|free_scaling|free_treatment|pct_discount
     * @param string      $benefitLabel  Human-readable description
     * @param float       $amountSaved   Rs. value saved
     * @param int|null    $invoiceId
     * @param string|null $notes         Extra detail (e.g. treatment name)
     */
    public static function log(
        int $patientId,
        int $membershipId,
        string $benefitType,
        string $benefitLabel,
        float $amountSaved = 0,
        ?int $invoiceId = null,
        ?string $notes = null
    ): MembershipBenefitLog {
        return MembershipBenefitLog::create([
            'clinic_id'     => 1,
            'patient_id'    => $patientId,
            'membership_id' => $membershipId,
            'invoice_id'    => $invoiceId,
            'benefit_type'  => $benefitType,
            'benefit_label' => $benefitLabel,
            'amount_saved'  => $amountSaved,
            'notes'         => $notes,
            'created_by'    => Auth::id(),
            'availed_at'    => now(),
        ]);
    }

    /**
     * Log all benefits from a forPatient() result in one call.
     * Typically called after an invoice is saved.
     *
     * @param array    $benefitResult  Return value of self::forPatient()
     * @param int|null $invoiceId
     */
    public static function logFromResult(array $benefitResult, ?int $invoiceId = null): void
    {
        if (!$benefitResult['active'] || !$benefitResult['membership_id']) {
            return;
        }

        $membershipId = $benefitResult['membership_id'];
        $patientId    = FinancePatientMembership::find($membershipId)?->patient_id;
        if (!$patientId) return;

        // Log each free item individually
        foreach ($benefitResult['free_items'] as $itemName) {
            // Determine benefit type from name
            $nameLow = strtolower($itemName);
            $type = 'free_treatment';
            if (str_contains($nameLow, 'consultation')) $type = 'free_consultation';
            elseif (str_contains($nameLow, 'xray') || str_contains($nameLow, 'x-ray')) $type = 'free_xray';
            elseif (str_contains($nameLow, 'scaling')) $type = 'free_scaling';

            self::log(
                patientId:    $patientId,
                membershipId: $membershipId,
                benefitType:  $type,
                benefitLabel: 'Free: ' . $itemName,
                amountSaved:  0, // individual item amount not tracked here
                invoiceId:    $invoiceId,
                notes:        $itemName,
            );
        }

        // Log percentage discount as single entry if applicable
        if ($benefitResult['pct_discount'] > 0 && $benefitResult['discount'] > 0) {
            self::log(
                patientId:    $patientId,
                membershipId: $membershipId,
                benefitType:  'pct_discount',
                benefitLabel: $benefitResult['pct_discount'] . '% discount — Rs. ' . number_format($benefitResult['discount'], 0) . ' saved',
                amountSaved:  $benefitResult['discount'],
                invoiceId:    $invoiceId,
            );
        }
    }
}
