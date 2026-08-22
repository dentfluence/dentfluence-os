<?php

namespace Tests\Feature\Billing;

use App\Enums\PaymentMode;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Billing\PatientPaymentAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Access\Concerns\BuildsAccessPersonas;
use Tests\TestCase;

/**
 * A3 — PAYMENT MODE STANDARDISATION.
 *
 * Two defects fixed together:
 *   1. finance_transactions.payment_mode lacked `debit_card`. Strict mode turned
 *      that into MySQL 1265 INSIDE the payment transaction, so a debit-card
 *      payment rolled back entirely — six live entry points simply failed.
 *   2. `netbanking` duplicated `bank_transfer` with no behavioural difference.
 *      Retired; historical rows migrated.
 *
 * The test that matters most is the schema contract at the bottom: any mode a
 * payment can be TAKEN in must be a mode a finance row can be WRITTEN in. That
 * assertion, had it existed, would have caught this on 2026-06-06.
 */
class PaymentModeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAccessPersonas;

    private function staff(): User
    {
        $user = $this->userWithModulePerm('finance', true, true, true);
        $user->update(['role' => 'admin']);

        return $user->fresh();
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name'      => 'Mode Patient',
            'phone'     => '9' . random_int(100000000, 999999999),
            'branch_id' => 1,
        ]);
    }

    private function invoiceFor(Patient $patient, float $amount): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => today()->toDateString(),
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

        return $invoice->fresh();
    }

    /**
     * The real production route, not the service directly.
     *
     * Some modes carry extra required fields (cheque needs bank/number/date).
     * They are supplied here so a positive-path test exercises the payment
     * rather than silently bouncing off validation — assertRedirect() alone
     * cannot tell "payment recorded" from "redirected back with errors".
     */
    private function pay(User $user, Invoice $invoice, float $amount, string $mode)
    {
        $payload = [
            'amount'       => $amount,
            'payment_mode' => $mode,
            'payment_date' => today()->toDateString(),
            'reference_no' => 'REF-' . strtoupper($mode),
        ];

        if ($mode === 'cheque') {
            $payload += [
                'bank_name'   => 'HDFC Bank',
                'cheque_no'   => '000123',
                'cheque_date' => today()->toDateString(),
            ];
        }

        return $this->actingAs($user)->post(route('billing.payment', $invoice), $payload);
    }

    /** Every enum column's allowed values, read from the live schema. */
    private function enumValues(string $table): array
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'payment_mode']
        );

        $this->assertNotNull($row, "No payment_mode column on {$table}.");
        preg_match_all("/'([^']+)'/", $row->t, $m);

        return $m[1];
    }

    // ── 1–3: each canonical tender records end to end ───────────────────────

    #[DataProvider('tenderModes')]
    public function test_a_canonical_mode_records_across_all_three_tables(string $mode): void
    {
        $user    = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 2000);

        $this->pay($user, $invoice, 2000, $mode)
             ->assertSessionHasNoErrors()
             ->assertRedirect();

        // The payment survived — this is the assertion that fails pre-A3 for
        // debit_card, because the FinanceTransaction insert rolled everything back.
        $payment = InvoicePayment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($mode, $payment->payment_mode);

        $receipt = Receipt::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame($mode, $receipt->payment_mode);

        $finance = FinanceTransaction::where('source_type', InvoicePayment::class)
            ->where('source_id', $payment->id)->firstOrFail();
        $this->assertSame($mode, $finance->payment_mode,
            "finance_transactions must store '{$mode}' verbatim, not coerce it.");

        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);
    }

    public static function tenderModes(): array
    {
        return [
            'credit card'   => ['card'],
            'debit card'    => ['debit_card'],
            'bank transfer' => ['bank_transfer'],
            'cash'          => ['cash'],
            'upi'           => ['upi'],
            'cheque'        => ['cheque'],
        ];
    }

    public function test_exactly_one_payment_and_one_finance_row_are_written(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 2000);

        $this->pay($user, $invoice, 2000, 'debit_card')->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(1, InvoicePayment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, Receipt::where('invoice_id', $invoice->id)->count());
        $this->assertSame(1, FinanceTransaction::where('type', 'income')
            ->where('patient_id', $invoice->patient_id)->count());
    }

    // ── 4: netbanking is gone ───────────────────────────────────────────────

    public function test_netbanking_is_rejected_as_a_new_payment_mode(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 2000);

        $this->pay($user, $invoice, 2000, 'netbanking')
             ->assertSessionHasErrors('payment_mode');

        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_netbanking_is_not_storable_in_any_payment_table(): void
    {
        foreach (['invoice_payments', 'receipts', 'finance_transactions'] as $table) {
            $this->assertNotContains('netbanking', $this->enumValues($table),
                "{$table} still allows netbanking — the migration did not narrow it.");
        }
    }

    public function test_netbanking_is_absent_from_every_selector_and_validator(): void
    {
        $this->assertNotContains('netbanking', PaymentMode::values());
        $this->assertNotContains('netbanking', PatientPaymentAllocationService::ALLOWED_MODES);
        $this->assertStringNotContainsString('netbanking', PaymentMode::rule());

        $selectors = [
            'resources/views/billing/show.blade.php',
            'resources/views/billing/_invoice_panel.blade.php',
            'resources/views/patients/profile/quick-pay-modal.blade.php',
            'resources/views/patients/partials/membership-tab.blade.php',
            'resources/views/finance/wallets/show.blade.php',
        ];

        foreach ($selectors as $view) {
            $html = file_get_contents(base_path($view));
            $this->assertStringNotContainsString('value="netbanking"', $html,
                "{$view} still offers netbanking.");
        }
    }

    /** A historical row keeps rendering — as Bank Transfer, never blank. */
    public function test_a_retired_mode_still_renders_for_historical_rows(): void
    {
        $this->assertSame('Bank Transfer', PaymentMode::labelFor('netbanking'));
        $this->assertSame('Insurance', PaymentMode::labelFor('insurance'));
        $this->assertSame('—', PaymentMode::labelFor(null));
    }

    // ── UI vocabulary ───────────────────────────────────────────────────────

    public function test_card_reads_credit_card_and_never_a_bare_card(): void
    {
        $this->assertSame('Credit Card', PaymentMode::from('card')->label());
        $this->assertSame('Debit Card', PaymentMode::from('debit_card')->label());
        $this->assertSame('Bank Transfer', PaymentMode::from('bank_transfer')->label());
        $this->assertSame('UPI', PaymentMode::from('upi')->label());
        $this->assertSame('EMI', PaymentMode::from('emi')->label());

        foreach (PaymentMode::cases() as $mode) {
            $this->assertNotSame('Card', $mode->label(),
                'No mode may display as a bare "Card".');
        }
    }

    public function test_the_receipt_prints_credit_card(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 1000);
        $this->pay($user, $invoice, 1000, 'card')->assertSessionHasNoErrors()->assertRedirect();

        $receipt = Receipt::where('invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($user)
             ->get(route('billing.receipt', [$invoice, $receipt]))
             ->assertOk()
             ->assertSee('CREDIT CARD');
    }

    public function test_the_invoice_payment_selector_offers_the_canonical_labels(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 1000);

        $this->actingAs($user)->get(route('billing.show', $invoice))
             ->assertOk()
             ->assertSee('Credit Card')
             ->assertSee('Debit Card')
             ->assertSee('Bank Transfer')
             ->assertDontSee('Net Banking');
    }

    // ── 6: invalid modes still rejected ─────────────────────────────────────

    public function test_an_unknown_mode_is_rejected(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 500);

        $this->pay($user, $invoice, 500, 'crypto')->assertSessionHasErrors('payment_mode');
        $this->assertSame(0, InvoicePayment::where('invoice_id', $invoice->id)->count());
    }

    public function test_wallet_is_not_staff_selectable_on_the_payment_route(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 500);

        // Wallet tender is written by WalletService, never chosen on a form.
        $this->pay($user, $invoice, 500, 'wallet')->assertSessionHasErrors('payment_mode');
    }

    // ── 7: THE SCHEMA CONTRACT ──────────────────────────────────────────────

    /**
     * Any mode a payment can be TAKEN in must be a mode a finance row can be
     * WRITTEN in. This is the assertion whose absence let debit_card sit broken
     * from 2026-06-06 to 2026-08-19.
     */
    public function test_every_takeable_mode_is_recordable_in_finance_transactions(): void
    {
        $finance = $this->enumValues('finance_transactions');

        foreach (['invoice_payments', 'receipts'] as $table) {
            foreach ($this->enumValues($table) as $mode) {
                $this->assertContains($mode, $finance,
                    "{$table} accepts '{$mode}' but finance_transactions cannot store it — "
                    . 'a payment in this mode will roll back.');
            }
        }
    }

    public function test_the_billing_tables_match_the_canonical_enum_exactly(): void
    {
        $canonical = PaymentMode::values();
        sort($canonical);

        foreach (['invoice_payments', 'receipts'] as $table) {
            $actual = $this->enumValues($table);
            sort($actual);
            $this->assertSame($canonical, $actual,
                "{$table} has drifted from App\\Enums\\PaymentMode.");
        }
    }

    /** insurance is retained for history only — never canonical, never offered. */
    public function test_insurance_is_retained_in_finance_but_never_offered(): void
    {
        $this->assertContains('insurance', $this->enumValues('finance_transactions'),
            'insurance must NOT be dropped from finance_transactions without a data check.');

        $this->assertNotContains('insurance', PaymentMode::values());
        $this->assertStringNotContainsString('insurance', PaymentMode::rule());
    }

    // ── 8: entry-point consistency ──────────────────────────────────────────

    public function test_every_entry_point_validator_draws_from_the_canonical_set(): void
    {
        $canonical = PaymentMode::values();

        // The mobile capability payload the app renders its picker from.
        foreach (PaymentMode::options(['wallet']) as $option) {
            $this->assertContains($option['value'], $canonical);
            $this->assertNotSame('netbanking', $option['value']);
        }

        // Membership enrolment excludes EMI and wallet — deliberate, unchanged.
        $rule = PaymentMode::rule(['wallet', 'emi']);
        $this->assertStringNotContainsString('emi', $rule);
        $this->assertStringContainsString('debit_card', $rule);

        // No entry point may restate the vocabulary. Grep the source: a literal
        // payment-mode list outside the enum is how the tables drifted apart.
        $offenders = [];
        foreach ([
            'app/Http/Controllers/BillingController.php',
            'app/Http/Controllers/Api/V1/BillingController.php',
            'app/Http/Controllers/Api/V1/MembershipController.php',
            'app/Http/Controllers/Finance/WalletController.php',
        ] as $file) {
            $src = file_get_contents(base_path($file));
            if (preg_match('/in:cash,\s*(card|upi)/', $src)) {
                $offenders[] = $file;
            }
        }

        $this->assertSame([], $offenders,
            'These files still hard-code a payment-mode list instead of using PaymentMode::rule().');
    }

    /**
     * Money LEAVING the clinic (wallet refund, and the API advance mirror) is
     * deliberately restricted to modes a clinic can actually pay out in — you
     * cannot refund someone onto a credit card rail here, and EMI is meaningless.
     *
     * That list is now derived by exclusion rather than restated, which creates
     * a new hazard: adding a mode to the enum would silently widen it. This test
     * pins the resulting set, so growth fails loudly instead.
     */
    public function test_the_cash_out_rule_resolves_to_exactly_five_modes(): void
    {
        $rule    = PaymentMode::rule(['card', 'debit_card', 'emi', 'wallet']);
        $allowed = explode(',', substr($rule, strlen('in:')));
        sort($allowed);

        $this->assertSame(
            ['bank_transfer', 'cash', 'cheque', 'other', 'upi'],
            $allowed,
            'The refund/advance-out vocabulary changed. A1 froze this set — widening '
            . 'it is a business decision, not a side effect of adding a payment mode.'
        );
    }

    /**
     * 9 — FIFO allocation is untouched by A3. Its narrower list is a business
     * rule, not a vocabulary one, and must survive this change unchanged.
     */
    public function test_patient_level_allocation_still_accepts_exactly_its_own_modes(): void
    {
        $this->assertSame(
            ['cash', 'upi', 'debit_card', 'bank_transfer', 'cheque', 'other'],
            PatientPaymentAllocationService::ALLOWED_MODES,
            'FIFO accepts six of the nine canonical modes. Changing this set is a '
            . 'business decision, not a vocabulary one.'
        );

        // And every one of them is canonical — no orphan can hide in here.
        foreach (PatientPaymentAllocationService::ALLOWED_MODES as $mode) {
            $this->assertContains($mode, PaymentMode::values());
        }
    }

    /**
     * The patient-profile Quick Pay modal now renders the SAME per-invoice form
     * as the invoice screen, including the convenience-fee block and the
     * "Total charged to patient" row that were previously missing there.
     *
     * The old markup used a radio grid; the shared partial renders a <select
     * id="qpMode">. Two pieces of script still referenced the radios, one of
     * which threw on null and broke invoice selection outright — hence the
     * explicit assertion that no radio input survives.
     */
    public function test_the_profile_quick_pay_modal_uses_the_shared_form(): void
    {
        // The patient profile needs BOTH modules: the page is behind
        // module:patients (which redirects 302 rather than 403 when denied) and
        // the modal it renders is finance. staff() carries finance only.
        $user = $this->userWithTwoModulePerms(
            'finance',  [true, true, true],
            'patients', [true, true, true],
        );
        $user->update(['role' => 'admin']);
        $user = $user->fresh();

        $patient = $this->patient();
        $this->invoiceFor($patient, 28000);

        $html = $this->actingAs($user)
            ->get(route('patients.show', $patient))
            ->assertOk()
            ->getContent();

        foreach ([
            'id="qpPayForm"', 'id="qpAmount"', 'id="qpMode"',
            'id="qpFieldCC"', 'id="qpCcFeePanel"', 'id="qpCcTotal"', 'id="qpCcTotalAmt"',
            'id="qpFieldCheque"', 'id="qpFieldEmi"', 'id="qpProviderSel"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html,
                "Quick Pay is missing {$needle} — its JS binds to it.");
        }

        // The radio grid is gone; anything still querying it would throw.
        $this->assertStringNotContainsString('input type="radio" name="payment_mode"', $html);
        $this->assertStringNotContainsString('qpModeSelect', $html);

        // Same vocabulary as the invoice screen.
        $this->assertStringContainsString('Total charged to patient', $html);
        $this->assertStringContainsString('Credit Card', $html);
        $this->assertStringContainsString('Debit Card', $html);
        $this->assertStringNotContainsString('Net Banking', $html);
    }

    /**
     * EMI must never reach patient-level FIFO. An instalment schedule needs
     * provider + tenure bound to ONE invoice; a tender split across several
     * invoices has nothing to attach it to. This is a business rule, and it is
     * the reason the two payment forms legitimately differ by one option.
     */
    public function test_emi_is_rejected_by_patient_level_allocation(): void
    {
        $user    = $this->staff();
        $patient = $this->patient();
        $this->invoiceFor($patient, 1000);

        $this->assertNotContains('emi', PatientPaymentAllocationService::ALLOWED_MODES);

        // Must fail as a clean 422, never as a 500 from the database.
        $this->actingAs($user)->post(route('billing.patientPayment', $patient), [
            'amount'       => 1000,
            'payment_mode' => 'emi',
            'payment_date' => today()->toDateString(),
        ])->assertSessionHasErrors('payment_mode');
    }

    /**
     * A3.x — Credit Card is SINGLE-INVOICE ONLY.
     *
     * The convenience fee is computed once on the whole swipe and there is no
     * defensible way to attribute it across several invoices. Rather than
     * silently record a zero fee (which made the clinic absorb ~2.5% of every
     * combined card payment), the mode is not offered here at all.
     */
    public function test_credit_card_is_rejected_by_patient_level_allocation(): void
    {
        $user    = $this->staff();
        $patient = $this->patient();
        $this->invoiceFor($patient, 28000);

        $this->assertNotContains('card', PatientPaymentAllocationService::ALLOWED_MODES);

        $this->actingAs($user)->post(route('billing.patientPayment', $patient), [
            'amount'       => 28000,
            'payment_mode' => 'card',
            'payment_date' => today()->toDateString(),
        ])->assertSessionHasErrors('payment_mode');

        // Nothing was written — no payment, and above all no zero-fee row.
        $this->assertSame(0, InvoicePayment::where('patient_id', $patient->id)->count());
    }

    /**
     * The FIFO allocator must never compute or write a convenience fee. If this
     * ever changes, the tender-level accounting question has to be answered
     * first — see the A3.x audit.
     */
    public function test_the_fifo_allocator_writes_no_convenience_fee(): void
    {
        $user    = $this->staff();
        $patient = $this->patient();
        $a = $this->invoiceFor($patient, 10000);
        $b = $this->invoiceFor($patient, 18000);

        $this->actingAs($user)->post(route('billing.patientPayment', $patient), [
            'amount'       => 28000,
            'payment_mode' => 'debit_card',   // allowed: carries no fee
            'payment_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rows = InvoicePayment::where('patient_id', $patient->id)->get();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(0, (float) $row->convenience_fee, 0.001);
        }
        $this->assertEqualsWithDelta(0, $a->fresh()->balance_due, 0.01);
        $this->assertEqualsWithDelta(0, $b->fresh()->balance_due, 0.01);
    }

    /** The FIFO form must not offer Credit Card or EMI, and must say why. */
    public function test_the_fifo_form_offers_neither_credit_card_nor_emi(): void
    {
        $user = $this->userWithTwoModulePerms(
            'finance',  [true, true, true],
            'patients', [true, true, true],
        );
        $user->update(['role' => 'admin']);
        $patient = $this->patient();
        $this->invoiceFor($patient, 28000);

        $html = $this->actingAs($user->fresh())
            ->get(route('patients.show', $patient))->assertOk()->getContent();

        $this->assertStringContainsString(
            'Credit Card payments are available when paying a single invoice', $html);

        // The FIFO picker is driven by ALLOWED_MODES, so assert the constant is
        // what reaches the page rather than scraping option tags out of two forms.
        $this->assertNotContains('card', PatientPaymentAllocationService::ALLOWED_MODES);
        $this->assertNotContains('emi',  PatientPaymentAllocationService::ALLOWED_MODES);
    }

    /** Debit Card became allocatable on 2026-08-19 (CEO approval). */
    public function test_debit_card_is_accepted_by_patient_level_allocation(): void
    {
        $user    = $this->staff();
        $patient = $this->patient();
        $invoice = $this->invoiceFor($patient, 1000);

        $this->actingAs($user)->post(route('billing.patientPayment', $patient), [
            'amount'       => 1000,
            'payment_mode' => 'debit_card',
            'payment_date' => today()->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEqualsWithDelta(0, $invoice->fresh()->balance_due, 0.01);
        $this->assertSame('debit_card', InvoicePayment::where('invoice_id', $invoice->id)
            ->firstOrFail()->payment_mode);
    }

    // ── The shared per-invoice form partial ─────────────────────────────────

    /**
     * Stage 1 of the extraction: billing/_invoice_panel renders its payment form
     * from billing.partials.record-payment-form. The partial is prefix-driven so
     * the panel's existing JavaScript still finds every element it binds to —
     * if these ids move, the panel's EMI and convenience-fee logic goes silently
     * dead, which no other assertion would catch.
     */
    public function test_the_shared_payment_partial_keeps_the_panels_element_ids(): void
    {
        $user    = $this->staff();
        $invoice = $this->invoiceFor($this->patient(), 5000);

        $html = $this->actingAs($user)
            ->get(route('billing.panel', $invoice))
            ->assertOk()
            ->getContent();

        foreach ([
            'id="panelPaymentForm"', 'id="pAmount"', 'id="pDate"', 'id="pMode"',
            'id="pFieldRef"', 'id="pFieldCC"', 'id="pFieldCheque"', 'id="pFieldEmi"',
            'id="pEmiType"', 'id="pEmiTenure"', 'id="pProviderSel"', 'id="pSchemeSel"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html,
                "The shared partial dropped {$needle} — the panel's JS binds to it.");
        }

        // Handlers must still be wired, not just the elements present.
        $this->assertStringContainsString('pOnModeChange()', $html);
        $this->assertStringContainsString('pOnAmountChange()', $html);

        // And it carries the canonical vocabulary, EMI included (invoice-scoped).
        $this->assertStringContainsString('Credit Card', $html);
        $this->assertStringContainsString('Debit Card', $html);
        $this->assertStringContainsString('>EMI<', $html);
        $this->assertStringNotContainsString('Net Banking', $html);
    }
}
