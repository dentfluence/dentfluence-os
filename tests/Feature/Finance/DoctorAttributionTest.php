<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Services\Analytics\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * W-8 / G-02 — doctor attribution runs through the VISIT.
 *
 * The old chain was invoices.appointment_id -> appointments.doctor_id. Nothing
 * anywhere sets invoices.appointment_id, so the whole report read "Unassigned".
 * The tracker row wanted a new column plus a backfill; measured, neither is
 * needed — treatment_visit_items.invoice_item_id already ties the work line to
 * the bill line, and treatment_visits carries the doctor.
 *
 * Frozen rule: plan is a promise, visit is a fact. The appointment says who was
 * BOOKED; the visit says who DID it. Earnings belong on the fact.
 *
 * Visits are inserted with DB::table on purpose — going through the model would
 * fire observers and drag the activity engine into a fixture.
 */
class DoctorAttributionTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-08-15';

    private function range(): array
    {
        return [
            Carbon::parse('2026-08-01')->startOfDay(),
            Carbon::parse('2026-08-31')->endOfDay(),
        ];
    }

    private function m(): ReportMetricsService
    {
        return app(ReportMetricsService::class);
    }

    private function patient(string $name): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function invoiceFor(Patient $p): Invoice
    {
        return Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $p->id,
            'invoice_date'   => self::DAY,
            'status'         => 'draft',
        ]);
    }

    private function line(Invoice $inv, float $amount): InvoiceItem
    {
        return InvoiceItem::create([
            'invoice_id'  => $inv->id,
            'description' => 'Work',
            'unit_price'  => $amount,
            'qty'         => 1,
            'net_amount'  => $amount,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => $amount,
        ]);
    }

    /** A recorded visit by $doctor, optionally tied to a billed line. */
    private function visit(Patient $p, ?User $doctor, string $treatmentName, ?int $invoiceItemId = null, string $date = self::DAY): int
    {
        $visitId = DB::table('treatment_visits')->insertGetId([
            'patient_id' => $p->id,
            'doctor_id'  => $doctor?->id,
            'visit_date' => $date,
            'status'     => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('treatment_visit_items')->insert([
            'treatment_visit_id' => $visitId,
            'patient_id'         => $p->id,
            'treatment_name'     => $treatmentName,
            'invoice_item_id'    => $invoiceItemId,
            'billing_status'     => $invoiceItemId ? 'invoiced' : 'pending',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return $visitId;
    }

    private function pay(Invoice $inv, Patient $p, float $amount, User $staff): InvoicePayment
    {
        $payment = InvoicePayment::create([
            'invoice_id'   => $inv->id,
            'patient_id'   => $p->id,
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => self::DAY,
            'created_by'   => $staff->id,
        ]);
        $inv->refresh();
        $inv->recalculate();

        return $payment;
    }

    /* ───────────────────────────────────────────────────────────────────── */

    public function test_money_follows_the_visit_not_the_appointment(): void
    {
        $doctor = User::factory()->create(['name' => 'Dr Anushka']);
        $staff  = User::factory()->create();
        $p      = $this->patient('Visit Patient');

        $inv  = $this->invoiceFor($p);
        $item = $this->line($inv, 20000);
        $inv->recalculate();

        $this->visit($p, $doctor, 'Scaling', $item->id);
        $this->pay($inv, $p, 20000, $staff);

        [$from, $to] = $this->range();
        $rows = $this->m()->collectedByDoctor($from, $to);

        $this->assertTrue($rows->has('Dr Anushka'), 'the treating doctor must be credited');
        $this->assertEqualsWithDelta(20000.0, (float) $rows->get('Dr Anushka')->collected, 0.02);
        $this->assertFalse($rows->has('Unassigned'),
            'invoices.appointment_id is never set — attribution must not depend on it');
    }

    /** The DB::table trap: InvoicePayment soft-deletes, raw queries do not know that. */
    public function test_a_voided_payment_is_never_credited_to_the_doctor(): void
    {
        $doctor = User::factory()->create(['name' => 'Dr Voided']);
        $staff  = User::factory()->create();
        $p      = $this->patient('Void Patient');

        $inv  = $this->invoiceFor($p);
        $item = $this->line($inv, 15000);
        $inv->recalculate();
        $this->visit($p, $doctor, 'RCT', $item->id);

        $payment = $this->pay($inv, $p, 15000, $staff);
        $payment->delete();   // soft delete — exactly what a void does

        [$from, $to] = $this->range();
        $rows = $this->m()->collectedByDoctor($from, $to);

        $this->assertEqualsWithDelta(
            0.0,
            (float) ($rows->get('Dr Voided')->collected ?? 0),
            0.02,
            'a voided payment must not survive into a doctor’s collections'
        );
    }

    public function test_one_invoice_splits_between_two_doctors_by_line_share(): void
    {
        $a     = User::factory()->create(['name' => 'Dr A']);
        $b     = User::factory()->create(['name' => 'Dr B']);
        $staff = User::factory()->create();
        $p     = $this->patient('Split Patient');

        $inv   = $this->invoiceFor($p);
        $lineA = $this->line($inv, 60000);
        $lineB = $this->line($inv, 40000);
        $inv->recalculate();

        $this->visit($p, $a, 'Implant', $lineA->id);
        $this->visit($p, $b, 'Crown',   $lineB->id);

        // Part payment: 50,000 of 1,00,000 — split 60/40, not first-come.
        $this->pay($inv, $p, 50000, $staff);

        [$from, $to] = $this->range();
        $rows = $this->m()->collectedByDoctor($from, $to);

        $this->assertEqualsWithDelta(30000.0, (float) $rows->get('Dr A')->collected, 0.02, 'Dr A: 60% of 50,000');
        $this->assertEqualsWithDelta(20000.0, (float) $rows->get('Dr B')->collected, 0.02, 'Dr B: 40% of 50,000');
    }

    public function test_billed_by_doctor_reconciles_to_billed(): void
    {
        $a     = User::factory()->create(['name' => 'Dr Billed']);
        $p     = $this->patient('Billed Patient');

        $inv   = $this->invoiceFor($p);
        $lineA = $this->line($inv, 70000);
        $this->line($inv, 30000);          // never linked to a visit
        $inv->recalculate();

        $this->visit($p, $a, 'Implant', $lineA->id);

        [$from, $to] = $this->range();
        $m    = $this->m();
        $rows = $m->billedByDoctor($from, $to);

        $this->assertEqualsWithDelta(70000.0, (float) $rows->get('Dr Billed')->billed, 0.02);
        $this->assertEqualsWithDelta(30000.0, (float) $rows->get('Unassigned')->billed, 0.02,
            'a billed line with no visit behind it is Unassigned, not dropped');
        $this->assertEqualsWithDelta(
            $m->billed($from, $to),
            (float) $rows->sum('billed'),
            0.05,
            'doctor columns must add back up to billed()'
        );
    }

    public function test_work_done_is_counted_even_when_it_was_never_billed(): void
    {
        $doc = User::factory()->create(['name' => 'Dr Waived']);
        $p   = $this->patient('Waived Patient');

        // No invoice at all — a waived or not-yet-billed procedure still happened.
        $this->visit($p, $doc, 'Consultation', null);
        $this->visit($p, $doc, 'Consultation', null);

        [$from, $to] = $this->range();
        $rows = $this->m()->productionByDoctor($from, $to)
                     ->where('doctor', 'Dr Waived');

        $this->assertCount(1, $rows, 'two consultations group into one row');
        $this->assertSame(2, (int) $rows->first()->times);
        $this->assertSame('Consultation', $rows->first()->treatment,
            'unbilled work falls back to the typed name — honest, if not canonical');

        $visits = $this->m()->visitsByDoctor($from, $to);
        $this->assertSame(2, (int) $visits->get('Dr Waived')->visits);
    }
}
