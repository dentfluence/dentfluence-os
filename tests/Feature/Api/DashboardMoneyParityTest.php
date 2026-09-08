<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Analytics\ReportMetricsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M-2 (Android V1.1) — the phone's home screen must show the SAME money as
 * the web dashboard, to the rupee.
 *
 * Both figures were wrong on the phone before 8 Sep 2026, in ways the web
 * had already fixed:
 *
 *   - "today_revenue" summed invoices.paid_amount by INVOICE date, so money
 *     taken today against an older invoice was invisible.
 *   - "outstanding" filtered status IN ('unpaid','partial'), but a fully
 *     unpaid invoice is 'draft' — every such invoice was missing.
 *
 * Each test seeds the case that the OLD code got wrong, and asserts the
 * endpoint agrees with ReportMetricsService, which is the one definition.
 */
class DashboardMoneyParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-08 11:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'branch_id' => 1, 'is_active' => true]);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Parity Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function invoice(Patient $p, string $date, float $amount): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $p->id,
            'invoice_date'   => $date,
            'status'         => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Treatment',
            'unit_price'  => $amount,
            'qty'         => 1,
            'net_amount'  => $amount,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => $amount,
        ]);
        $invoice->recalculate();

        return $invoice->refresh();
    }

    private function pay(Invoice $invoice, string $date, float $amount, User $by): void
    {
        InvoicePayment::create([
            'invoice_id'   => $invoice->id,
            'patient_id'   => $invoice->patient_id,
            'amount'       => $amount,
            'payment_mode' => 'cash',
            'payment_date' => $date,
            'created_by'   => $by->id,
        ]);
        $invoice->refresh()->recalculate();
    }

    public function test_collected_today_counts_a_payment_made_today_against_an_old_invoice(): void
    {
        $admin = $this->admin();
        $p     = $this->patient();

        $july = $this->invoice($p, '2026-07-10', 5000);
        $this->pay($july, '2026-09-08', 2000, $admin);   // today, on July's bill

        Sanctum::actingAs($admin);
        $res = $this->getJson('/api/v1/dashboard')->assertOk();

        // The old code read invoices dated today: there are none, so it said 0.
        $res->assertJsonPath('data.finance.today_revenue', 2000);
        $res->assertJsonPath('data.finance.collected_today', 2000);
        $res->assertJsonPath('data.money_visible', true);
    }

    public function test_outstanding_includes_fully_unpaid_draft_invoices(): void
    {
        $admin = $this->admin();
        $p     = $this->patient();

        $this->invoice($p, '2026-08-20', 5000);                       // nothing paid: status 'draft'
        $part = $this->invoice($p, '2026-08-25', 3000);
        $this->pay($part, '2026-08-25', 1000, $admin);                // 'partial', 2000 due

        Sanctum::actingAs($admin);
        $res = $this->getJson('/api/v1/dashboard')->assertOk();

        // Old filter ('unpaid','partial') saw only the 2000.
        $res->assertJsonPath('data.finance.outstanding_balance', 7000);
        $res->assertJsonPath('data.finance.outstanding_count', 2);
    }

    public function test_the_endpoint_agrees_with_report_metrics_service_to_the_rupee(): void
    {
        $admin = $this->admin();
        $p     = $this->patient();

        $a = $this->invoice($p, '2026-09-01', 12000);
        $this->pay($a, '2026-09-08', 4500, $admin);
        $this->invoice($p, '2026-09-08', 800);
        Wallet::create([
            'patient_id'             => $p->id,
            'balance_promotional'    => 100,
            'balance_permanent'      => 999,
            'balance_patient_credit' => 2500,
            'balance_total'          => 1099,
        ]);

        $m = app(ReportMetricsService::class);

        Sanctum::actingAs($admin);
        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data.finance');

        $this->assertSame($m->collected(now()->startOfDay(), now()->endOfDay(), 1), (float) $data['collected_today']);
        $this->assertSame($m->outstanding(1), (float) $data['outstanding_balance']);
        $this->assertSame($m->patientCreditHeld(1), (float) $data['patient_credit']);
        $this->assertSame(2500.0, (float) $data['patient_credit']);
    }

    public function test_money_is_hidden_from_non_admin_roles(): void
    {
        $user = User::factory()->create(['role' => 'front_desk', 'branch_id' => 1, 'is_active' => true]);
        $p    = $this->patient();
        $this->invoice($p, '2026-09-08', 5000);

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/dashboard')->assertOk();

        $res->assertJsonPath('data.money_visible', false);
        $res->assertJsonPath('data.finance', null);
        // The counts the receptionist DOES need are still there.
        $res->assertJsonStructure(['data' => ['patients', 'appointments', 'lab', 'today_appointments']]);
    }
}
