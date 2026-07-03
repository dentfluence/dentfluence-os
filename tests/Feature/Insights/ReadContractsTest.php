<?php

namespace Tests\Feature\Insights;

use App\Contracts\Insights\AppointmentReadContract;
use App\Contracts\Insights\BillingReadContract;
use App\Contracts\Insights\CommunicationReadContract;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 6 · Slice 4 — read-contracts.
 *
 * Proves each Eloquent*ReadContract implementation returns exactly what the
 * inline queries in the Slice 1 calculators used to return, and that the
 * container resolves the interfaces (bound in InsightsServiceProvider) —
 * the extraction changed WHERE the query lives, never what it returns.
 */
class ReadContractsTest extends TestCase
{
    use RefreshDatabase;

    private function patient(): Patient
    {
        return Patient::create(['name' => 'Contract Test Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
    }

    private function doctor(): User
    {
        return User::factory()->create(['branch_id' => 1]);
    }

    public function test_container_resolves_all_three_contracts(): void
    {
        $this->assertInstanceOf(AppointmentReadContract::class, app(AppointmentReadContract::class));
        $this->assertInstanceOf(CommunicationReadContract::class, app(CommunicationReadContract::class));
        $this->assertInstanceOf(BillingReadContract::class, app(BillingReadContract::class));
    }

    public function test_appointment_contract_finds_last_completed_visit(): void
    {
        $patient = $this->patient();
        $doctor  = $this->doctor();

        DB::table('appointments')->insert([
            'patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'branch_id' => 1,
            'appointment_date' => now()->subDays(3)->toDateString(), 'appointment_time' => '10:00:00',
            'type' => 'treatment', 'status' => 'done', 'created_by' => $doctor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $lastVisit = app(AppointmentReadContract::class)->lastCompletedVisitDate([$patient->id]);

        $this->assertSame(now()->subDays(3)->toDateString(), $lastVisit);
    }

    public function test_appointment_contract_returns_null_with_no_patients(): void
    {
        $this->assertNull(app(AppointmentReadContract::class)->lastCompletedVisitDate([]));
    }

    public function test_communication_contract_counts_recall_outcomes(): void
    {
        $patient = $this->patient();

        DB::table('communication_queue')->insert([
            ['person_name' => 'x', 'phone' => '1', 'patient_id' => $patient->id, 'source_engine' => 'recall', 'outcome' => 'appointment_booked', 'created_at' => now(), 'updated_at' => now()],
            ['person_name' => 'x', 'phone' => '1', 'patient_id' => $patient->id, 'source_engine' => 'recall', 'outcome' => 'unreachable', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $counts = app(CommunicationReadContract::class)->recallOutcomeCounts([$patient->id]);

        $this->assertSame(['total' => 2, 'positive' => 1], $counts);
    }

    public function test_billing_contract_sums_payments(): void
    {
        $patient = $this->patient();
        $invoice = Invoice::create([
            'invoice_number' => 'INV-RC-' . random_int(100000, 999999), 'patient_id' => $patient->id,
            'invoice_date' => now()->toDateString(), 'total_amount' => 1000, 'paid_amount' => 1000, 'status' => 'paid',
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id, 'patient_id' => $patient->id,
            'amount' => 1000, 'payment_mode' => 'cash', 'payment_date' => now()->toDateString(),
        ]);

        $total = app(BillingReadContract::class)->totalPayments([$patient->id]);

        $this->assertEqualsWithDelta(1000.0, $total, 0.01);
    }
}
