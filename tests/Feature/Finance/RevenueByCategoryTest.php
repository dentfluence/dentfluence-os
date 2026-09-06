<?php

namespace Tests\Feature\Finance;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Services\Analytics\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * W-7 / G-01 — revenue by treatment category.
 *
 * The report used to read finance_income_entries, a table with EXACTLY ZERO
 * writers anywhere in the application, so every category had shown Rs 0 since
 * the day it shipped. It now reads invoice_items.
 *
 * The reason this needs a test rather than a one-line swap: five discount
 * layers live on the INVOICE, not on the line. Invoice::recalculate() computes
 *     total = (taxable + gst) - wallet - coupon - membership - manual
 * so a plain SUM(invoice_items.total) is always >= the invoice total, and the
 * category table's Total would silently disagree with Billed everywhere else.
 * Each line therefore takes its pro-rata share of the invoice's real total.
 *
 * The seeded invoice makes that visible: 1,20,000 of lines, a 12,000 manual
 * discount, so 1,08,000 actually billed, and every category must come back
 * exactly 10% lighter.
 */
class RevenueByCategoryTest extends TestCase
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

    private function treatment(string $category, string $name): int
    {
        $catId = DB::table('treatment_categories')->insertGetId([
            'name'       => $category,
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('treatments')->insertGetId([
            'treatment_category_id' => $catId,
            'name'                  => $name,
            'is_active'             => 1,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    private function patient(string $name): Patient
    {
        return Patient::create([
            'name'      => $name,
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function line(Invoice $inv, ?int $treatmentId, float $amount): void
    {
        InvoiceItem::create([
            'invoice_id'   => $inv->id,
            'treatment_id' => $treatmentId,
            'description'  => 'Line',
            'unit_price'   => $amount,
            'qty'          => 1,
            'net_amount'   => $amount,
            'gst_pct'      => 0,
            'gst_amount'   => 0,
            'total'        => $amount,
        ]);
    }

    private function invoice(Patient $p, string $date = self::DAY, string $status = 'draft'): Invoice
    {
        return Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $p->id,
            'invoice_date'   => $date,
            'status'         => $status,
        ]);
    }

    public function test_categories_sum_exactly_to_billed_even_with_an_invoice_level_discount(): void
    {
        $implant = $this->treatment('Implants', 'Single implant');
        $ortho   = $this->treatment('Ortho', 'Aligner phase 1');
        $p       = $this->patient('Category Patient');

        $inv = $this->invoice($p);
        $this->line($inv, $implant, 60000);
        $this->line($inv, $ortho,   40000);
        $this->line($inv, null,     20000);   // manual line, no treatment picked

        // A 12,000 invoice-level discount on 1,20,000 of lines -> 1,08,000 billed.
        $inv->manual_discount_amount = 12000;
        $inv->save();
        $inv->recalculate();
        $inv->refresh();

        $this->assertEqualsWithDelta(108000.0, (float) $inv->total_amount, 0.01,
            'seed sanity: the invoice really is discounted');

        [$from, $to] = $this->range();
        $m    = app(ReportMetricsService::class);
        $rows = $m->billedByCategory($from, $to);

        // Every category lands 10% lighter, not at its gross line value.
        $this->assertEqualsWithDelta(54000.0, (float) $rows->get('Implants')->revenue, 0.02, 'Implants');
        $this->assertEqualsWithDelta(36000.0, (float) $rows->get('Ortho')->revenue, 0.02, 'Ortho');
        $this->assertEqualsWithDelta(18000.0, (float) $rows->get('Uncategorised')->revenue, 0.02, 'Uncategorised');

        // The whole point: this table can never disagree with Billed.
        $this->assertEqualsWithDelta(
            $m->billed($from, $to),
            (float) $rows->sum('revenue'),
            0.05,
            'category revenue must reconcile to billed() to the rupee'
        );
    }

    public function test_a_line_with_no_treatment_is_shown_not_dropped(): void
    {
        $p   = $this->patient('Manual Line Patient');
        $inv = $this->invoice($p);
        $this->line($inv, null, 25000);
        $inv->recalculate();

        [$from, $to] = $this->range();
        $rows = app(ReportMetricsService::class)->billedByCategory($from, $to);

        $this->assertTrue($rows->has('Uncategorised'),
            'treatment_id is nullable by design — dropping those lines under-reports the total while looking healthy');
        $this->assertEqualsWithDelta(25000.0, (float) $rows->get('Uncategorised')->revenue, 0.02);
    }

    public function test_cancelled_invoices_are_not_counted(): void
    {
        $implant = $this->treatment('Implants', 'Single implant');
        $p       = $this->patient('Cancelled Patient');

        $good = $this->invoice($p);
        $this->line($good, $implant, 30000);
        $good->recalculate();

        $bad = $this->invoice($p);
        $this->line($bad, $implant, 99000);
        $bad->recalculate();
        $bad->status = 'cancelled';
        $bad->save();

        [$from, $to] = $this->range();
        $rows = app(ReportMetricsService::class)->billedByCategory($from, $to);

        $this->assertEqualsWithDelta(30000.0, (float) $rows->get('Implants')->revenue, 0.02,
            'a cancelled invoice is not revenue');
    }

    public function test_invoices_outside_the_range_are_not_counted(): void
    {
        $implant = $this->treatment('Implants', 'Single implant');
        $p       = $this->patient('Range Patient');

        $inRange = $this->invoice($p, self::DAY);
        $this->line($inRange, $implant, 30000);
        $inRange->recalculate();

        $before = $this->invoice($p, '2026-07-10');
        $this->line($before, $implant, 70000);
        $before->recalculate();

        [$from, $to] = $this->range();
        $rows = app(ReportMetricsService::class)->billedByCategory($from, $to);

        $this->assertEqualsWithDelta(30000.0, (float) $rows->get('Implants')->revenue, 0.02,
            'billed by category is a FLOW — it follows the date filter');
    }
}
