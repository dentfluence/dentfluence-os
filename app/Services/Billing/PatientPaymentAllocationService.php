<?php

namespace App\Services\Billing;

use App\Models\FinalBill;
use App\Models\Finance\FinanceBankAccount;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Relationship\ActivityEngine;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * PatientPaymentAllocationService
 * -------------------------------
 * ONE patient tender, allocated automatically across that patient's open
 * invoices oldest-first, with any surplus becoming Patient Credit.
 *
 * Worked example (the case this was built for):
 *
 *   Invoice A  1,000  paid 600  -> 400 outstanding   (older)
 *   Invoice B  1,500  paid   0  -> 1,500 outstanding (newer)
 *   Patient tenders 2,500.
 *
 *     400 -> Invoice A   (revenue)
 *   1,500 -> Invoice B   (revenue)
 *     600 -> Patient Credit (liability, NOT revenue)
 *
 * Design rules this obeys, and why:
 *
 *  - It sits ABOVE the trusted per-invoice primitives; it does not replace or
 *    reinterpret them. Revenue recognition is still "money that actually
 *    settled an invoice", exactly as G1 established, and the surplus still
 *    travels the U8 advance path (liability + wallet credit). Nothing here
 *    redefines Patient Credit, and promotional credit is never touched.
 *
 *  - InvoicePayment keeps its single invoice_id. One payment row per invoice is
 *    what an auditor expects to see, and it keeps every existing per-invoice
 *    report correct without modification.
 *
 *  - The PATIENT gets exactly ONE receipt (PAY- series) for the whole tender,
 *    carrying an allocation breakdown, because the patient performed one
 *    payment. Handing over three slips for one payment is not a receipt, it is
 *    a reconciliation exercise.
 *
 *  - Simple tenders only. EMI is explicitly rejected: an EMI schedule spanning
 *    several invoices has no meaning. Credit-card convenience fee is likewise
 *    out of scope here - the fee is computed per transaction on the full
 *    amount, and there is no defensible rule for splitting it across invoices,
 *    so rather than invent one this path records no fee at all.
 *
 * The whole allocation runs in ONE transaction: either every invoice moves and
 * the receipt exists, or nothing happened.
 */
class PatientPaymentAllocationService
{
    /**
     * Tenders accepted in allocation mode.
     *
     * This is the INTERSECTION of the payment_mode enums on invoice_payments,
     * receipts and finance_transactions. 'debit_card' and 'netbanking' are
     * valid on the first two but missing from finance_transactions, so they are
     * deliberately excluded here rather than allowed through to fail (or worse,
     * silently truncate) on the finance mirror. 'emi' and 'wallet' are excluded
     * by design, not by enum.
     */
    public const ALLOWED_MODES = ['cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other'];

    /** Money below this is rounding dust, not a payment. */
    private const EPSILON = 0.01;

    /**
     * @param  array{amount:float|string, payment_mode:string, payment_date:string,
     *               reference_no?:?string, notes?:?string, clinic_account_id?:?int,
     *               bank_name?:?string, cheque_no?:?string, cheque_date?:?string}  $in
     *
     * @return array{receipt: Receipt, allocations: array<int,array<string,mixed>>,
     *               settled: float, surplus: float, tender: float,
     *               outstanding_after: float}
     */
    public function allocate(Patient $patient, array $in, ?int $userId = null): array
    {
        $tender = round((float) ($in['amount'] ?? 0), 2);
        $mode   = (string) ($in['payment_mode'] ?? '');
        $date   = (string) ($in['payment_date'] ?? now()->toDateString());

        if ($tender < self::EPSILON) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a payment amount greater than zero.',
            ]);
        }

        if (! in_array($mode, self::ALLOWED_MODES, true)) {
            throw ValidationException::withMessages([
                'payment_mode' => 'This payment mode is not available for a combined patient payment. '
                                . 'EMI and card-with-convenience-fee payments must be recorded against a single invoice.',
            ]);
        }

        return DB::transaction(function () use ($patient, $in, $userId, $tender, $mode, $date) {

            // ── Which invoices are open? ─────────────────────────────────────
            // Filtered on balance_due, NEVER on a status list. Invoice::
            // deriveStatus() returns 'draft' whenever paid <= 0, so an untouched
            // unpaid invoice has status 'draft' — 'draft' here means UNPAID, not
            // unissued. (Confirmed in code: FinanceController maps its "Unpaid"
            // filter to where('invoices.status','draft'), and the quick-pay modal
            // labels a 'draft' invoice "Unpaid". There is no issue/send step and
            // no invoice is ever written with status 'unpaid'.) Filtering on a
            // status list would silently skip invoices.
            //
            // lockForUpdate serialises concurrent tenders for the same patient,
            // so two staff members cannot both allocate against the same balance.
            $invoices = Invoice::where('patient_id', $patient->id)
                ->where('status', '!=', 'cancelled')
                ->where('balance_due', '>', 0)
                ->orderBy('invoice_date')   // oldest first
                ->orderBy('id')             // deterministic tiebreak on same date
                ->lockForUpdate()
                ->get();

            $clinicAccountName = null;
            if (! empty($in['clinic_account_id'])) {
                $clinicAccountName = FinanceBankAccount::find($in['clinic_account_id'])?->account_name;
            }

            $actor       = $userId ? User::find($userId) : null;
            $remaining   = $tender;
            $allocations = [];

            foreach ($invoices as $invoice) {
                if ($remaining < self::EPSILON) {
                    break;
                }

                $take = round(min($remaining, (float) $invoice->balance_due), 2);
                if ($take < self::EPSILON) {
                    continue;
                }

                $paidBefore = (float) $invoice->paid_amount;

                // 1. Payment row — one per invoice, single invoice_id preserved.
                $payment = InvoicePayment::create([
                    'invoice_id'          => $invoice->id,
                    'patient_id'          => $invoice->patient_id,
                    'amount'              => $take,
                    'payment_mode'        => $mode,
                    'payment_date'        => $date,
                    'reference_no'        => $in['reference_no'] ?? null,
                    'notes'               => trim('Allocated from patient payment. ' . ($in['notes'] ?? '')),
                    'created_by'          => $userId,
                    'clinic_account_id'   => $in['clinic_account_id'] ?? null,
                    'clinic_account_name' => $clinicAccountName,
                    'bank_name'           => $in['bank_name'] ?? null,
                    'cheque_no'           => $in['cheque_no'] ?? null,
                    'cheque_date'         => $in['cheque_date'] ?? null,
                    'cheque_status'       => $mode === 'cheque' ? 'pending' : null,
                    'convenience_fee'     => 0,
                ]);

                // 2. Invoice totals + status.
                $invoice->recalculate();
                $invoice->refresh();

                // 3. Final bill on full settlement — same trigger as recordPayment.
                //    generateFromInvoice() types $userId as a non-nullable int, so
                //    guard rather than hand it a null. Every real caller (the
                //    controller) supplies auth()->id(); the guard only exists so a
                //    future headless caller fails soft instead of fatally.
                if ($invoice->isFullyPaid() && ! $invoice->hasFinalBill() && $userId !== null) {
                    FinalBill::generateFromInvoice($invoice, $userId);
                }

                // 4. Finance mirror. Revenue is the SETTLED amount only; the
                //    surplus never reaches this branch.
                FinanceTransaction::create([
                    'type'              => 'income',
                    'direction'         => 'credit',
                    'source_type'       => InvoicePayment::class,
                    'source_id'         => $payment->id,
                    'amount'            => $take,
                    'net_amount'        => $take,
                    'payment_mode'      => $mode,
                    'payment_reference' => $in['reference_no'] ?? null,
                    'patient_id'        => $invoice->patient_id,
                    'status'            => 'active',
                    'transaction_date'  => $date,
                    'notes'             => 'Allocated from combined patient payment',
                    'created_by'        => $userId,
                ]);

                app(ActivityEngine::class)->log(
                    subject:        $payment,
                    event:          'payment.received',
                    actor:          $actor,
                    metadata:       [
                        'patient_id' => $invoice->patient_id,
                        'invoice_id' => $invoice->id,
                        'amount'     => $take,
                        'allocated'  => true,
                    ],
                    relationshipId: $patient->relationship_id,
                    description:    'Payment allocated to invoice ' . $invoice->invoice_number,
                );

                $allocations[] = [
                    'invoice_id'     => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date'   => optional($invoice->invoice_date)->format('Y-m-d'),
                    'amount'         => $take,
                    'paid_before'    => round($paidBefore, 2),
                    'balance_after'  => round((float) $invoice->balance_due, 2),
                ];

                $remaining = round($remaining - $take, 2);
            }

            $settled = round(array_sum(array_column($allocations, 'amount')), 2);
            $surplus = round($remaining, 2);

            // ── Surplus -> Patient Credit, via the untouched U8 advance path ──
            // Liability, never revenue. createReceipt: false because the single
            // PAY- receipt below already covers this leg of the same tender.
            if ($surplus >= self::EPSILON) {
                app(WalletService::class)->receiveAdvance(
                    patient:       $patient,
                    amount:        $surplus,
                    paymentMode:   $mode,
                    paymentDate:   $date,
                    notes:         'Surplus from combined patient payment',
                    createdBy:     $userId,
                    createReceipt: false,
                );
            } else {
                $surplus = 0.0;
            }

            $outstandingAfter = round((float) Invoice::where('patient_id', $patient->id)
                ->where('status', '!=', 'cancelled')
                ->sum('balance_due'), 2);

            // ── ONE receipt for ONE tender ───────────────────────────────────
            // invoice_id / invoice_payment_id stay null: this document belongs to
            // the patient and the payment, not to any single invoice. The
            // breakdown is an immutable snapshot — invoice balances keep moving
            // after today, a receipt must not.
            $receipt = Receipt::create([
                'receipt_number'       => Receipt::nextAllocationNumber(),
                'receipt_kind'         => 'payment',
                'invoice_id'           => null,
                'invoice_payment_id'   => null,
                'patient_id'           => $patient->id,
                'amount'               => $tender,
                'payment_mode'         => $mode,
                'receipt_date'         => $date,
                'reference_no'         => $in['reference_no'] ?? null,
                'invoice_total'        => $settled,
                'amount_paid_before'   => 0,
                'balance_after'        => $outstandingAfter,
                'notes'                => $in['notes'] ?? null,
                'allocation_breakdown' => [
                    'tender'            => $tender,
                    'settled'           => $settled,
                    'patient_credit'    => $surplus,
                    'outstanding_after' => $outstandingAfter,
                    'invoices'          => $allocations,
                ],
                'created_by'           => $userId,
            ]);

            return [
                'receipt'           => $receipt,
                'allocations'       => $allocations,
                'settled'           => $settled,
                'surplus'           => $surplus,
                'tender'            => $tender,
                'outstanding_after' => $outstandingAfter,
            ];
        });
    }

    /** Total open balance for a patient — the number staff sees before paying. */
    public function outstandingFor(Patient $patient): float
    {
        return round((float) Invoice::where('patient_id', $patient->id)
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->sum('balance_due'), 2);
    }
}
