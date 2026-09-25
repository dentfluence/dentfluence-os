<?php

namespace App\Services;

use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Finance\FinanceVendor;
use App\Models\LabCase;

/**
 * LabExpenseService — Expense Module integration.
 *
 * When a lab case is marked Received (or its payment marked Paid),
 * automatically creates a FinanceExpense entry — once.
 * lab_cases.expense_id is the duplicate guard: no double data entry.
 */
class LabExpenseService
{
    /**
     * Create the linked expense for a case (idempotent).
     * Returns null when there is nothing to record (no cost).
     */
    public function createForCase(LabCase $case): ?FinanceExpense
    {
        // Duplicate guard — expense already linked
        if ($case->expense_id) {
            return $case->expense;
        }

        // Nothing to record without a cost
        if (!$case->lab_cost || (float) $case->lab_cost <= 0) {
            return null;
        }

        $category = $this->labCategory();
        $vendorId = $this->resolveFinanceVendor($case);

        $expense = FinanceExpense::create([
            'category_id'  => $category->id,
            'vendor_id'    => $vendorId,
            'title'        => "Lab: {$case->case_number} — " . ($case->patient?->name ?? 'Patient'),
            'description'  => $this->buildDescription($case),
            'expense_date' => optional($case->received_date)->toDateString() ?? now()->toDateString(),
            'amount'       => $case->lab_cost,
            'gst_applicable' => false,
            'gst_rate'     => 0,
            'gst_amount'   => 0,
            'total_amount' => $case->lab_cost,
            'payment_mode' => 'other',
            // CEO ruling 6 Sep: "lab, consultant, material — sagle variable
            // expenses aahet, te vaja jhale pahijet PAID kelyavar."
            // finance_expenses.payment_status DEFAULTS TO 'paid', so leaving
            // this key out silently deducted every lab bill the moment the
            // case came back, cash unpaid. Inventory already writes 'unpaid'
            // explicitly; lab did not. Most lab work here runs on a monthly
            // account (see the note below), so it is genuinely unpaid for
            // weeks. Marking it paid is the finance module's job, not this
            // service's.
            'payment_status' => 'unpaid',
            'status'       => 'approved',
            'notes'        => $case->payment_status === 'monthly_account'
                                ? 'Monthly lab account — settle with vendor statement.'
                                : null,
            'created_by'   => auth()->id(),
        ]);

        // Link back (quietly — no extra "updated" noise on the case)
        $case->expense_id = $expense->id;
        $case->saveQuietly();

        $case->logEvent('expense_linked', "Expense entry created (₹{$case->lab_cost})", [
            'meta' => ['expense_id' => $expense->id, 'amount' => (string) $case->lab_cost],
        ]);

        return $expense;
    }

    /**
     * INT-19 (security audit 24 Sep 2026) — a lab case must be expensed once.
     *
     * Every case already gets its own unpaid expense when it comes back
     * (createForCase). Approving a monthly reconciliation then booked the
     * whole vendor bill AGAIN, so the same crown sat in Finance twice.
     *
     * The monthly bill is the real payable, so it wins: per-case expenses of
     * the included cases that are still UNPAID are cancelled (kept, marked
     * cancelled/void, with the reason). Ones already PAID stay — that money
     * really left — and their total is returned so the caller books only the
     * remainder of the agreed amount.
     *
     * @param  iterable<LabCase>  $cases
     * @return float  amount already paid through per-case expenses
     */
    public function supersedeCaseExpenses(iterable $cases, string $billRef): float
    {
        $alreadyPaid = 0.0;

        foreach ($cases as $case) {
            $expense = $case->expense_id ? FinanceExpense::find($case->expense_id) : null;
            if (! $expense || $expense->status === 'cancelled') {
                continue;
            }

            if ($expense->payment_status === 'paid') {
                $alreadyPaid += (float) $expense->total_amount;
                continue;
            }

            $expense->update([
                'status'         => 'cancelled',
                'payment_status' => 'void',
                'notes'          => trim(($expense->notes ? $expense->notes . ' ' : '') . 'Superseded by monthly lab bill ' . $billRef . '.'),
            ]);
        }

        return round($alreadyPaid, 2);
    }

    /** "Lab Charges" expense category — created on first use */
    protected function labCategory(): FinanceExpenseCategory
    {
        return FinanceExpenseCategory::firstOrCreate(
            ['slug' => 'lab-charges'],
            ['name' => 'Lab Charges', 'is_active' => true, 'is_system' => false]
        );
    }

    /**
     * Find (or create) the FinanceVendor for the case's lab vendor,
     * and remember the link on lab_vendors.finance_vendor_id.
     */
    protected function resolveFinanceVendor(LabCase $case): ?int
    {
        $labVendor = $case->vendor;
        if (!$labVendor) {
            return null;
        }

        if ($labVendor->finance_vendor_id) {
            return $labVendor->finance_vendor_id;
        }

        $financeVendor = FinanceVendor::firstOrCreate(
            ['vendor_name' => $labVendor->name],
            [
                'vendor_type' => 'lab',
                'phone'       => $labVendor->phone,
                'email'       => $labVendor->email,
                'is_active'   => true,
                'created_by'  => auth()->id(),
            ]
        );

        $labVendor->finance_vendor_id = $financeVendor->id;
        $labVendor->saveQuietly();

        return $financeVendor->id;
    }

    /** Structured description so Finance shows full context */
    protected function buildDescription(LabCase $case): string
    {
        $lines = [
            'Lab Case: ' . $case->case_number,
            'Patient: ' . ($case->patient?->name ?? '—'),
            'Doctor: ' . ($case->doctor?->name ?? '—'),
            'Work: ' . trim($case->work_category . ($case->work_subtype ? " — {$case->work_subtype}" : '')),
            'Vendor: ' . ($case->vendor?->name ?? '—'),
            'Payment: ' . str_replace('_', ' ', $case->payment_status),
        ];

        return implode("\n", $lines);
    }
}
