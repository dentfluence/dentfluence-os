<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Receipt;
use App\Models\EmiProvider;
use App\Models\AppSetting;
use App\Models\Patient;
use App\Models\Finance\FinanceBankAccount;
use App\Services\Billing\InvoicePaymentService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function __construct(private InvoicePaymentService $payments)
    {
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
     | Mirrors web BillingController::store(). Simplified: no coupon/wallet/
     | membership layers on mobile (those can be applied on web). Returns the
     | new invoice summary + id so the mobile can proceed to record payment.
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
        ]);

        // Verify the patient belongs to this user's branch.
        $patient = Patient::where('branch_id', $request->user()->branch_id)
            ->whereKey($request->patient_id)->first();
        if (! $patient) {
            return $this->error('Patient not found in your branch.', [], 404);
        }

        $invoiceId = null;

        DB::transaction(function () use ($request, &$invoiceId) {
            $invoice = Invoice::create([
                'invoice_number' => Invoice::nextNumber(),
                'patient_id'     => $request->patient_id,
                'invoice_date'   => $request->invoice_date,
                'due_date'       => $request->due_date,
                'discount_pct'   => $request->discount_pct ?? 0,
                'notes'          => $request->notes,
                'status'         => 'draft',
                'created_by'     => $request->user()->id,
            ]);

            foreach ($request->items as $i => $row) {
                $item = new InvoiceItem([
                    'invoice_id'   => $invoice->id,
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

            $invoice->recalculate();
            $invoiceId = $invoice->id;
        });

        $inv = Invoice::with('patient:id,branch_id,name,patient_id,phone')->find($invoiceId);

        return $this->success($this->invoiceSummary($inv), 'Invoice created.', 201);
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

        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_mode' => 'required|string',
            'notes'        => 'nullable|string|max:300',
        ]);

        (new WalletService())->credit(
            patientId:  $pt->id,
            amount:     (float) $request->amount,
            creditType: 'permanent',
            notes:      $request->notes ?? ('Advance payment via mobile — ' . $request->payment_mode),
            createdBy:  $request->user()->id,
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
