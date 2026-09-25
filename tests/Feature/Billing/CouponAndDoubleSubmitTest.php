<?php

namespace Tests\Feature\Billing;

use App\Models\CouponCode;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Billing\ReceiptVoidService;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * INT-05 — coupon limits re-checked under a lock, usage released on cancel.
 * INT-07 — patient-level payment double submit is refused, not turned into a
 *          phantom advance.
 */
class CouponAndDoubleSubmitTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function admin(): User
    {
        $user = $this->userWithModulePerm('finance', true, true, true);
        $user->update(['role' => 'admin', 'role_id' => \App\Models\Role::where('slug', \App\Models\Role::ADMIN)->value('id')]);

        return $user->fresh();
    }

    private function patient(): Patient
    {
        return Patient::create(['name' => 'Coupon Patient', 'phone' => '9' . random_int(100000000, 999999999), 'branch_id' => 1]);
    }

    private function invoice(Patient $patient, float $amount = 1000): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(), 'patient_id' => $patient->id,
            'invoice_date' => today()->toDateString(), 'status' => 'draft',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Treatment', 'unit_price' => $amount,
            'qty' => 1, 'net_amount' => $amount, 'gst_pct' => 0, 'gst_amount' => 0, 'total' => $amount,
        ]);
        $invoice->recalculate();

        return $invoice->fresh();
    }

    private function coupon(int $global, int $perPatient = 5): CouponCode
    {
        return CouponCode::create([
            'code' => 'T' . random_int(1000, 9999), 'discount_type' => 'flat', 'discount_value' => 100,
            'max_uses_global' => $global, 'max_uses_per_patient' => $perPatient, 'uses_count' => 0,
            'min_invoice_amount' => 0, 'is_active' => true,
        ]);
    }

    public function test_global_limit_holds_even_when_the_pre_check_was_passed(): void
    {
        $coupon = $this->coupon(1);
        $a = $this->patient();
        $b = $this->patient();

        (new CouponService())->apply($coupon->id, $a->id, $this->invoice($a)->id, 100);

        $this->expectException(ValidationException::class);
        (new CouponService())->apply($coupon->id, $b->id, $this->invoice($b)->id, 100);
    }

    public function test_same_invoice_records_one_usage_only(): void
    {
        $coupon = $this->coupon(0);
        $p = $this->patient();
        $inv = $this->invoice($p);

        (new CouponService())->apply($coupon->id, $p->id, $inv->id, 100);
        (new CouponService())->apply($coupon->id, $p->id, $inv->id, 100);

        $this->assertSame(1, $coupon->fresh()->uses_count);
    }

    public function test_cancelling_the_invoice_gives_the_coupon_back(): void
    {
        $this->actingAs($this->admin());
        $coupon = $this->coupon(1);
        $p = $this->patient();
        $inv = $this->invoice($p);
        (new CouponService())->apply($coupon->id, $p->id, $inv->id, 100);

        app(ReceiptVoidService::class)->cancelInvoice($inv, 'no_refund', 'Entered twice', auth()->id());

        $this->assertSame(0, $coupon->fresh()->uses_count);
        $this->assertTrue($coupon->fresh()->canBeUsedByPatient($p->id));
    }

    public function test_patient_payment_double_submit_does_not_become_an_advance(): void
    {
        $user = $this->admin();
        $p = $this->patient();
        $this->invoice($p, 700);

        $payload = ['amount' => 700, 'payment_mode' => 'cash', 'payment_date' => today()->toDateString()];
        $this->actingAs($user)->post(route('billing.patientPayment', $p), $payload)->assertRedirect();
        $this->actingAs($user)->post(route('billing.patientPayment', $p), $payload)->assertSessionHasErrors('amount');

        $this->assertSame(1, Receipt::where('patient_id', $p->id)->count());
    }
}
