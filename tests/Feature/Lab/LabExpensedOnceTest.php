<?php

namespace Tests\Feature\Lab;

use App\Models\Finance\FinanceExpense;
use App\Models\LabCase;
use App\Models\Patient;
use App\Services\LabExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * INT-19 (security audit 24 Sep 2026) — a lab case was expensed when it came
 * back AND again inside the approved monthly bill. The monthly bill now
 * supersedes the per-case expenses that are still unpaid; paid ones stay and
 * are subtracted from what the bill books.
 */
class LabExpensedOnceTest extends TestCase
{
    use RefreshDatabase;

    private function expensedCase(float $cost): LabCase
    {
        $patient = Patient::create(['name' => 'Lab Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
        $case = LabCase::create([
            'patient_id' => $patient->id, 'work_category' => 'Crown & Bridge',
            'status' => 'order_placed', 'branch_id' => 1, 'lab_cost' => $cost,
        ]);
        app(LabExpenseService::class)->createForCase($case);

        return $case->fresh();
    }

    public function test_unpaid_case_expenses_are_superseded_and_paid_ones_are_counted(): void
    {
        $unpaid = $this->expensedCase(3000);
        $paid   = $this->expensedCase(2000);
        FinanceExpense::whereKey($paid->expense_id)->update(['payment_status' => 'paid']);

        $alreadyPaid = app(LabExpenseService::class)->supersedeCaseExpenses([$unpaid, $paid], 'LR-TEST');

        $this->assertEquals(2000.0, $alreadyPaid);
        $this->assertDatabaseHas('finance_expenses', ['id' => $unpaid->expense_id, 'status' => 'cancelled', 'payment_status' => 'void']);
        $this->assertDatabaseHas('finance_expenses', ['id' => $paid->expense_id, 'status' => 'approved', 'payment_status' => 'paid']);
    }

    public function test_running_it_twice_changes_nothing_more(): void
    {
        $case = $this->expensedCase(1500);
        $svc  = app(LabExpenseService::class);

        $svc->supersedeCaseExpenses([$case], 'LR-1');
        $this->assertEquals(0.0, $svc->supersedeCaseExpenses([$case], 'LR-1'));
        $this->assertSame(1, FinanceExpense::count());
        $this->assertDatabaseHas('finance_expenses', ['id' => $case->expense_id, 'status' => 'cancelled']);
    }
}
