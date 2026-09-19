<?php

namespace Tests\Feature\Billing;

use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinancePatientMembership;
use App\Models\Patient;
use App\Services\MembershipBenefitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AOCP membership benefits are judged AS OF THE INVOICE DATE, never as of today.
 *
 * The defect this locks: a bill dated 11 Aug for a patient who only enrolled on
 * 13 Aug was receiving the membership discount, because the service resolved
 * the membership with Carbon::today() and ignored invoice_date entirely.
 *
 * Both directions matter:
 *   - a membership that had NOT started yet on the bill date must not apply;
 *   - a membership that HAD lapsed by today must still apply to a bill dated
 *     inside its term.
 */
class MembershipAsOfDateTest extends TestCase
{
    use RefreshDatabase;

    private function patient(string $name = 'AsOf Patient'): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    /** A plan that gives a flat 20% off everything. */
    private function plan(): FinanceMembershipPlan
    {
        return FinanceMembershipPlan::create([
            'clinic_id'           => 1,
            'plan_name'           => 'AOCP Test',
            'price'               => 1000,
            'duration'            => 'yearly', // enum column, not a day count
            'benefits'            => ['discount_percent' => 20],
            'discount_percentage' => 20,
            'is_active'           => true,
        ]);
    }

    private function enroll(
        Patient $patient,
        FinanceMembershipPlan $plan,
        string $start,
        string $end,
        string $status = 'active'
    ): FinancePatientMembership {
        return FinancePatientMembership::create([
            'clinic_id'   => 1,
            'patient_id'  => $patient->id,
            'plan_id'     => $plan->id,
            'start_date'  => $start,
            'end_date'    => $end,
            'amount_paid' => 1000,
            'status'      => $status,
        ]);
    }

    private function items(): array
    {
        return [['name' => 'Composite Filling', 'amount' => 1000, 'qty' => 1]];
    }

    public function test_A_membership_not_yet_started_gives_no_benefit_on_a_backdated_bill(): void
    {
        $patient = $this->patient();
        // Enrolled two days ago, still running today.
        $this->enroll($patient, $this->plan(), now()->subDays(2)->toDateString(), now()->addYear()->toDateString());

        // Bill dated four days ago — before the membership existed.
        $result = MembershipBenefitService::forPatient(
            $patient->id,
            $this->items(),
            1000,
            now()->subDays(4)->toDateString()
        );

        $this->assertFalse($result['active'], 'Membership must not apply before its start_date.');
        $this->assertSame(0.0, (float) $result['discount']);
    }

    public function test_B_same_patient_still_gets_the_benefit_on_a_bill_dated_today(): void
    {
        $patient = $this->patient();
        $this->enroll($patient, $this->plan(), now()->subDays(2)->toDateString(), now()->addYear()->toDateString());

        $result = MembershipBenefitService::forPatient($patient->id, $this->items(), 1000, now()->toDateString());

        $this->assertTrue($result['active']);
        $this->assertSame(200.0, (float) $result['discount']);
    }

    public function test_C_lapsed_membership_still_applies_to_a_bill_dated_inside_its_term(): void
    {
        $patient = $this->patient();
        // Ran last year and has since been marked expired by expireStale().
        $this->enroll(
            $patient,
            $this->plan(),
            now()->subMonths(8)->toDateString(),
            now()->subMonths(2)->toDateString(),
            'expired'
        );

        $result = MembershipBenefitService::forPatient(
            $patient->id,
            $this->items(),
            1000,
            now()->subMonths(4)->toDateString()
        );

        $this->assertTrue($result['active'], 'A lapsed membership was still valid on a date inside its term.');
        $this->assertSame(200.0, (float) $result['discount']);
    }

    public function test_D_expired_membership_gives_nothing_on_a_bill_dated_today(): void
    {
        $patient = $this->patient();
        $this->enroll(
            $patient,
            $this->plan(),
            now()->subMonths(8)->toDateString(),
            now()->subMonths(2)->toDateString(),
            'expired'
        );

        $result = MembershipBenefitService::forPatient($patient->id, $this->items(), 1000, now()->toDateString());

        $this->assertFalse($result['active']);
        $this->assertSame(0.0, (float) $result['discount']);
    }

    public function test_E_omitting_the_as_of_date_keeps_the_legacy_today_behaviour(): void
    {
        $patient = $this->patient();
        $this->enroll($patient, $this->plan(), now()->subDays(2)->toDateString(), now()->addYear()->toDateString());

        $result = MembershipBenefitService::forPatient($patient->id, $this->items(), 1000);

        $this->assertTrue($result['active']);
        $this->assertSame(now()->toDateString(), $result['as_of']);
    }
}
