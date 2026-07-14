<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\BillingAuditLog;
use App\Models\BillingPrompt;
use App\Models\CouponCode;
use App\Models\EmiSchedule;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\EmiProvider;
use App\Models\AppSetting;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\StockMovement;
use App\Models\Patient;
use App\Models\RoleBillingPermission;
use App\Models\Finance\FinanceBankAccount;
use App\Models\Finance\FinanceTransaction;
use App\Models\TreatmentVisitItem;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Billing\EmiScheduleService;
use App\Services\Billing\InvoicePaymentService;
use App\Services\Billing\ManualDiscountService;
use App\Services\CouponService;
use App\Services\MembershipBenefitService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BillingController (API v1)
 * --------------------------
 * Mobile money-in flow — full parity with the web BillingController. Recording a
 * payment runs the SAME chain via InvoicePaymentService::recordPayment(), so the
 * InvoicePayment / Receipt / FinalBill / FinanceTransaction (+ EmiSchedule)
 * records are identical to the web. Everything is branch-scoped via the invoice's
 * patient — cross-branch access returns the enveloped 404.
 */
class BillingController extends ApiController
{
    private const PAYMENT_MODES = [
        ['value' => 'cash',          'label' => 'Cash'],
        ['value' => 'upi',           'label' => 'UPI'],
        ['value' => 'card',          'label' => 'Credit Card'],
        ['value' => 'debit_card',    'label' => 'Debit Card'],
        ['value' => 'netbanking',    'label' => 'Net Banking'],
        ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
        ['value' => 'cheque',        'label' => 'Cheque'],
        ['value' => 'emi',           'label' => 'EMI'],
        ['value' => 'other',         'label' => 'Other'],
    ];

    private const EMI_TENURES = [3, 6, 9, 12, 18, 24, 36, 48, 60];

    public function __construct(
        private InvoicePaymentService $payments,
        private EmiScheduleService $emiSchedules,
    ) {
    }

    /* =========================================================================
     | Form options for the Record-Payment screen
     |=========================================================================*/
    public function paymentOptions(Request $request, $invoice): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $invoiceTotal = (float) $inv->total_amount;

        // Active clinic bank/UPI accounts ("received in") — optional on payment.
        $accounts = FinanceBankAccount::where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('account_name')
            ->get()
            ->map(fn ($a) => [
                'id'           => $a->id,
                'account_name' => $a->account_name,
                'bank_name'    => $a->bank_name,
                'is_primary'   => (bool) $a->is_primary,
            ])->values();

        // Active EMI providers + their schemes with the breakdown for THIS invoice.
        $providers = EmiProvider::allActive()->map(function ($p) use ($invoiceTotal) {
            return [
                'id'      => $p->id,
                'name'    => $p->name,
                'schemes' => $p->activeSchemes->map(function ($s) use ($invoiceTotal) {
                    $b = $s->breakdown($invoiceTotal);
                    return [
                        'id'                   => $s->id,
                        'scheme_name'          => $s->scheme_name,
                        'tenure_months'        => $s->tenure_months,
                        'upfront_emis'         => $s->upfront_emis,
                        'patient_monthly_emi'  => $b['patient_monthly_emi'],
                        'patient_upfront_amount' => $b['patient_upfront_amount'],
                        'clinic_interest_cost' => $b['clinic_interest_cost'],
                        'gst_on_interest'      => $b['gst_on_interest'],
                        'provider_deduction'   => $b['total_provider_deduction'],
                        'clinic_net_amount'    => $b['clinic_net_amount'],
                        'pass_cost_to_patient' => $b['pass_cost_to_patient'],
                        'convenience_charge'   => $b['convenience_charge'],
                        'receipt_total'        => $b['receipt_total'],
                    ];
                })->values(),
            ];
        })->values();

        return $this->success([
            'invoice'                  => $this->invoiceSummary($inv),
            'payment_modes'            => self::PAYMENT_MODES,
            'emi_tenures'              => self::EMI_TENURES,
            'clinic_accounts'          => $accounts,
            'cc_convenience_threshold' => (float) AppSetting::get('cc_convenience_threshold', 10000),
            'cc_convenience_rate'      => (float) AppSetting::get('cc_convenience_rate', 2.5),
            'emi_providers'            => $providers,
            // Available wallet credit so the payment form can offer
            // "pay from wallet" (wallet_used) — same option the web form shows.
            'wallet'                   => (new WalletService())->summary($inv->patient_id),
        ], '');
    }

    /* =========================================================================
     | Record a payment (full parity, all modes incl. direct/provider EMI)
     |=========================================================================*/
    public function recordPayment(Request $request, $invoice): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        if ($inv->status === 'cancelled') {
            return $this->error('Cannot record payment on a cancelled invoice.', [], 422);
        }

        $mode    = $request->input('payment_mode');
        $emiType = $request->input('emi_type', 'direct');

        // ── Validation (base + mode-specific) — mirrors the web controller ────
        $rules = [
            'amount'            => 'required|numeric|min:0.01',
            'payment_mode'      => 'required|in:cash,card,debit_card,upi,cheque,netbanking,bank_transfer,emi,other',
            'payment_date'      => 'required|date',
            'clinic_account_id' => 'nullable|exists:finance_bank_accounts,id',
            'reference_no'      => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
            // Wallet allocation during payment — same rule as web recordPayment.
            // InvoicePaymentService already implements the debit; this rule was
            // the only thing stopping mobile from paying part of an invoice
            // from wallet credit (2026-07-14 parity fix).
            'wallet_used'       => 'nullable|numeric|min:0',
        ];

        if (in_array($mode, ['upi', 'netbanking', 'bank_transfer'])) {
            $rules['reference_no'] = 'required|string|max:100';
        }

        if ($mode === 'cheque') {
            $rules['bank_name']   = 'required|string|max:100';
            $rules['cheque_no']   = 'required|string|max:50';
            $rules['cheque_date'] = 'required|date';
        }

        if ($mode === 'card') {
            $rules['convenience_fee'] = 'nullable|numeric|min:0';
        }

        if ($mode === 'emi') {
            $rules['emi_type'] = 'required|in:direct,provider';

            if ($emiType === 'direct') {
                $rules['emi_provider']      = 'nullable|string|max:100';
                $rules['emi_tenure']        = 'required|integer|min:1|max:84';
                $rules['emi_interest_rate'] = 'required|numeric|min:0|max:36';
                $rules['emi_start_date']    = 'required|date|after_or_equal:today';
            } else {
                $rules['emi_provider_scheme_id'] = 'required|integer|exists:emi_schemes,id';
                $rules['emi_upfront_amount']     = 'nullable|numeric|min:0';
                $rules['convenience_fee']        = 'nullable|numeric|min:0';
            }
        }

        $data = $request->validate($rules);
        // Ensure emi_type is carried through for the service
        $data['emi_type'] = $emiType;

        $result = $this->payments->recordPayment($inv, $data, $request->user()->id);

        return $this->success([
            'message' => $result['message'],
            'invoice' => $this->invoiceSummary($result['invoice']->fresh()),
            'receipt' => $result['receipt'] ? $this->receiptPayload($result['receipt']->fresh()) : null,
        ], $result['message'], 201);
    }

    /* =========================================================================
     | Edit payment date  PATCH /invoices/{invoice}/payments/{payment}/date
     | Mirrors web BillingController::updatePayment — cascades to the linked
     | Receipt + FinanceTransaction via the shared InvoicePaymentService.
     |=========================================================================*/
    public function updatePaymentDate(Request $request, $invoice, $payment): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $pay = InvoicePayment::whereKey($payment)->first();
        if (! $pay || $pay->invoice_id !== $inv->id) {
            return $this->error('Payment does not belong to this invoice.', [], 404);
        }

        if ($inv->status === 'cancelled') {
            return $this->error('Cannot edit a payment on a cancelled invoice.', [], 422);
        }

        $data = $request->validate([
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $this->payments->updatePaymentDate($pay, $data['payment_date'], $request->user()->id);

        return $this->success(
            ['invoice' => $this->invoiceSummary($inv->fresh())],
            'Payment date updated.'
        );
    }

    /* =========================================================================
     | Mark Provider-EMI payment received (generates settlement receipt)
     |=========================================================================*/
    public function markProviderPaid(Request $request, $invoice, $payment): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $pmt = InvoicePayment::where('invoice_id', $inv->id)->whereKey($payment)->first();
        if (! $pmt) {
            return $this->error('Payment not found.', [], 404);
        }

        if ($pmt->emi_type !== 'provider') {
            return $this->error('This is not a Provider EMI payment.', [], 422);
        }
        if ($pmt->provider_paid_at !== null) {
            return $this->error('Provider payment has already been marked as received.', [], 422);
        }

        $data = $request->validate([
            'provider_paid_date' => 'required|date',
            'provider_reference' => 'nullable|string|max:100',
        ]);

        $receipt = $this->payments->markProviderPaid($inv, $pmt, $data, $request->user()->id);

        return $this->success([
            'message' => 'Provider payment marked as received. Settlement receipt generated for Rs. '
                         . number_format((float) $pmt->clinic_net_amount, 2) . '.',
            'receipt' => $this->receiptPayload($receipt->fresh()),
            'invoice' => $this->invoiceSummary($inv->fresh()),
        ], 'Provider payment marked as received.', 201);
    }

    /* =========================================================================
     | Direct-EMI instalment schedule — read + receivables "mark paid".
     |
     | Pure follow-up bookkeeping: does NOT touch invoice totals or the
     | Finance ledger (see EmiScheduleService docblock) — the full principal
     | was already booked as revenue when the Direct EMI payment was first
     | recorded, because "direct" EMI means the clinic itself finances the
     | patient and collects instalments outside the software.
     |=========================================================================*/
    public function emiSchedule(Request $request, $invoice, $payment): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $pmt = InvoicePayment::where('invoice_id', $inv->id)->whereKey($payment)->first();
        if (! $pmt) {
            return $this->error('Payment not found.', [], 404);
        }
        if ($pmt->emi_type !== 'direct') {
            return $this->error('This is not a Direct EMI payment.', [], 422);
        }

        $rows = $this->emiSchedules->listForPayment($pmt);

        return $this->success([
            'invoice_number'    => $inv->invoice_number,
            'emi_provider'      => $pmt->emi_provider,
            'emi_tenure'        => $pmt->emi_tenure,
            'emi_interest_rate' => (float) $pmt->emi_interest_rate,
            'schedule'          => $rows->map(fn ($r) => $this->emiRowPayload($r))->values(),
        ], '');
    }

    public function markEmiInstallmentPaid(Request $request, $invoice, $payment, $schedule): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $pmt = InvoicePayment::where('invoice_id', $inv->id)->whereKey($payment)->first();
        if (! $pmt || $pmt->emi_type !== 'direct') {
            return $this->error('Direct EMI payment not found.', [], 404);
        }

        $row = EmiSchedule::where('invoice_payment_id', $pmt->id)->whereKey($schedule)->first();
        if (! $row) {
            return $this->error('Instalment not found.', [], 404);
        }

        $data = $request->validate([
            'paid_date'         => 'required|date',
            'payment_reference' => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:300',
        ]);

        try {
            $row = $this->emiSchedules->markPaid(
                $row,
                $data['paid_date'],
                $data['payment_reference'] ?? null,
                $data['notes'] ?? null,
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        return $this->success($this->emiRowPayload($row), 'Instalment #' . $row->instalment_no . ' marked as collected.');
    }

    /* =========================================================================
     | Refund from wallet back to patient (cash/UPI/etc OUT) — mirrors web
     | Finance\WalletController::refund() exactly: capped at the permanent
     | balance, same WALLET_REFUND permission gate (admin bypasses), same
     | Finance mirror (type=refund/direction=debit) + audit log entry.
     | POST /api/v1/patients/{patient}/wallet/refund
     |=========================================================================*/
    public function refundWalletCredit(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        $user = $request->user();
        if (! $user->isAdminRole()) {
            $role = $user->roleModel;
            if (! $role || ! $role->billingCan(RoleBillingPermission::WALLET_REFUND)) {
                return $this->error('You do not have permission for this wallet action.', [], 403);
            }
        }

        $data = $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_mode' => 'required|in:cash,upi,bank_transfer,cheque,other',
            'refund_date'  => 'required|date',
            'reason'       => 'required|string|min:3|max:300',
        ]);

        $wallet = Wallet::forPatient($pt->id);
        if ((float) $data['amount'] > (float) $wallet->balance_permanent) {
            return $this->error(
                'Refund exceeds available wallet balance of Rs. ' . number_format($wallet->balance_permanent, 2) . '.',
                [], 422
            );
        }

        $withdrawn = 0.0;

        DB::transaction(function () use ($data, $pt, $request, &$withdrawn) {
            $withdrawn = (new WalletService())->withdraw(
                patientId:   $pt->id,
                amount:      (float) $data['amount'],
                paymentMode: $data['payment_mode'],
                notes:       'Refund: ' . $data['reason'],
                createdBy:   $request->user()->id,
            );

            if ($withdrawn <= 0) {
                return;
            }

            $tx = WalletTransaction::where('patient_id', $pt->id)
                ->where('source', 'withdrawal')->latest()->first();

            FinanceTransaction::create([
                'type'             => 'refund',
                'direction'        => 'debit',
                'source_type'      => WalletTransaction::class,
                'source_id'        => $tx?->id,
                'amount'           => $withdrawn,
                'net_amount'       => $withdrawn,
                'payment_mode'     => $data['payment_mode'],
                'patient_id'       => $pt->id,
                'status'           => 'active',
                'transaction_date' => $data['refund_date'],
                'notes'            => 'Wallet refund — ' . $data['reason'],
                'created_by'       => $request->user()->id,
            ]);

            if ($tx) {
                BillingAuditLog::record('wallet_refund', $tx,
                    'Refund Rs. ' . number_format($withdrawn, 2) . ' (' . $data['payment_mode'] . '). ' . $data['reason'],
                    $request->user()->id, 'Wallet · ' . $pt->name);
            }
        });

        if ($withdrawn <= 0) {
            return $this->error('Refund could not be applied (amount may exceed balance).', [], 422);
        }

        $wallet->refresh();

        return $this->success([
            'patient_id'      => $pt->id,
            'patient_name'    => $pt->name,
            'amount_refunded' => $withdrawn,
            'wallet_balance'  => (float) $wallet->balance_total,
        ], '₹' . number_format($withdrawn, 0) . ' refunded from ' . $pt->name . "'s wallet.", 201);
    }

    /* =========================================================================
     | Receipt detail (for the printable receipt PDF — mirrors receipt.blade)
     |=========================================================================*/
    public function receipt(Request $request, $invoice, $receipt): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $rcpt = Receipt::where('invoice_id', $inv->id)->whereKey($receipt)->first();
        if (! $rcpt) {
            return $this->error('Receipt not found.', [], 404);
        }

        $cs = AppSetting::whereIn('key', [
            'clinic_name', 'clinic_address', 'clinic_phone', 'clinic_email', 'clinic_gst_no',
        ])->pluck('value', 'key');

        return $this->success([
            'clinic'  => [
                'name'    => $cs->get('clinic_name') ?? config('app.name'),
                'address' => $cs->get('clinic_address'),
                'phone'   => $cs->get('clinic_phone'),
                'email'   => $cs->get('clinic_email'),
                'gstin'   => $cs->get('clinic_gst_no'),
            ],
            'receipt' => $this->receiptPayload($rcpt),
        ], '');
    }

    /* =========================================================================
     | A patient's open (unpaid / partial) invoices — for the Home "New Payment"
     | flow: pick patient -> pick open invoice -> record payment.
     |=========================================================================*/
    public function openInvoices(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        $rows = Invoice::where('patient_id', $pt->id)
            ->where('status', '!=', 'cancelled')
            ->where('balance_due', '>', 0)
            ->orderByDesc('invoice_date')
            ->get()
            ->map(fn ($i) => $this->invoiceSummary($i));

        return $this->success([
            'patient'  => [
                'id'         => $pt->id,
                'name'       => $pt->name,
                'patient_id' => $pt->patient_id,
                'phone'      => $pt->phone,
            ],
            'invoices' => $rows->values(),
        ], '');
    }

    /* =========================================================================
     | Clinic-wide billing list  (mobile Billing module)
     | GET /api/v1/billing/invoices?status=all|open|paid|cancelled&search=&limit=
     | Branch-scoped through the invoice's patient. Read-only, any staff.
     |=========================================================================*/
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;
        $status   = (string) $request->query('status', 'all');
        $search   = trim((string) $request->query('search', ''));

        $query = Invoice::with('patient:id,branch_id,name,patient_id,phone')
            ->whereHas('patient', fn ($q) => $q->where('branch_id', $branchId));

        // Status filter. "open" = anything still owing; "paid" = fully settled.
        switch ($status) {
            case 'open':
                $query->where('status', '!=', 'cancelled')
                      ->where('balance_due', '>', 0);
                break;
            case 'paid':
                $query->where('status', '!=', 'cancelled')
                      ->where('balance_due', '<=', 0);
                break;
            case 'cancelled':
                $query->where('status', 'cancelled');
                break;
            // 'all' → no extra filter.
        }

        // Search by invoice number OR patient name / phone / code.
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('patient_id', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderByDesc('invoice_date')->orderByDesc('id');

        $limit = max(1, min((int) $request->query('limit', 20), 100));
        $page  = $query->paginate($limit)->appends($request->query());

        return $this->success(
            collect($page->items())
                ->map(fn ($i) => $this->invoiceSummary($i))
                ->values(),
            '',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ]
        );
    }

    /* =========================================================================
     | Billing summary KPIs  (mobile Billing module header cards)
     | GET /api/v1/billing/summary
     |=========================================================================*/
    public function summary(Request $request): JsonResponse
    {
        $branchId = $request->user()->branch_id;

        // Money actually received (receipts) — scoped to this branch's patients.
        $receipts = fn () => Receipt::whereHas(
            'patient', fn ($q) => $q->where('branch_id', $branchId)
        );

        $todayCollection = (float) $receipts()
            ->whereDate('receipt_date', now()->toDateString())->sum('amount');

        $monthCollection = (float) $receipts()
            ->whereDate('receipt_date', '>=', now()->startOfMonth()->toDateString())
            ->sum('amount');

        // Outstanding / counts come from non-cancelled invoices.
        $invoices = fn () => Invoice::whereHas(
            'patient', fn ($q) => $q->where('branch_id', $branchId)
        )->where('status', '!=', 'cancelled');

        return $this->success([
            'today_collection' => $todayCollection,
            'month_collection' => $monthCollection,
            'outstanding'      => (float) $invoices()->sum('balance_due'),
            'open_count'       => $invoices()->where('balance_due', '>', 0)->count(),
            'invoice_count'    => $invoices()->count(),
        ], '');
    }

    /* =========================================================================
     | Create invoice  POST /api/v1/invoices
     | Full parity with web BillingController::store() — coupon, wallet,
     | membership and manual discount all stack exactly like the web (same
     | order: coupon -> membership -> wallet -> manual, each on top of the
     | previous via Invoice::recalculate()). One deliberate improvement over
     | web: coupon discount and membership discount are RECOMPUTED server-side
     | here instead of trusting a client-submitted rupee amount — web's hidden
     | form field pattern is a client-trust gap not worth reproducing on an API.
     |=========================================================================*/
    public function createInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id'           => 'required|integer|exists:patients,id',
            'invoice_date'         => 'required|date',
            'due_date'             => 'nullable|date|after_or_equal:invoice_date',
            'discount_pct'         => 'nullable|numeric|min:0|max:100',
            'notes'                => 'nullable|string|max:1000',
            'items'                => 'required|array|min:1',
            'items.*.description'  => 'required|string|max:200',
            'items.*.unit_price'   => 'required|numeric|min:0',
            'items.*.qty'          => 'required|integer|min:1',
            'items.*.disc_pct'     => 'nullable|numeric|min:0|max:100',
            'items.*.gst_pct'      => 'nullable|numeric|min:0|max:100',
            'items.*.tooth_number' => 'nullable|string|max:20',
            'items.*.treatment_id' => 'nullable|integer|exists:treatments,id',
            // Retail/FMCG product line (2026-07-06 web parity) — links a sold
            // is_sellable InventoryItem so applyRetailStockMovements() below
            // can auto-deduct stock the same way web billing does.
            'items.*.inventory_item_id' => 'nullable|integer|exists:inventory_items,id',
            // Discount layers (all optional, all stack)
            'coupon_code'                => 'nullable|string|max:50',
            'wallet_applied'             => 'nullable|numeric|min:0',
            'wallet_treatment_ids'       => 'nullable|array',
            'wallet_treatment_ids.*'     => 'integer',
            'apply_membership_discount' => 'nullable|boolean',
            'manual_discount_type'       => 'nullable|in:flat,percentage',
            'manual_discount_value'      => 'nullable|numeric|min:0.01',
            'manual_discount_reason'     => 'nullable|required_with:manual_discount_value|string|min:3|max:500',
            // Optional links back to the source that prompted this invoice
            'prompt_ids'                 => 'nullable|array',
            'prompt_ids.*'               => 'integer|exists:billing_prompts,id',
            'visit_item_ids'             => 'nullable|array',
            'visit_item_ids.*'           => 'integer',
        ]);

        // Verify the patient belongs to this user's branch.
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($request->patient_id)->first();
        if (! $patient) {
            return $this->error('Patient not found in your branch.', [], 404);
        }

        $invoiceId = null;

        DB::transaction(function () use ($request, $patient, &$invoiceId) {
            $invoice = Invoice::create([
                'invoice_number' => Invoice::nextNumber(),
                'patient_id'     => $patient->id,
                'invoice_date'   => $request->invoice_date,
                'due_date'       => $request->due_date,
                'discount_pct'   => $request->discount_pct ?? 0,
                'notes'          => $request->notes,
                'status'         => 'draft',
                'created_by'     => $request->user()->id,
            ]);

            foreach ($request->items as $i => $row) {
                $item = new InvoiceItem([
                    'invoice_id'        => $invoice->id,
                    'treatment_id'      => $row['treatment_id'] ?? null,
                    'inventory_item_id' => $row['inventory_item_id'] ?? null,
                    'description'  => $row['description'],
                    'tooth_number' => $row['tooth_number'] ?? null,
                    'unit_price'   => $row['unit_price'],
                    'qty'          => $row['qty'],
                    'disc_pct'     => $row['disc_pct'] ?? 0,
                    'gst_pct'      => $row['gst_pct'] ?? 0,
                    'sort_order'   => $i,
                ]);
                $item->compute();
                $item->save();
            }

            // Subtotal now reflects the real saved items — every discount below
            // is computed against this, never a client-submitted number.
            $invoice->recalculate();

            // Deduct stock for any retail/FMCG product lines (2026-07-06 web
            // parity) — no-op if none of the items carry inventory_item_id.
            $this->applyRetailStockMovements($invoice);

            // ── Coupon ────────────────────────────────────────────────────────
            $couponId = null;
            if ($request->filled('coupon_code')) {
                $coupon = CouponCode::active()
                    ->where('code', strtoupper(trim($request->coupon_code)))
                    ->first();
                if (! $coupon) {
                    throw ValidationException::withMessages(['coupon_code' => 'Invalid or expired coupon code.']);
                }
                if (! $coupon->canBeUsedByPatient($patient->id)) {
                    throw ValidationException::withMessages(['coupon_code' => 'This coupon has already been used the maximum number of times for this patient.']);
                }
                if ((float) $invoice->subtotal < (float) $coupon->min_invoice_amount) {
                    throw ValidationException::withMessages(['coupon_code' => 'Minimum invoice amount for this coupon is Rs. ' . number_format($coupon->min_invoice_amount, 0) . '.']);
                }
                $couponDiscount = $coupon->calculateDiscount((float) $invoice->subtotal);
                if ($couponDiscount > 0) {
                    $invoice->update(['coupon_id' => $coupon->id, 'coupon_discount' => $couponDiscount]);
                    $couponId = $coupon->id;
                }
            }

            // ── Membership discount (recomputed server-side; staff opts in) ────
            $membershipBenefit = null;
            if ($request->boolean('apply_membership_discount')) {
                $invoice->refresh();
                // FMCG/retail product lines (inventory_item_id set) never receive AOCP
                // membership benefits — any discount on those is manual, entered
                // directly on the invoice line. Only treatment/procedure lines count.
                $eligibleItems = $invoice->items->whereNull('inventory_item_id');
                $lineItems = $eligibleItems->map(fn ($it) => [
                    'name'   => $it->description,
                    'amount' => (float) $it->unit_price,
                    'qty'    => (int) $it->qty,
                ])->all();
                $eligibleSubtotal = (float) $eligibleItems->sum(fn ($it) => (float) $it->unit_price * (int) $it->qty);
                $membershipBenefit = MembershipBenefitService::forPatient($patient->id, $lineItems, $eligibleSubtotal);
                if (($membershipBenefit['active'] ?? false) && ($membershipBenefit['discount'] ?? 0) > 0) {
                    $invoice->update([
                        'membership_id'       => $membershipBenefit['membership_id'],
                        'membership_discount' => $membershipBenefit['discount'],
                    ]);
                }
            }

            $invoice->recalculate();

            // ── Wallet (capped server-side — never trust a client rupee amount) ─
            if ($request->filled('wallet_applied') && (float) $request->wallet_applied > 0) {
                $invoice->refresh();
                $wallet    = Wallet::forPatient($patient->id);
                $requested = (float) $request->wallet_applied;
                $cap       = min($requested, (float) $wallet->balance_total, (float) $invoice->balance_due);
                if ($cap > 0) {
                    $debited = (new WalletService())->debit(
                        patientId:    $patient->id,
                        amount:       $cap,
                        invoiceId:    $invoice->id,
                        createdBy:    $request->user()->id,
                        treatmentIds: array_map('intval', (array) $request->input('wallet_treatment_ids', [])),
                    );
                    if ($debited > 0) {
                        $invoice->update(['wallet_applied' => $debited]);
                    }
                }
            }

            $invoice->recalculate();

            // ── Mark billing prompts / visit items as invoiced ──────────────────
            if ($request->filled('prompt_ids')) {
                BillingPrompt::whereIn('id', $request->prompt_ids)
                    ->where('patient_id', $patient->id)
                    ->where('status', 'pending')
                    ->each(fn ($p) => $p->markInvoiced($invoice, $request->user()->id));
            }
            if ($request->filled('visit_item_ids')) {
                TreatmentVisitItem::whereIn('id', $request->visit_item_ids)
                    ->where('patient_id', $patient->id)
                    ->update(['billing_status' => 'invoiced']);
            }

            // ── Manual discount — applied last, own authorization + audit trail ─
            // Throws ValidationException on a permission/limit violation, which
            // rolls back this entire transaction (nothing partially saved).
            if ($request->filled('manual_discount_value') && (float) $request->manual_discount_value > 0) {
                (new ManualDiscountService())->apply(
                    $invoice->fresh(),
                    $request->manual_discount_type ?? 'flat',
                    (float) $request->manual_discount_value,
                    (string) $request->manual_discount_reason,
                    $request->user(),
                );
            }

            // ── Coupon usage + membership benefit log — recorded once final ─────
            if ($couponId) {
                $invoice->refresh();
                (new CouponService())->apply(
                    couponId:       $couponId,
                    patientId:      $patient->id,
                    invoiceId:      $invoice->id,
                    discountAmount: (float) $invoice->coupon_discount,
                    createdBy:      $request->user()->id,
                );
            }
            if ($membershipBenefit) {
                MembershipBenefitService::logFromResult($membershipBenefit, $invoice->id);
            }

            $invoiceId = $invoice->id;
        });

        $inv = Invoice::with('patient:id,branch_id,name,patient_id,phone')->find($invoiceId);

        return $this->success($this->invoiceSummary($inv), 'Invoice created.', 201);
    }

    /* =========================================================================
     | Validate a coupon code live (before creating the invoice)
     | GET /api/v1/coupons/validate?code=..&patient_id=..&subtotal=..
     |=========================================================================*/
    public function validateCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'       => 'required|string|max:50',
            'patient_id' => 'required|integer|exists:patients,id',
            'subtotal'   => 'required|numeric|min:0',
        ]);

        $result = (new CouponService())->validate($data['code'], (int) $data['patient_id'], (float) $data['subtotal']);

        return $this->success($result, '');
    }

    /* =========================================================================
     | Preview membership benefit for a patient + running line items — read-only,
     | so mobile can show "Apply membership discount (Rs. X)" before committing.
     | GET /api/v1/patients/{patient}/membership-benefit-preview
     |=========================================================================*/
    public function membershipBenefitPreview(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        $data = $request->validate([
            'items'                      => 'nullable|array',
            'items.*.name'               => 'required_with:items|string',
            'items.*.amount'             => 'required_with:items|numeric|min:0',
            'items.*.qty'                => 'nullable|integer|min:1',
            'items.*.inventory_item_id'  => 'nullable|integer',
            'subtotal'                   => 'nullable|numeric|min:0',
        ]);

        // FMCG/retail product rows (carry an inventory_item_id) never receive AOCP
        // membership benefits — any discount on those is manual, entered directly
        // on the invoice row. Only treatment/procedure rows count toward the preview.
        $eligibleRows = collect($data['items'] ?? [])->reject(fn ($i) => !empty($i['inventory_item_id']));
        $lineItems = $eligibleRows->map(fn ($i) => [
            'name'   => $i['name'],
            'amount' => (float) $i['amount'],
            'qty'    => (int) ($i['qty'] ?? 1),
        ])->all();
        // Fall back to the client-supplied subtotal only when no items were sent at all
        // (e.g. an initial "is membership active" check before any lines exist).
        $eligibleSubtotal = $data['items'] ?? null
            ? (float) $eligibleRows->sum(fn ($i) => (float) $i['amount'] * (int) ($i['qty'] ?? 1))
            : (float) ($data['subtotal'] ?? 0);

        $benefit = MembershipBenefitService::forPatient($pt->id, $lineItems, $eligibleSubtotal);

        return $this->success($benefit, '');
    }

    /* =========================================================================
     | Manual discount on an EXISTING (already-created) invoice — apply / remove.
     | Permission + role limit enforced inside ManualDiscountService; a violation
     | throws ValidationException -> standard 422 envelope.
     |=========================================================================*/
    public function applyManualDiscount(Request $request, $invoice): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $data = $request->validate([
            'manual_discount_type'   => 'required|in:flat,percentage',
            'manual_discount_value'  => 'required|numeric|min:0.01',
            'manual_discount_reason' => 'required|string|min:3|max:500',
        ]);

        (new ManualDiscountService())->apply(
            $inv,
            $data['manual_discount_type'],
            (float) $data['manual_discount_value'],
            $data['manual_discount_reason'],
            $request->user(),
        );

        return $this->success($this->invoiceSummary($inv->fresh()), 'Manual discount applied.');
    }

    public function removeManualDiscount(Request $request, $invoice): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        (new ManualDiscountService())->remove($inv, $request->user(), $data['reason'] ?? 'Manual discount removed');

        return $this->success($this->invoiceSummary($inv->fresh()), 'Manual discount removed.');
    }

    /* =========================================================================
     | Cancel invoice / void a receipt — admin-only, full refund handling.
     | Mirrors web BillingController::cancelInvoice()/voidReceipt() exactly,
     | including the 2.5% card-refund charge and FinalBill auto-invalidation.
     |=========================================================================*/
    public function cancelInvoice(Request $request, $invoice): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        if (! $request->user()->isAdminRole()) {
            return $this->error('Only admins can cancel invoices.', [], 403);
        }

        $data = $request->validate([
            'cancelled_reason'     => 'required|string|min:5|max:500',
            'cancel_refund_method' => 'required|in:wallet,cash,bank_transfer,no_refund',
        ]);

        DB::transaction(function () use ($request, $inv, $data) {
            BillingAuditLog::record(
                'cancel_invoice', $inv,
                $data['cancelled_reason'] . ' [refund: ' . $data['cancel_refund_method'] . ']',
                $request->user()->id, $inv->invoice_number,
            );

            foreach ($inv->receipts()->whereNull('deleted_at')->get() as $receipt) {
                $this->refundReceipt($receipt, $inv, $data['cancel_refund_method'],
                    'Invoice ' . $inv->invoice_number . ' cancelled. Reason: ' . $data['cancelled_reason'],
                    $request->user()->id, $data['cancelled_reason']);
            }

            if ($inv->finalBill) {
                $inv->finalBill->update([
                    'deleted_reason' => 'Invoice ' . $inv->invoice_number . ' cancelled. Reason: ' . $data['cancelled_reason'],
                    'deleted_by'     => $request->user()->id,
                ]);
                $inv->finalBill->delete();
            }

            // Reverse any retail-sale stock deductions before the invoice is
            // cancelled — mirrors web BillingController (2026-07-06 parity).
            $this->reverseRetailStockMovements($inv);

            // Reverse any wallet credit debited against this invoice — without
            // this, cancelling a wallet-part-paid invoice from mobile silently
            // lost the patient's credit (2026-07-14 parity fix, shared brain).
            (new WalletService())->reverseInvoiceDebit(
                $inv,
                'invoice ' . $inv->invoice_number . ' cancelled. ' . $data['cancelled_reason'],
                $request->user()->id,
            );

            $inv->update([
                'status'           => 'cancelled',
                'cancelled_reason' => $data['cancelled_reason'],
                'cancelled_by'     => $request->user()->id,
            ]);
            $inv->delete(); // soft-delete, matches web (moves to Trash)
        });

        return $this->success(null, 'Invoice ' . $inv->invoice_number . ' cancelled.');
    }

    // ── Retail stock sync (2026-07-06 web parity) ──────────────────────────────
    // A patient invoice can carry retail product lines (toothpaste, brushes,
    // OTC medicines — is_sellable items). Selling one auto-deducts stock via a
    // StockMovement row, exactly like web BillingController — see that class
    // for the canonical version this mirrors.

    /**
     * Deduct stock for every sellable-product line currently on this invoice.
     * Safe to call on an invoice with no product lines — it simply does nothing.
     */
    private function applyRetailStockMovements(Invoice $invoice): void
    {
        $lines = $invoice->items()->whereNotNull('inventory_item_id')->get();
        if ($lines->isEmpty()) {
            return;
        }

        $location = InventoryLocation::where('type', 'main_store')->where('is_active', true)->first()
            ?? InventoryLocation::where('is_active', true)->first();
        if (! $location) {
            return; // no location configured yet — never block the billing save on this
        }

        foreach ($lines as $line) {
            $item = InventoryItem::find($line->inventory_item_id);
            if (! $item || ! $item->is_sellable) {
                continue; // item was deleted or un-marked sellable since the line was added
            }

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'retail_sale',
                'qty'               => -$line->qty,
                'from_location_id'  => $location->id,
                'unit_cost'         => $item->average_purchase_price,
                'total_cost'        => round($item->average_purchase_price * $line->qty, 2),
                'reference_type'    => Invoice::class,
                'reference_id'      => $invoice->id,
                'notes'             => 'Retail sale — invoice ' . $invoice->invoice_number . ' (mobile)',
                'created_by'        => auth()->id(),
            ]);
        }
    }

    /**
     * Reverse every retail-sale deduction previously recorded against this
     * invoice via compensating stock-in entries. Call before an invoice is
     * cancelled.
     */
    private function reverseRetailStockMovements(Invoice $invoice): void
    {
        $priorSales = StockMovement::where('reference_type', Invoice::class)
            ->where('reference_id', $invoice->id)
            ->where('movement_type', 'retail_sale')
            ->get();

        foreach ($priorSales as $movement) {
            StockMovement::create([
                'inventory_item_id' => $movement->inventory_item_id,
                'movement_type'     => 'stock_in',
                'qty'               => abs($movement->qty),
                'to_location_id'    => $movement->from_location_id,
                'unit_cost'         => $movement->unit_cost,
                'total_cost'        => $movement->total_cost,
                'reference_type'    => Invoice::class,
                'reference_id'      => $invoice->id,
                'notes'             => 'Reversal — invoice ' . $invoice->invoice_number . ' cancelled (mobile)',
                'created_by'        => auth()->id(),
            ]);
        }
    }

    public function voidReceipt(Request $request, $invoice, $receipt): JsonResponse
    {
        $inv = $this->findInvoice($request, $invoice);
        if ($inv instanceof JsonResponse) return $inv;

        if (! $request->user()->isAdminRole()) {
            return $this->error('Only admins can void receipts.', [], 403);
        }

        $rcpt = Receipt::where('invoice_id', $inv->id)->whereKey($receipt)->first();
        if (! $rcpt) {
            return $this->error('Receipt not found.', [], 404);
        }

        $data = $request->validate([
            'void_reason'        => 'required|string|min:5|max:500',
            'void_refund_method' => 'required|in:wallet,cash,bank_transfer,no_refund',
        ]);

        DB::transaction(function () use ($request, $inv, $rcpt, $data) {
            BillingAuditLog::record(
                'void_receipt', $rcpt,
                $data['void_reason'] . ' [refund: ' . $data['void_refund_method'] . ']',
                $request->user()->id, $rcpt->receipt_number,
            );

            $this->refundReceipt($rcpt, $inv, $data['void_refund_method'],
                'Voided receipt ' . $rcpt->receipt_number . '. Reason: ' . $data['void_reason'],
                $request->user()->id, $data['void_reason']);

            $inv->refresh();
            $inv->recalculate();
            $inv->refresh();
            if ($inv->status !== 'paid' && $inv->finalBill) {
                $inv->finalBill->update([
                    'deleted_reason' => 'Auto-invalidated: linked receipt ' . $rcpt->receipt_number . ' was voided. Reason: ' . $data['void_reason'],
                    'deleted_by'     => $request->user()->id,
                ]);
                $inv->finalBill->delete();
            }
        });

        return $this->success($this->invoiceSummary($inv->fresh()), 'Receipt ' . $rcpt->receipt_number . ' voided.');
    }

    /* =========================================================================
     | Billing Prompts — auto-raised when a treatment visit item is saved
     | (TreatmentVisitService), consumed here to pre-fill/create an invoice.
     |=========================================================================*/
    public function pendingBillingPrompts(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        $rows = BillingPrompt::where('patient_id', $pt->id)
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id'          => $p->id,
                'trigger_type' => $p->trigger_type,
                'trigger_id'   => $p->trigger_id,
                'description'  => $p->description,
                'created_at'   => $p->created_at,
            ]);

        return $this->success($rows->values(), '');
    }

    /** Pending (not-yet-invoiced) visit items for a prompt's triggering visit. */
    public function billingPromptFormOptions(Request $request, $prompt): JsonResponse
    {
        $p = BillingPrompt::where('status', 'pending')->whereKey($prompt)->first();
        if (! $p) {
            return $this->error('Billing prompt not found or already resolved.', [], 404);
        }

        $items = TreatmentVisitItem::where('treatment_visit_id', $p->trigger_id)
            ->where('billing_status', 'pending')
            ->get()
            ->map(fn ($it) => [
                'id'             => $it->id,
                'treatment_name' => $it->treatment_name,
                'tooth_number'   => $it->tooth_number,
                'suggested_price'=> (float) ($it->suggested_price ?? 0),
            ]);

        return $this->success([
            'prompt'      => ['id' => $p->id, 'patient_id' => $p->patient_id, 'description' => $p->description],
            'visit_items' => $items->values(),
        ], '');
    }

    public function dismissBillingPrompt(Request $request, $prompt): JsonResponse
    {
        $p = BillingPrompt::where('status', 'pending')->whereKey($prompt)->first();
        if (! $p) {
            return $this->error('Billing prompt not found or already resolved.', [], 404);
        }

        $p->dismiss($request->user()->id);

        return $this->success(null, 'Billing prompt dismissed.');
    }

    /* =========================================================================
     | Add advance / wallet credit  POST /api/v1/patients/{patient}/wallet/credit
     | Adds a "permanent" wallet credit (advance payment) to the patient's wallet.
     | Simple mobile use-case: patient pays cash/UPI up-front → credit wallet.
     |=========================================================================*/
    public function addWalletCredit(Request $request, $patient): JsonResponse
    {
        $pt = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($patient)->first();
        if (! $pt) {
            return $this->error('Patient not found.', [], 404);
        }

        // Same permission gate as web receiveAdvance (ADVANCE_ADJUSTMENT,
        // admin bypass) — this endpoint was previously ungated.
        $user = $request->user();
        if (! $user->isAdminRole()) {
            $role = $user->roleModel;
            if (! $role || ! $role->billingCan(RoleBillingPermission::ADVANCE_ADJUSTMENT)) {
                return $this->error('You do not have permission for this wallet action.', [], 403);
            }
        }

        // Validation identical to web Finance\WalletController::receiveAdvance.
        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_mode' => 'required|in:cash,card,debit_card,upi,cheque,netbanking,bank_transfer,other',
            'payment_date' => 'required|date',
            'notes'        => 'nullable|string|max:300',
        ]);

        // Shared brain — wallet deposit + FinanceTransaction income mirror +
        // billing audit. Previously this path skipped the finance ledger
        // entirely, so mobile advances were invisible to the cashbook.
        (new WalletService())->receiveAdvance(
            patient:     $pt,
            amount:      (float) $request->amount,
            paymentMode: $request->payment_mode,
            paymentDate: $request->payment_date,
            notes:       $request->notes,
            createdBy:   $user->id,
        );

        // Reload wallet balance after the credit.
        $wallet = \App\Models\Wallet::forPatient($pt->id);

        return $this->success([
            'patient_id'      => $pt->id,
            'patient_name'    => $pt->name,
            'amount_credited' => (float) $request->amount,
            'wallet_balance'  => (float) $wallet->balance_total,
        ], '₹' . number_format($request->amount, 0) . ' advance credited to wallet.', 201);
    }

    /* =========================================================================
     | Helpers
     |=========================================================================*/
    private function findInvoice(Request $request, $id)
    {
        $inv = Invoice::with('patient:id,branch_id,name,patient_id,phone')
            ->whereKey($id)->first();

        if (! $inv || ! $inv->patient ||
            (int) $inv->patient->branch_id !== (int) $request->user()->branch_id) {
            return $this->error('Invoice not found.', [], 404);
        }
        return $inv;
    }

    private function invoiceSummary(Invoice $i): array
    {
        return [
            'id'           => $i->id,
            'number'       => $i->invoice_number,
            'date'         => $i->invoice_date,
            'status'       => $i->status,
            'total_amount' => (float) $i->total_amount,
            'paid_amount'  => (float) $i->paid_amount,
            'balance_due'  => (float) $i->balance_due,
            'patient'      => $i->relationLoaded('patient') && $i->patient ? [
                'id'    => $i->patient->id,
                'name'  => $i->patient->name,
                'phone' => $i->patient->phone,
            ] : null,
        ];
    }

    /** Card/debit-card refunds via bank_transfer attract a 2.5% clinic charge. */
    private function refundChargeRate(string $paymentMode): float
    {
        return match ($paymentMode) {
            'card', 'debit_card' => 2.5,
            default              => 0.0,
        };
    }

    /**
     * Shared refund/void logic for one receipt — soft-deletes the Receipt +
     * its InvoicePayment (with void audit fields), reverses the original
     * FinanceTransaction, and creates the refund-side FinanceTransaction per
     * the chosen method. Used by both cancelInvoice() (loops every receipt)
     * and voidReceipt() (single receipt). Mirrors web exactly.
     */
    private function refundReceipt(Receipt $receipt, Invoice $invoice, string $method, string $notes, int $userId, string $reason): void
    {
        $amount    = (float) $receipt->amount;
        $patientId = $invoice->patient_id;

        $chargeRate     = $method === 'bank_transfer' ? $this->refundChargeRate($receipt->payment_mode) : 0.0;
        $chargeDeducted = round($amount * $chargeRate / 100, 2);
        $refundAmount   = $amount - $chargeDeducted;

        if ($receipt->invoice_payment_id) {
            $payment = InvoicePayment::find($receipt->invoice_payment_id);
            if ($payment) {
                $payment->update([
                    'void_reason'          => $reason,
                    'voided_by'            => $userId,
                    'void_refund_method'   => $method,
                    'void_refund_amount'   => $refundAmount,
                    'void_charge_deducted' => $chargeDeducted,
                ]);
                FinanceTransaction::where('source_type', InvoicePayment::class)
                    ->where('source_id', $payment->id)
                    ->where('status', 'active')
                    ->update(['status' => 'voided']);
                $payment->delete();
            }
        }

        $receipt->delete();

        if ($method === 'no_refund') {
            FinanceTransaction::create([
                'type' => 'refund', 'direction' => 'debit', 'source_type' => Receipt::class,
                'source_id' => $receipt->id, 'amount' => $amount, 'net_amount' => 0,
                'payment_mode' => $receipt->payment_mode, 'patient_id' => $patientId,
                'status' => 'active', 'transaction_date' => now()->toDateString(),
                'notes' => 'No refund issued — ' . $notes, 'created_by' => $userId,
            ]);
            return;
        }

        if ($method === 'wallet') {
            (new WalletService())->credit(
                patientId: $patientId, amount: $amount, creditType: 'permanent',
                notes: 'Wallet credit — ' . $notes, createdBy: $userId,
            );
        }

        $paymentModeOut = $method === 'cash' ? 'cash' : $receipt->payment_mode;
        $notePrefix = match ($method) {
            'wallet' => 'Wallet credit',
            'cash'   => 'Cash refund',
            'bank_transfer' => $chargeDeducted > 0
                ? 'Bank transfer refund (Rs. ' . number_format($refundAmount, 2) . ' to patient, Rs. ' . number_format($chargeDeducted, 2) . ' clinic charge)'
                : 'Bank transfer refund (no charge)',
            default => ucfirst($method) . ' refund',
        };

        FinanceTransaction::create([
            'type' => 'refund', 'direction' => 'debit', 'source_type' => Receipt::class,
            'source_id' => $receipt->id, 'amount' => $amount,
            'net_amount' => $method === 'bank_transfer' ? $refundAmount : $amount,
            'payment_mode' => $paymentModeOut, 'patient_id' => $patientId,
            'status' => 'active', 'transaction_date' => now()->toDateString(),
            'notes' => $notePrefix . ' — ' . $notes, 'created_by' => $userId,
        ]);
    }

    private function emiRowPayload(EmiSchedule $r): array
    {
        return [
            'id'                => $r->id,
            'instalment_no'     => $r->instalment_no,
            'due_date'          => $r->due_date,
            'principal'         => (float) $r->principal,
            'interest'          => (float) $r->interest,
            'emi_amount'        => (float) $r->emi_amount,
            'status'            => $r->status,
            'paid_date'         => $r->paid_date,
            'payment_reference' => $r->payment_reference,
            'notes'             => $r->notes,
        ];
    }

    private function receiptPayload(Receipt $r): array
    {
        $r->loadMissing(['invoice:id,invoice_number,invoice_date', 'patient:id,name,phone']);

        return [
            'id'                 => $r->id,
            'receipt_number'     => $r->receipt_number,
            'receipt_date'       => $r->receipt_date,
            'amount'             => (float) $r->amount,
            'payment_mode'       => $r->payment_mode,
            'reference_no'       => $r->reference_no,
            'invoice_total'      => (float) $r->invoice_total,
            'amount_paid_before' => (float) $r->amount_paid_before,
            'balance_after'      => (float) $r->balance_after,
            'notes'              => $r->notes,
            'receipt_type'       => $r->receipt_type,
            'invoice_number'     => $r->invoice?->invoice_number,
            'invoice_date'       => $r->invoice?->invoice_date,
            'patient_name'       => $r->patient?->name,
            'patient_phone'      => $r->patient?->phone,
        ];
    }
}
