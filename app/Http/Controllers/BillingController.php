<?php

namespace App\Http\Controllers;

use App\Models\BillingAuditLog;
use App\Models\EmiProvider;
use App\Models\EmiScheme;
use App\Models\EmiSchedule;
use App\Models\BillingPrompt;
use App\Models\CouponCode;
use App\Models\CouponUsage;
use App\Models\FinalBill;
use App\Models\Finance\FinanceBankAccount;
use App\Models\Finance\FinanceMembershipPlan;
use App\Models\Finance\FinanceTransaction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Receipt;
use App\Models\Treatment;
use App\Models\TreatmentPlan;
use App\Models\TreatmentVisitItem;
use App\Services\Billing\TreatmentPlanBillingService;
use App\Models\Wallet;
use App\Services\MembershipBenefitService;
use App\Services\WalletService;
use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BillingController extends Controller
{
    // ── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Invoice::with('patient')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('patient', fn($p) => $p->where('name', 'like', "%{$s}%")
                                                      ->orWhere('phone', 'like', "%{$s}%"))
                  ->orWhere('invoice_number', 'like', "%{$s}%");
            });
        }

        $invoices = $query->paginate(20)->withQueryString();

        $summary = Invoice::selectRaw("
            COUNT(*) as total,
            SUM(total_amount) as total_billed,
            SUM(paid_amount) as total_collected,
            SUM(balance_due) as total_outstanding
        ")->first();

        return view('billing.index', compact('invoices', 'summary'));
    }

    // ── Create (generic) ────────────────────────────────────────────────────

    public function create(Request $request)
    {
        $patients        = Patient::orderBy('name')->get(['id', 'name', 'phone']);
        $treatments      = Treatment::where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_price', 'gst_pct', 'unit_basis']);
        $invoice         = null;
        $selectedPatient = $request->filled('patient_id') ? Patient::find($request->patient_id) : null;
        $preloadedItems  = collect();
        $pendingPrompts  = collect();
        $wallet          = null;

        $membershipInfo = null;

        if ($selectedPatient) {
            $pendingPrompts = BillingPrompt::with('invoice')
                ->forPatient($selectedPatient->id)
                ->pending()
                ->latest()
                ->get();
            $wallet = Wallet::forPatient($selectedPatient->id);
            $membershipInfo = MembershipBenefitService::forPatient($selectedPatient->id);
        }

        return view('billing.form', compact(
            'patients', 'treatments', 'invoice', 'selectedPatient',
            'preloadedItems', 'pendingPrompts', 'wallet', 'membershipInfo'
        ));
    }

    // ── Create from Billing Prompt ───────────────────────────────────────────
    // Front desk clicks "Build Invoice" on a pending billing prompt.
    // We open the invoice form as an EDITABLE DRAFT, pre-filled with the visit's
    // line items (added automatically as rows — no manual treatment picking).
    // Staff can tweak prices/qty/discounts, then hit Save to create the invoice.

    public function createFromPrompt(Patient $patient, BillingPrompt $prompt)
    {
        if ($prompt->patient_id !== $patient->id) {
            abort(403, 'Prompt does not belong to this patient.');
        }

        // Already invoiced/dismissed? Don't reopen — jump to the linked invoice
        // if there is one, otherwise return to the patient.
        if ($prompt->status !== 'pending') {
            if ($prompt->invoice_id) {
                return redirect()->route('billing.show', $prompt->invoice_id)
                    ->with('info', 'This prompt was already invoiced.');
            }
            return redirect()->route('patients.show', $patient)
                ->with('error', 'This billing prompt is no longer pending.');
        }

        // Pull the pending line items recorded against the triggering visit.
        // Prompts fired by a completed visit use trigger_type 'treatment_visit'
        // with trigger_id = treatment_visit.id. These pre-fill the form as rows.
        $preloadedItems = collect();
        if ($prompt->trigger_type === 'treatment_visit' && $prompt->trigger_id) {
            $preloadedItems = TreatmentVisitItem::where('patient_id', $patient->id)
                ->where('treatment_visit_id', $prompt->trigger_id)
                ->where('billing_status', 'pending')
                ->get();
        }

        $patients   = Patient::orderBy('name')->get(['id', 'name', 'phone']);
        $treatments = Treatment::where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_price', 'gst_pct', 'unit_basis']);
        $invoice    = null;

        // All pending prompts for this patient (so the form can show context)
        $pendingPrompts = BillingPrompt::forPatient($patient->id)->pending()->latest()->get();

        // Wallet balance
        $wallet = Wallet::forPatient($patient->id);

        // Membership auto-apply (recalculated client-side as items change)
        $membershipInfo = MembershipBenefitService::forPatient($patient->id);

        $selectedPatient = $patient;

        return view('billing.form', compact(
            'patients', 'treatments', 'invoice',
            'selectedPatient', 'preloadedItems', 'pendingPrompts',
            'wallet', 'prompt', 'membershipInfo'
        ));
    }

    // ── Dismiss Prompt ──────────────────────────────────────────────────────

    public function dismissPrompt(BillingPrompt $prompt)
    {
        $prompt->dismiss(auth()->id());

        return back()->with('success', 'Billing prompt dismissed.');
    }

    // ── AJAX: Validate Coupon ────────────────────────────────────────────────
    // Called by the invoice form via fetch() when the user enters a code.
    // Returns JSON: { valid, discount_type, discount_value, label, error? }

    public function validateCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string', 'subtotal' => 'required|numeric|min:0']);

        $coupon = CouponCode::active()->where('code', strtoupper(trim($request->code)))->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'error' => 'Coupon code not found or expired.']);
        }

        $patientId = $request->patient_id;
        if ($patientId && !$coupon->canBeUsedByPatient((int) $patientId)) {
            return response()->json(['valid' => false, 'error' => 'This coupon has already been used the maximum number of times for this patient.']);
        }

        $discountAmount = $coupon->calculateDiscount((float) $request->subtotal);

        return response()->json([
            'valid'          => true,
            'coupon_id'      => $coupon->id,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'discount_amount'=> $discountAmount,
            'label'          => $coupon->discountLabel(),
        ]);
    }

    // ── Bill from Treatment Plan (partial multi-tooth) ───────────────────────
    // Opens a screen listing the plan's items with a checkbox per PENDING tooth.
    // The user ticks only the teeth completed this visit; the rest stay pending
    // on the plan until a later visit.

    public function billFromPlan(TreatmentPlan $plan, TreatmentPlanBillingService $service)
    {
        $plan->load(['patient', 'items.teeth']);

        // Lazily create per-tooth rows the first time this plan is billed.
        $service->ensurePlanTeeth($plan);
        $plan->load('items.teeth');

        // Items that still have at least one pending tooth.
        $billableItems = $plan->items->filter(
            fn ($item) => $item->teeth->where('status', 'pending')->isNotEmpty()
        );

        return view('billing.from-plan', compact('plan', 'billableItems'));
    }

    public function storeFromPlan(Request $request, TreatmentPlan $plan, TreatmentPlanBillingService $service)
    {
        $request->validate([
            'tooth_ids'   => 'required|array|min:1',
            'tooth_ids.*' => 'integer',
        ]);

        try {
            $invoice = $service->createInvoiceFromSelection(
                $plan,
                array_map('intval', $request->tooth_ids),
                auth()->id(),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('billing.show', $invoice)
            ->with('success', 'Invoice created from treatment plan. Remaining teeth stay pending.');
    }

    // ── Apply Manual Discount ────────────────────────────────────────────────
    // Header-level doctor/manager discount, separate from coupons. Permission +
    // role limit are enforced inside ManualDiscountService, which also writes the
    // full accountability entry (old total, discount, new total, reason, user).

    public function applyManualDiscount(Request $request, Invoice $invoice)
    {
        $request->validate([
            'manual_discount_type'  => 'required|in:flat,percentage',
            'manual_discount_value' => 'required|numeric|min:0.01',
            'manual_discount_reason'=> 'required|string|min:3|max:500',
        ]);

        try {
            (new \App\Services\Billing\ManualDiscountService())->apply(
                $invoice,
                $request->manual_discount_type,
                (float) $request->manual_discount_value,
                $request->manual_discount_reason,
                auth()->user()
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Manual discount applied.');
    }

    // ── Remove Manual Discount ───────────────────────────────────────────────

    public function removeManualDiscount(Request $request, Invoice $invoice)
    {
        try {
            (new \App\Services\Billing\ManualDiscountService())->remove(
                $invoice,
                auth()->user(),
                $request->input('reason', 'Manual discount removed')
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Manual discount removed.');
    }

    // ── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'patient_id'            => 'required|exists:patients,id',
            'invoice_date'          => 'required|date',
            'due_date'              => 'nullable|date|after_or_equal:invoice_date',
            'discount_pct'          => 'nullable|numeric|min:0|max:100',
            'notes'                 => 'nullable|string|max:1000',
            'items'                 => 'required|array|min:1',
            'items.*.description'   => 'required|string|max:200',
            'items.*.unit_price'    => 'required|numeric|min:0',
            'items.*.qty'           => 'required|integer|min:1',
            'items.*.disc_pct'      => 'nullable|numeric|min:0|max:100',
            'items.*.gst_pct'       => 'nullable|numeric|min:0|max:100',
            'items.*.tooth_number'  => 'nullable|string|max:100',
            'items.*.treatment_id'  => 'nullable|integer|exists:treatments,id',
            // Discount layers
            'coupon_code'           => 'nullable|string|max:50',
            'wallet_applied'        => 'nullable|numeric|min:0',
            'wallet_treatment_ids'  => 'nullable|array',       // treatment IDs for promo restriction check
            'wallet_treatment_ids.*'=> 'integer',
            'membership_discount'   => 'nullable|numeric|min:0',
            // Manual discount (permission + limit enforced in ManualDiscountService)
            'manual_discount_type'  => 'nullable|in:flat,percentage',
            'manual_discount_value' => 'nullable|numeric|min:0',
            'manual_discount_reason'=> 'nullable|string|max:500',
            'prompt_ids'            => 'nullable|array',
            'prompt_ids.*'          => 'integer|exists:billing_prompts,id',
        ]);

        $invoiceId = null;

        DB::transaction(function () use ($request, &$invoiceId) {

            // ── Resolve coupon ───────────────────────────────────────────────
            $couponId       = null;
            $couponDiscount = 0;
            if ($request->filled('coupon_code')) {
                $coupon = CouponCode::active()
                    ->where('code', strtoupper(trim($request->coupon_code)))
                    ->first();
                if ($coupon && $coupon->canBeUsedByPatient((int) $request->patient_id)) {
                    $couponId = $coupon->id;
                    // Will be calculated after items are saved via recalculate
                    // Store coupon_discount based on submitted hidden field
                    $couponDiscount = max(0, (float) $request->input('coupon_discount', 0));
                }
            }

            // ── Create invoice header ────────────────────────────────────────
            $invoice = Invoice::create([
                'invoice_number'      => Invoice::nextNumber(),
                'patient_id'          => $request->patient_id,
                'invoice_date'        => $request->invoice_date,
                'due_date'            => $request->due_date,
                'discount_pct'        => $request->discount_pct ?? 0,
                'wallet_applied'      => $request->wallet_applied ?? 0,
                'coupon_id'           => $couponId,
                'coupon_discount'     => $couponDiscount,
                'membership_discount' => $request->membership_discount ?? 0,
                'notes'               => $request->notes,
                'status'              => 'draft',
                'created_by'          => auth()->id(),
            ]);

            // ── Save line items ──────────────────────────────────────────────
            foreach ($request->items as $i => $row) {
                $item = new InvoiceItem([
                    'invoice_id'   => $invoice->id,
                    'treatment_id' => $row['treatment_id'] ?? null,   // link to Treatment master
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

            // ── Record coupon usage ──────────────────────────────────────────
            if ($couponId) {
                (new CouponService())->apply(
                    couponId:       $couponId,
                    patientId:      (int) $request->patient_id,
                    invoiceId:      $invoice->id,
                    discountAmount: $couponDiscount,
                    createdBy:      auth()->id()
                );
            }

            // ── Debit wallet if wallet credit was applied ────────────────────
            // Pass treatment IDs so promo credits can be hard-blocked for non-applicable treatments.
            $walletApplied = (float) ($request->wallet_applied ?? 0);
            if ($walletApplied > 0) {
                $treatmentIds = array_map('intval', (array) ($request->wallet_treatment_ids ?? []));

                (new WalletService())->debit(
                    patientId:    (int) $request->patient_id,
                    amount:       $walletApplied,
                    invoiceId:    $invoice->id,
                    createdBy:    auth()->id(),
                    treatmentIds: $treatmentIds
                );
            }

            // ── Mark billing prompts as invoiced ─────────────────────────────
            if ($request->filled('prompt_ids')) {
                BillingPrompt::whereIn('id', $request->prompt_ids)
                    ->where('patient_id', $request->patient_id)
                    ->where('status', 'pending')
                    ->each(fn($p) => $p->markInvoiced($invoice, auth()->id()));
            }

            // ── Mark visit items as billed ───────────────────────────────────
            // Link visit items that were included as line items
            if ($request->has('visit_item_ids')) {
                TreatmentVisitItem::whereIn('id', $request->visit_item_ids)
                    ->where('patient_id', $request->patient_id)
                    ->update(['billing_status' => 'invoiced']); // enum: pending | invoiced | waived
            }

            // ── Apply manual discount ────────────────────────────────────────
            // Permission + role limit are enforced inside the service; a failure
            // throws ValidationException which rolls back this whole transaction.
            if ((float) $request->input('manual_discount_value', 0) > 0) {
                (new \App\Services\Billing\ManualDiscountService())->apply(
                    $invoice,
                    $request->input('manual_discount_type', 'flat'),
                    (float) $request->manual_discount_value,
                    (string) $request->input('manual_discount_reason', ''),
                    auth()->user()
                );
            }
        });

        return redirect()->route('billing.show', $invoiceId)
            ->with('success', 'Invoice created successfully.');
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function show(Invoice $invoice)
    {
        $invoice->load(['patient', 'items', 'payments', 'receipts', 'finalBill']);
        // Clinic bank accounts for the "Received In" dropdown in the payment form
        $bankAccounts = FinanceBankAccount::where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('account_name')
            ->get(['id', 'account_name', 'bank_name', 'account_type']);
        // Wallet — powers the "Use wallet credit" allocation in the payment modal
        $wallet = Wallet::forPatient($invoice->patient_id);
        $wallet->recalculate();
        return view('billing.show', compact('invoice', 'bankAccounts', 'wallet'));
    }

    // ── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return redirect()->route('billing.show', $invoice)
                ->with('error', 'Paid or cancelled invoices cannot be edited.');
        }

        $invoice->load('items');
        $patients        = Patient::orderBy('name')->get(['id', 'name', 'phone']);
        $treatments      = Treatment::where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_price', 'gst_pct', 'unit_basis']);
        $selectedPatient = $invoice->patient;

        return view('billing.form', compact('invoice', 'patients', 'treatments', 'selectedPatient'));
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, Invoice $invoice)
    {
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return back()->with('error', 'Paid or cancelled invoices cannot be edited.');
        }

        $request->validate([
            'patient_id'          => 'required|exists:patients,id',
            'invoice_date'        => 'required|date',
            'due_date'            => 'nullable|date|after_or_equal:invoice_date',
            'discount_pct'        => 'nullable|numeric|min:0|max:100',
            'notes'               => 'nullable|string|max:1000',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string|max:200',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.qty'         => 'required|integer|min:1',
            'items.*.disc_pct'    => 'nullable|numeric|min:0|max:100',
            'items.*.gst_pct'     => 'nullable|numeric|min:0|max:100',
            'items.*.tooth_number'=> 'nullable|string|max:100',
            'items.*.treatment_id'=> 'nullable|integer|exists:treatments,id',
            'manual_discount_type'  => 'nullable|in:flat,percentage',
            'manual_discount_value' => 'nullable|numeric|min:0',
            'manual_discount_reason'=> 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            $invoice->update([
                'patient_id'   => $request->patient_id,
                'invoice_date' => $request->invoice_date,
                'due_date'     => $request->due_date,
                'discount_pct' => $request->discount_pct ?? 0,
                'notes'        => $request->notes,
                'updated_by'   => auth()->id(),
            ]);

            $invoice->items()->delete();

            foreach ($request->items as $i => $row) {
                $item = new InvoiceItem([
                    'invoice_id'   => $invoice->id,
                    'treatment_id' => $row['treatment_id'] ?? null,   // link to Treatment master
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
            $invoice->refresh();

            // Manual discount: apply if provided, otherwise clear any existing one.
            $mdVal = (float) $request->input('manual_discount_value', 0);
            $mdService = new \App\Services\Billing\ManualDiscountService();
            if ($mdVal > 0) {
                $mdService->apply(
                    $invoice,
                    $request->input('manual_discount_type', 'flat'),
                    $mdVal,
                    (string) $request->input('manual_discount_reason', ''),
                    auth()->user()
                );
            } elseif (($invoice->manual_discount_amount ?? 0) > 0) {
                $mdService->remove($invoice, auth()->user(), 'Cleared during invoice edit');
            }
        });

        return redirect()->route('billing.show', $invoice)
            ->with('success', 'Invoice updated successfully.');
    }

    // ── Destroy ──────────────────────────────────────────────────────────────
    // Called from the 3-dot menu on Finance > Invoices tab.
    // Unpaid/draft invoices: simple soft-delete with reason.
    // Paid/partial invoices: must go through cancelInvoice() which handles refunds.

    public function destroy(Request $request, Invoice $invoice)
    {
        if (! auth()->user()->isAdminRole()) {
            abort(403, 'Only admins can delete invoices.');
        }

        // Paid or partial invoices have money attached — use cancelInvoice flow
        if (in_array($invoice->status, ['paid', 'partial'])) {
            return $this->cancelInvoice($request, $invoice);
        }

        // Unpaid / draft — just needs a reason
        $request->validate([
            'cancelled_reason' => 'required|string|min:5|max:500',
        ]);

        BillingAuditLog::record(
            'delete_invoice',
            $invoice,
            $request->cancelled_reason,
            auth()->id(),
            $invoice->invoice_number
        );

        $invoice->update([
            'status'           => 'cancelled',
            'cancelled_reason' => $request->cancelled_reason,
            'cancelled_by'     => auth()->id(),
        ]);
        $invoice->delete();

        return redirect()->route('finance.income')->with('success', 'Invoice ' . $invoice->invoice_number . ' deleted.');
    }

    // ── Cancel (legacy — kept for backward compat with existing patient profile button) ──

    public function cancel(Invoice $invoice)
    {
        $invoice->update(['status' => 'cancelled']);
        return back()->with('success', 'Invoice cancelled.');
    }

    // ── Record Payment ───────────────────────────────────────────────────────
    // Saves payment → auto-creates Receipt → auto-generates FinalBill if fully paid.
    // Handles mode-specific rules: CC convenience fee, cheque fields, EMI schedule.

    public function recordPayment(Request $request, Invoice $invoice)
    {
        if ($invoice->status === 'cancelled') {
            return back()->with('error', 'Cannot record payment on a cancelled invoice.');
        }

        $mode    = $request->input('payment_mode');
        $emiType = $request->input('emi_type', 'direct'); // 'direct' or 'provider'

        // ── Validation (base + mode-specific) ───────────────────────────────
        $rules = [
            'amount'            => 'required|numeric|min:0.01',
            'payment_mode'      => 'required|in:cash,card,debit_card,upi,cheque,netbanking,bank_transfer,emi,other',
            'payment_date'      => 'required|date',
            'clinic_account_id' => 'nullable|exists:finance_bank_accounts,id',
            'reference_no'      => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
            // Payment allocation: draw part of the payment from wallet credit
            'wallet_used'       => 'nullable|numeric|min:0',
        ];

        // UPI / netbanking / bank_transfer — reference required
        if (in_array($mode, ['upi', 'netbanking', 'bank_transfer'])) {
            $rules['reference_no'] = 'required|string|max:100';
        }

        // Cheque — extra fields required
        if ($mode === 'cheque') {
            $rules['bank_name']   = 'required|string|max:100';
            $rules['cheque_no']   = 'required|string|max:50';
            $rules['cheque_date'] = 'required|date';
        }

        // Credit card — convenience fee field
        if ($mode === 'card') {
            $rules['convenience_fee'] = 'nullable|numeric|min:0';
        }

        // EMI — sub-type specific validation
        if ($mode === 'emi') {
            $rules['emi_type'] = 'required|in:direct,provider';

            if ($emiType === 'direct') {
                // Direct EMI: clinic collects instalments
                $rules['emi_provider']      = 'nullable|string|max:100';
                $rules['emi_tenure']        = 'required|integer|min:1|max:84';
                $rules['emi_interest_rate'] = 'required|numeric|min:0|max:36';
                $rules['emi_start_date']    = 'required|date|after_or_equal:today';
            } else {
                // Provider EMI: provider pays clinic upfront
                $rules['emi_provider_scheme_id'] = 'required|integer|exists:emi_schemes,id';
                $rules['emi_upfront_amount']     = 'nullable|numeric|min:0';
                $rules['convenience_fee']        = 'nullable|numeric|min:0';
            }
        }

        $request->validate($rules);

        // ── Credit Card: convenience fee (configurable in Settings → Billing) ─
        // Fee applies per-transaction: when THIS payment amount exceeds the
        // threshold, add (rate %) of the amount. Threshold & rate come from
        // AppSetting (billing group), defaulting to ₹10,000 and 2.5%.
        $convenienceFee = 0;
        if ($mode === 'card') {
            $threshold = (float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000);
            $rate      = (float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5);
            $amount    = (float) $request->amount;

            if ($amount > $threshold) {
                $convenienceFee = round($amount * $rate / 100, 2);
            }

            // Server value is authoritative; never trust a lower submitted value
            $submitted      = (float) ($request->convenience_fee ?? 0);
            $convenienceFee = max($convenienceFee, $submitted);
        }

        // ── Provider EMI: load scheme & compute breakdown ────────────────────
        $providerScheme    = null;
        $providerBreakdown = null;
        if ($mode === 'emi' && $emiType === 'provider') {
            $providerScheme    = EmiScheme::findOrFail($request->emi_provider_scheme_id);
            $invoiceTotal      = (float) $invoice->total_amount;
            $providerBreakdown = $providerScheme->breakdown($invoiceTotal);

            // Convenience fee from scheme (override if submitted value differs)
            if ($providerScheme->pass_cost_to_patient) {
                $convenienceFee = $providerBreakdown['convenience_charge'];
            }
        }

        $receipt = null;
        $walletUsedApplied = 0.0; // how much wallet credit was consumed in this payment
        $excessToWallet    = 0.0; // overpayment moved into the wallet

        DB::transaction(function () use (
            $request, $invoice, $mode, $emiType,
            $convenienceFee, $providerScheme, $providerBreakdown,
            &$receipt, &$walletUsedApplied, &$excessToWallet
        ) {
            $paidBefore = (float) $invoice->paid_amount;

            // ── Payment allocation: consume wallet credit first ──────────────
            // Draw part of the payment from the patient's wallet. This lowers the
            // invoice total (wallet works like a pre-tax credit here), so the cash
            // amount then settles the reduced balance.
            $walletRequested = (float) $request->input('wallet_used', 0);
            if ($walletRequested > 0) {
                $wallet = \App\Models\Wallet::forPatient($invoice->patient_id);
                $cap    = min($walletRequested, (float) $wallet->balance_total, (float) $invoice->balance_due);
                if ($cap > 0) {
                    $debited = (new WalletService())->debit(
                        patientId: $invoice->patient_id,
                        amount:    $cap,
                        invoiceId: $invoice->id,
                        createdBy: auth()->id(),
                    );
                    if ($debited > 0) {
                        $invoice->update(['wallet_applied' => (float) $invoice->wallet_applied + $debited]);
                        $invoice->recalculate();
                        $invoice->refresh();
                        $walletUsedApplied = $debited;
                    }
                }
            }

            // ── Direct EMI: compute instalment amount ────────────────────────
            $emiAmount = null;
            if ($mode === 'emi' && $emiType === 'direct' && $request->emi_tenure > 0) {
                $schedule  = EmiSchedule::buildSchedule(
                    (float) $request->amount,
                    (float) $request->emi_interest_rate,
                    (int)   $request->emi_tenure,
                    $request->emi_start_date
                );
                $emiAmount = $schedule[0]['emi_amount'] ?? null;
            }

            // ── Provider EMI: derive stored values ───────────────────────────
            $clinicNetAmount  = null;
            $emiUpfrontAmount = null;
            if ($mode === 'emi' && $emiType === 'provider' && $providerBreakdown) {
                $clinicNetAmount  = $providerBreakdown['clinic_net_amount'];
                $emiUpfrontAmount = $request->emi_upfront_amount ?? $providerBreakdown['patient_upfront_amount'];
            }

            // 1. Save payment record
            // Resolve clinic account name for display caching
            $clinicAccountName = null;
            if ($request->filled('clinic_account_id')) {
                $clinicAccountName = FinanceBankAccount::find($request->clinic_account_id)?->account_name;
            }

            $payment = InvoicePayment::create([
                'invoice_id'             => $invoice->id,
                'patient_id'             => $invoice->patient_id,
                'amount'                 => $request->amount,
                'payment_mode'           => $mode,
                'payment_date'           => $request->payment_date,
                'reference_no'           => $request->reference_no,
                'notes'                  => $request->notes,
                'created_by'             => auth()->id(),
                // Clinic account received in (Phase 2)
                'clinic_account_id'      => $request->clinic_account_id ?: null,
                'clinic_account_name'    => $clinicAccountName,
                // Cheque
                'bank_name'              => $request->bank_name,
                'cheque_no'              => $request->cheque_no,
                'cheque_date'            => $request->cheque_date,
                'cheque_status'          => $mode === 'cheque' ? 'pending' : null,
                // Credit card / Provider EMI convenience
                'convenience_fee'        => $convenienceFee,
                // Direct EMI fields
                'emi_type'               => $mode === 'emi' ? $emiType : null,
                'emi_provider'           => $mode === 'emi' && $emiType === 'direct' ? $request->emi_provider : null,
                'emi_tenure'             => $mode === 'emi' && $emiType === 'direct' ? $request->emi_tenure : ($providerScheme?->tenure_months),
                'emi_interest_rate'      => $mode === 'emi' && $emiType === 'direct' ? $request->emi_interest_rate : null,
                'emi_amount'             => $emiAmount ?? ($providerBreakdown ? $providerBreakdown['patient_monthly_emi'] : null),
                'emi_start_date'         => $mode === 'emi' && $emiType === 'direct' ? $request->emi_start_date : now()->toDateString(),
                // Provider EMI fields
                'emi_provider_scheme_id' => $providerScheme?->id,
                'emi_upfront_amount'     => $emiUpfrontAmount,
                'clinic_net_amount'      => $clinicNetAmount,
            ]);

            // 2. Generate instalment schedule (Direct EMI only)
            // Provider EMI: provider handles collection — no schedule needed here
            if ($mode === 'emi' && $emiType === 'direct' && $request->emi_tenure > 0) {
                $schedule = EmiSchedule::buildSchedule(
                    (float) $request->amount,
                    (float) $request->emi_interest_rate,
                    (int)   $request->emi_tenure,
                    $request->emi_start_date
                );
                foreach ($schedule as $row) {
                    EmiSchedule::create([
                        'invoice_payment_id' => $payment->id,
                        'invoice_id'         => $invoice->id,
                        'patient_id'         => $invoice->patient_id,
                        'instalment_no'      => $row['instalment_no'],
                        'due_date'           => $row['due_date'],
                        'principal'          => $row['principal'],
                        'interest'           => $row['interest'],
                        'emi_amount'         => $row['emi_amount'],
                        'status'             => 'pending',
                        'created_by'         => auth()->id(),
                    ]);
                }
            }

            // 3. Recalculate invoice totals
            $invoice->recalculate();
            $invoice->refresh();
            $balanceAfter = (float) $invoice->balance_due;

            // 3b. Excess payment → wallet credit.
            // If the patient paid more than the invoice total, the surplus becomes
            // permanent wallet credit (usable on future invoices). The full cash is
            // already recorded as income, so no extra finance entry is needed here.
            if ((float) $invoice->paid_amount > (float) $invoice->total_amount) {
                $excess = round((float) $invoice->paid_amount - (float) $invoice->total_amount, 2);
                if ($excess >= 0.01) {
                    (new WalletService())->deposit(
                        patientId:   $invoice->patient_id,
                        amount:      $excess,
                        paymentMode: $mode,
                        notes:       'Excess payment from ' . $invoice->invoice_number,
                        createdBy:   auth()->id(),
                        source:      'advance',
                    );
                    $excessToWallet = $excess;
                }
            }

            // 4. Generate receipt(s)
            //
            // Provider EMI — split receipt model:
            //   Receipt #1 (now)  → patient upfront amount only (emi_upfront_amount)
            //   Receipt #2 (later)→ clinic_net_amount, created by markProviderPaid()
            //
            // All other modes → single receipt for the full payment amount.

            if ($mode === 'emi' && $emiType === 'provider') {
                // Only issue Receipt #1 if the patient actually pays something upfront today.
                if ($emiUpfrontAmount > 0) {
                    $receipt = Receipt::create([
                        'receipt_number'     => Receipt::nextNumber(),
                        'invoice_id'         => $invoice->id,
                        'invoice_payment_id' => $payment->id,
                        'patient_id'         => $invoice->patient_id,
                        'amount'             => $emiUpfrontAmount,
                        'payment_mode'       => $mode,
                        'receipt_date'       => $request->payment_date,
                        'reference_no'       => $request->reference_no,
                        'invoice_total'      => $invoice->total_amount,
                        'amount_paid_before' => $paidBefore,
                        'balance_after'      => $balanceAfter,
                        'notes'              => $request->notes,
                        'created_by'         => auth()->id(),
                        'receipt_type'       => 'patient_upfront',
                    ]);
                }
                // Receipt #2 (provider_settlement) is created later via markProviderPaid().
            } else {
                // Standard single-receipt flow for cash / card / UPI / cheque / direct EMI.
                $receipt = Receipt::create([
                    'receipt_number'     => Receipt::nextNumber(),
                    'invoice_id'         => $invoice->id,
                    'invoice_payment_id' => $payment->id,
                    'patient_id'         => $invoice->patient_id,
                    'amount'             => (float) $request->amount,
                    'payment_mode'       => $mode,
                    'receipt_date'       => $request->payment_date,
                    'reference_no'       => $request->reference_no,
                    'invoice_total'      => $invoice->total_amount,
                    'amount_paid_before' => $paidBefore,
                    'balance_after'      => $balanceAfter,
                    'notes'              => $request->notes,
                    'created_by'         => auth()->id(),
                ]);
            }

            // 6. Auto-generate FinalBill when fully paid
            if ($invoice->isFullyPaid() && !$invoice->hasFinalBill()) {
                FinalBill::generateFromInvoice($invoice, auth()->id());
            }

            // 7. Finance Mirror
            // For Provider EMI, net_amount reflects what clinic actually receives
            $financeNetAmount = ($mode === 'emi' && $emiType === 'provider' && $clinicNetAmount !== null)
                ? $clinicNetAmount
                : (float) $request->amount;

            FinanceTransaction::create([
                'type'              => 'income',
                'direction'         => 'credit',
                'source_type'       => InvoicePayment::class,
                'source_id'         => $payment->id,
                'amount'            => (float) $request->amount,
                'net_amount'        => $financeNetAmount,
                'payment_mode'      => $mode,
                'payment_reference' => $request->reference_no,
                'patient_id'        => $invoice->patient_id,
                'status'            => 'active',
                'transaction_date'  => $request->payment_date,
                'notes'             => $request->notes,
                'created_by'        => auth()->id(),
            ]);
        });

        // Build flash message
        if ($mode === 'emi' && $emiType === 'provider' && $providerBreakdown) {
            $upfrontFmt = number_format($providerBreakdown['patient_upfront_amount'], 2);
            $netFmt     = number_format($providerBreakdown['clinic_net_amount'], 2);

            if ($receipt) {
                $msg = 'Provider EMI recorded. Upfront receipt ' . $receipt->receipt_number . ' (₹' . $upfrontFmt . ') generated.';
            } else {
                $msg = 'Provider EMI recorded — no upfront payment (0 upfront EMIs).';
            }
            $msg .= ' Clinic net: ₹' . $netFmt . '. Mark provider payment received to generate settlement receipt.';
            if ($convenienceFee > 0) {
                $msg .= ' Convenience charge ₹' . number_format($convenienceFee, 2) . ' included in patient loan.';
            }
        } else {
            $msg = '₹' . number_format($request->amount, 2) . ' recorded. Receipt ' . $receipt->receipt_number . ' generated.';
            if ($convenienceFee > 0) {
                $msg .= ' Convenience fee ₹' . number_format($convenienceFee, 2) . ' applied.';
            }
        }

        if ($walletUsedApplied > 0) {
            $msg .= ' ₹' . number_format($walletUsedApplied, 2) . ' applied from wallet.';
        }
        if ($excessToWallet > 0) {
            $msg .= ' ₹' . number_format($excessToWallet, 2) . ' excess credited to wallet.';
        }

        $invoice->refresh();
        if ($invoice->isFullyPaid()) {
            $msg .= ' Invoice fully paid — Final Bill generated.';
        }

        $fromPatient = $request->input('from_patient');
        $redirectUrl = route('billing.show', $invoice) . ($fromPatient ? '?from_patient=' . $fromPatient : '');

        return redirect($redirectUrl)->with('success', $msg);
    }

    // ── Mark Provider EMI Payment Received ──────────────────────────────────
    // Called when the clinic physically receives the money from the EMI provider.
    // Generates Receipt #2 (provider_settlement) for the clinic_net_amount.

    public function markProviderPaid(Request $request, Invoice $invoice, InvoicePayment $payment)
    {
        // Guard: must be a provider EMI payment that hasn't been marked yet
        if ($payment->emi_type !== 'provider') {
            return back()->with('error', 'This is not a Provider EMI payment.');
        }

        if ($payment->provider_paid_at !== null) {
            return back()->with('error', 'Provider payment has already been marked as received.');
        }

        if ($payment->invoice_id !== $invoice->id) {
            abort(403, 'Payment does not belong to this invoice.');
        }

        $request->validate([
            'provider_paid_date' => 'required|date',
            'provider_reference' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($request, $invoice, $payment) {
            // Generate Receipt #2 — settlement from provider to clinic
            Receipt::create([
                'receipt_number'     => Receipt::nextNumber(),
                'invoice_id'         => $invoice->id,
                'invoice_payment_id' => $payment->id,
                'patient_id'         => $invoice->patient_id,
                'amount'             => $payment->clinic_net_amount,
                'payment_mode'       => 'emi',
                'receipt_date'       => $request->provider_paid_date,
                'reference_no'       => $request->provider_reference,
                'invoice_total'      => $invoice->total_amount,
                'amount_paid_before' => (float) $invoice->paid_amount,
                'balance_after'      => (float) $invoice->balance_due,
                'notes'              => 'Provider EMI settlement receipt.',
                'created_by'         => auth()->id(),
                'receipt_type'       => 'provider_settlement',
            ]);

            // Stamp the payment so we know provider has paid
            $payment->update(['provider_paid_at' => now()]);
        });

        return back()->with('success', 'Provider payment marked as received. Settlement receipt generated for ₹' . number_format($payment->clinic_net_amount, 2) . '.');
    }

    // ── Refund charge rate by payment mode ───────────────────────────────────
    // Only card / debit_card attract a 2.5% processing charge on bank transfer.
    // UPI, cash, cheque, netbanking, EMI, and others have no charge.

    private function refundChargeRate(string $paymentMode): float
    {
        return match ($paymentMode) {
            'card', 'debit_card' => 2.5,
            default              => 0.0,
        };
    }

    // ── Void Receipt ─────────────────────────────────────────────────────────
    // Soft-deletes the Receipt + its InvoicePayment. Recalculates invoice totals.
    // Saves void reason + who + refund method to invoice_payments for full audit.
    // If invoice is no longer fully paid after void → auto-deletes linked FinalBill.

    public function voidReceipt(Request $request, Invoice $invoice, Receipt $receipt)
    {
        if ($receipt->invoice_id !== $invoice->id) {
            abort(403, 'Receipt does not belong to this invoice.');
        }

        // Admin-only gate
        if (! auth()->user()->isAdminRole()) {
            abort(403, 'Only admins can void receipts.');
        }

        $request->validate([
            'void_reason'         => 'required|string|min:5|max:500',
            'void_refund_method'  => 'required|in:wallet,cash,bank_transfer,no_refund',
        ]);

        $CARD_CHARGE_PCT = 2.5; // % deducted by clinic for card/UPI reversals

        DB::transaction(function () use ($request, $invoice, $receipt, $CARD_CHARGE_PCT) {
            $amount    = (float) $receipt->amount;
            $patientId = $invoice->patient_id;
            $method    = $request->void_refund_method;

            // Calculate charge: only card/debit_card bank transfers attract 2.5%
            // UPI, cash, cheque, netbanking, EMI → 0% charge
            $chargeRate     = ($method === 'bank_transfer') ? $this->refundChargeRate($receipt->payment_mode) : 0.0;
            $chargeDeducted = round($amount * $chargeRate / 100, 2);
            $refundAmount   = $amount - $chargeDeducted;

            // 1. Audit log
            BillingAuditLog::record(
                'void_receipt',
                $receipt,
                $request->void_reason . ' [refund: ' . $method . ']',
                auth()->id(),
                $receipt->receipt_number
            );

            // 2. Save audit fields + soft-delete the InvoicePayment
            if ($receipt->invoice_payment_id) {
                $payment = InvoicePayment::find($receipt->invoice_payment_id);
                if ($payment) {
                    // Save void audit before deleting
                    $payment->update([
                        'void_reason'          => $request->void_reason,
                        'voided_by'            => auth()->id(),
                        'void_refund_method'   => $method,
                        'void_refund_amount'   => $refundAmount,
                        'void_charge_deducted' => $chargeDeducted,
                    ]);

                    // Reverse the FinanceTransaction for this payment
                    FinanceTransaction::where('source_type', InvoicePayment::class)
                        ->where('source_id', $payment->id)
                        ->where('status', 'active')
                        ->update(['status' => 'voided']);

                    $payment->delete(); // soft-delete
                }
            }

            // 3. Soft-delete the Receipt
            $receipt->delete();

            // 4. Recalculate invoice (paid_amount, balance_due, status)
            $invoice->refresh();
            $invoice->recalculate();

            // 5. Auto-delete Final Bill if invoice is no longer fully paid
            //    (A Final Bill only exists when invoice was 100% paid — now it's invalid)
            $invoice->refresh();
            if ($invoice->status !== 'paid' && $invoice->finalBill) {
                $invoice->finalBill->update([
                    'deleted_reason' => 'Auto-invalidated: linked receipt ' . $receipt->receipt_number . ' was voided. Reason: ' . $request->void_reason,
                    'deleted_by'     => auth()->id(),
                ]);
                $invoice->finalBill->delete();
            }

            // 6. Handle refund / wallet credit / no refund
            $notes = 'Voided receipt ' . $receipt->receipt_number . '. Reason: ' . $request->void_reason;

            if ($method === 'no_refund') {
                // No money returned — just log the void, amount is forfeited/written off
                FinanceTransaction::create([
                    'type'             => 'refund',
                    'direction'        => 'debit',
                    'source_type'      => Receipt::class,
                    'source_id'        => $receipt->id,
                    'amount'           => $amount,
                    'net_amount'       => 0,
                    'payment_mode'     => $receipt->payment_mode,
                    'patient_id'       => $patientId,
                    'status'           => 'active',
                    'transaction_date' => now()->toDateString(),
                    'notes'            => 'No refund issued — ' . $notes,
                    'created_by'       => auth()->id(),
                ]);
            } elseif ($method === 'wallet') {
                // Full amount credited to patient permanent wallet
                (new WalletService())->credit(
                    patientId : $patientId,
                    amount    : $amount,
                    creditType: 'permanent',
                    notes     : 'Wallet credit — ' . $notes,
                    createdBy : auth()->id()
                );
                FinanceTransaction::create([
                    'type'             => 'refund',
                    'direction'        => 'debit',
                    'source_type'      => Receipt::class,
                    'source_id'        => $receipt->id,
                    'amount'           => $amount,
                    'net_amount'       => $amount,
                    'payment_mode'     => $receipt->payment_mode,
                    'patient_id'       => $patientId,
                    'status'           => 'active',
                    'transaction_date' => now()->toDateString(),
                    'notes'            => 'Wallet credit — ' . $notes,
                    'created_by'       => auth()->id(),
                ]);

            } elseif ($method === 'cash') {
                // Physical cash returned — log as refund debit, no wallet change
                FinanceTransaction::create([
                    'type'             => 'refund',
                    'direction'        => 'debit',
                    'source_type'      => Receipt::class,
                    'source_id'        => $receipt->id,
                    'amount'           => $amount,
                    'net_amount'       => $amount,
                    'payment_mode'     => 'cash',
                    'patient_id'       => $patientId,
                    'status'           => 'active',
                    'transaction_date' => now()->toDateString(),
                    'notes'            => 'Cash refund — ' . $notes,
                    'created_by'       => auth()->id(),
                ]);

            } elseif ($method === 'bank_transfer') {
                // Bank transfer / UPI refund.
                // Charge rate auto-determined from original payment mode:
                //   card / debit_card → 2.5% | all others (upi, netbanking, cheque…) → 0%
                $chargeNote = $chargeDeducted > 0
                    ? ' (₹' . number_format($refundAmount, 2) . ' to patient, ₹' . number_format($chargeDeducted, 2) . ' clinic charge)'
                    : ' (no charge)';
                FinanceTransaction::create([
                    'type'             => 'refund',
                    'direction'        => 'debit',
                    'source_type'      => Receipt::class,
                    'source_id'        => $receipt->id,
                    'amount'           => $amount,
                    'net_amount'       => $refundAmount,
                    'payment_mode'     => $receipt->payment_mode,
                    'patient_id'       => $patientId,
                    'status'           => 'active',
                    'transaction_date' => now()->toDateString(),
                    'notes'            => 'Bank transfer refund' . $chargeNote . ' — ' . $notes,
                    'created_by'       => auth()->id(),
                ]);
            }
        });

        $chargeRate = $this->refundChargeRate($receipt->payment_mode);
        $actionMsg  = match($request->void_refund_method) {
            'wallet'        => ' ₹' . number_format($receipt->amount, 2) . ' credited to patient wallet.',
            'cash'          => ' Cash refund of ₹' . number_format($receipt->amount, 2) . ' recorded.',
            'bank_transfer' => $chargeRate > 0
                                ? ' Bank transfer: patient receives ₹' . number_format($receipt->amount * (1 - $chargeRate / 100), 2) . ' (' . $chargeRate . '% charge deducted).'
                                : ' Bank transfer refund of ₹' . number_format($receipt->amount, 2) . ' recorded (no charge).',
            'no_refund'     => ' No refund issued — amount written off.',
            default         => '',
        };

        return redirect()->route('billing.show', $invoice->id)
                         ->with('success', 'Receipt ' . $receipt->receipt_number . ' voided.' . $actionMsg);
    }

    // ── Cancel Invoice (with reason) ─────────────────────────────────────────
    // Admin-only. Voids all receipts (credits wallet), deletes Final Bill if any,
    // marks invoice as cancelled. Saves full audit trail.

    public function cancelInvoice(Request $request, Invoice $invoice)
    {
        if (! auth()->user()->isAdminRole()) {
            abort(403, 'Only admins can cancel invoices.');
        }

        $request->validate([
            'cancelled_reason'       => 'required|string|min:5|max:500',
            'cancel_refund_method'   => 'required|in:wallet,cash,bank_transfer,no_refund',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            $patientId = $invoice->patient_id;
            $method    = $request->cancel_refund_method;

            // 1. Audit log
            BillingAuditLog::record(
                'cancel_invoice',
                $invoice,
                $request->cancelled_reason . ' [refund: ' . $method . ']',
                auth()->id(),
                $invoice->invoice_number
            );

            // 2. Void every active receipt on this invoice
            $activeReceipts = $invoice->receipts()->whereNull('deleted_at')->get();
            foreach ($activeReceipts as $receipt) {
                $amount = (float) $receipt->amount;

                // bank_transfer charge is auto-determined from original payment mode:
                //   card / debit_card → 2.5% | all others (upi, cash-cancelled-as-transfer, etc.) → 0%
                $chargeDeducted = 0;
                $refundAmount   = $amount;
                if ($method === 'bank_transfer') {
                    $chargeDeducted = round($amount * $this->refundChargeRate($receipt->payment_mode) / 100, 2);
                    $refundAmount   = $amount - $chargeDeducted;
                }

                $notes = 'Invoice ' . $invoice->invoice_number . ' cancelled. Reason: ' . $request->cancelled_reason;

                // Save void audit on the payment record
                if ($receipt->invoice_payment_id) {
                    $payment = InvoicePayment::find($receipt->invoice_payment_id);
                    if ($payment) {
                        $payment->update([
                            'void_reason'          => $request->cancelled_reason,
                            'voided_by'            => auth()->id(),
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

                // Credit / refund / no refund
                if ($method === 'no_refund') {
                    // No money returned — just log the write-off
                    FinanceTransaction::create([
                        'type'             => 'refund',
                        'direction'        => 'debit',
                        'source_type'      => Receipt::class,
                        'source_id'        => $receipt->id,
                        'amount'           => $amount,
                        'net_amount'       => 0,
                        'payment_mode'     => $receipt->payment_mode,
                        'patient_id'       => $patientId,
                        'status'           => 'active',
                        'transaction_date' => now()->toDateString(),
                        'notes'            => 'No refund issued — ' . $notes,
                        'created_by'       => auth()->id(),
                    ]);
                } else {
                    if ($method === 'wallet') {
                        (new WalletService())->credit(
                            patientId : $patientId,
                            amount    : $amount,
                            creditType: 'permanent',
                            notes     : 'Wallet credit — ' . $notes,
                            createdBy : auth()->id()
                        );
                    }

                    $methodLabel = match($method) {
                        'wallet'        => 'Wallet credit',
                        'cash'          => 'Cash refund',
                        'bank_transfer' => $chargeDeducted > 0
                            ? 'Bank transfer refund (₹' . number_format($refundAmount, 2) . ' to patient, ₹' . number_format($chargeDeducted, 2) . ' clinic charge)'
                            : 'Bank transfer refund (no charge)',
                        default         => ucfirst($method) . ' refund',
                    };
                    FinanceTransaction::create([
                        'type'             => 'refund',
                        'direction'        => 'debit',
                        'source_type'      => Receipt::class,
                        'source_id'        => $receipt->id,
                        'amount'           => $amount,
                        'net_amount'       => $refundAmount,
                        'payment_mode'     => $method === 'cash' ? 'cash' : $receipt->payment_mode,
                        'patient_id'       => $patientId,
                        'status'           => 'active',
                        'transaction_date' => now()->toDateString(),
                        'notes'            => $methodLabel . ' — ' . $notes,
                        'created_by'       => auth()->id(),
                    ]);
                }
            }

            // 3. Auto-delete Final Bill if present
            if ($invoice->finalBill) {
                $invoice->finalBill->update([
                    'deleted_reason' => 'Invoice ' . $invoice->invoice_number . ' cancelled. Reason: ' . $request->cancelled_reason,
                    'deleted_by'     => auth()->id(),
                ]);
                $invoice->finalBill->delete();
            }

            // 4. Mark invoice as cancelled + save audit
            $invoice->update([
                'status'           => 'cancelled',
                'cancelled_reason' => $request->cancelled_reason,
                'cancelled_by'     => auth()->id(),
            ]);
            $invoice->delete(); // soft-delete (moves to Trash tab)
        });

        return redirect()->route('finance.income')
                         ->with('success', 'Invoice ' . $invoice->invoice_number . ' cancelled and moved to Trash.');
    }

    // ── Delete Final Bill (with reason) ──────────────────────────────────────
    // Admin-only. Soft-deletes the Final Bill only.
    // Invoice and receipts are NOT affected — invoice stays Paid.
    // Use case: bill generated with wrong discount / before treatment complete.

    public function deleteFinalBill(Request $request, \App\Models\FinalBill $finalBill)
    {
        if (! auth()->user()->isAdminRole()) {
            abort(403, 'Only admins can delete final bills.');
        }

        $request->validate([
            'deleted_reason' => 'required|string|min:5|max:500',
        ]);

        BillingAuditLog::record(
            'delete_final_bill',
            $finalBill,
            $request->deleted_reason,
            auth()->id(),
            $finalBill->bill_number
        );

        $finalBill->update([
            'deleted_reason' => $request->deleted_reason,
            'deleted_by'     => auth()->id(),
        ]);
        $finalBill->delete();

        return redirect()->route('finance.income', ['tab' => 'bills'])
                         ->with('success', 'Final bill ' . $finalBill->bill_number . ' deleted. Invoice and payments are unaffected.');
    }

    // ── Invoice Panel (AJAX partial for patient profile drawer) ──────────────
    // Returns self-contained HTML — no layout. Loaded by the slide-over in patients/show.

    public function panel(Invoice $invoice)
    {
        $invoice->load(['patient', 'items', 'payments', 'receipts', 'finalBill']);

        $activeEmiProviders = \App\Models\EmiProvider::where('is_active', true)
            ->with(['schemes' => fn($q) => $q->where('is_active', true)])
            ->orderBy('name')->get();

        return view('billing._invoice_panel', compact('invoice', 'activeEmiProviders'));
    }

    // ── Show Receipt ─────────────────────────────────────────────────────────

    public function showReceipt(Invoice $invoice, Receipt $receipt)
    {
        if ($receipt->invoice_id !== $invoice->id) {
            abort(403, 'Receipt does not belong to this invoice.');
        }

        $receipt->load(['invoice.patient', 'invoice.items', 'payment']);

        $clinic = \App\Models\AppSetting::whereIn('key', [
            'clinic_name', 'clinic_address', 'clinic_phone', 'clinic_email', 'clinic_gst_no'
        ])->pluck('value', 'key');

        return view('billing.receipt', compact('receipt', 'clinic'));
    }

    // ── Show Final Bill ───────────────────────────────────────────────────────

    public function showFinalBill(Invoice $invoice)
    {
        $finalBill = $invoice->finalBill;

        if (!$finalBill) {
            return redirect()->route('billing.show', $invoice)
                ->with('error', 'Final bill has not been generated yet. Pay the full balance first.');
        }

        $invoice->load(['patient', 'items', 'payments', 'receipts']);

        $clinic = \App\Models\AppSetting::whereIn('key', [
            'clinic_name', 'clinic_address', 'clinic_phone', 'clinic_email', 'clinic_gst_no'
        ])->pluck('value', 'key');

        return view('billing.final-bill', compact('invoice', 'finalBill', 'clinic'));
    }

    // ── Enroll Patient in Membership ─────────────────────────────────────────
    // POST from patient profile billing tab enrollment modal.

    public function enrollMembership(Request $request, Patient $patient)
    {
        $request->validate([
            'plan_id'                   => 'required|exists:finance_membership_plans,id',
            'amount_paid'               => 'required|numeric|min:0',
            'payment_mode'              => 'required|in:cash,upi,card,debit_card,netbanking,bank_transfer',
            'family_head_membership_id' => 'nullable|exists:finance_patient_memberships,id',
            'family_name'               => 'nullable|string|max:100',
            'start_date'                => 'nullable|date|before_or_equal:today', // backdated entry allowed, no future dates
        ]);

        // Enrollment date — defaults to today, but can be backdated.
        // Used for the membership validity AND all finance records (invoice/payment/receipt/transaction).
        $enrollDate = $request->filled('start_date')
            ? $request->input('start_date')
            : now()->toDateString();

        $familyHeadId = $request->input('family_head_membership_id');
        $familyName   = trim($request->input('family_name', '')) ?: null;

        // No "family head" concept: if a member is linked, this enrollment is an
        // add-on under them; otherwise it's a standalone member.
        $memberType   = $familyHeadId ? 'addon' : 'individual';

        // Guard: add-on must point at a real, currently-active member (any plan/price).
        if ($memberType === 'addon') {
            $headEnrollment = \App\Models\Finance\FinancePatientMembership::find($familyHeadId);
            if (! $headEnrollment || ! $headEnrollment->isActive()) {
                return back()->withErrors(['family_head_membership_id' => 'The selected member does not have an active membership.'])->withInput();
            }
            // Guard: check max add-ons not exceeded
            $plan = \App\Models\Finance\FinanceMembershipPlan::find($request->plan_id);
            if ($plan && $plan->isAddonModel()) {
                $currentAddons = $headEnrollment->familyMembers()->where('status', 'active')->count();
                if ($currentAddons >= ($plan->max_family_members ?? 4)) {
                    return back()->withErrors(['family_head_membership_id' => 'This family has reached the maximum allowed add-on members.'])->withInput();
                }
            }
            // Inherit family_name from head if not given
            if (! $familyName) {
                $familyName = $headEnrollment->family_name;
            }
        }

        // 1. Enroll patient — creates FinancePatientMembership record
        $membership = MembershipBenefitService::enroll(
            $patient->id,
            (int) $request->plan_id,
            (float) $request->amount_paid,
            Auth::id(),
            $memberType,
            $familyHeadId ? (int) $familyHeadId : null,
            $familyName,
            $enrollDate   // backdated enrollment date (or today)
        );

        $plan       = $membership->plan;
        $amountPaid = (float) $request->amount_paid;

        // 2. Create membership invoice
        $invoice = Invoice::create([
            'invoice_number' => Invoice::nextNumber(),
            'patient_id'     => $patient->id,
            'invoice_date'   => $enrollDate,
            'due_date'       => $enrollDate,
            'subtotal'       => $amountPaid,
            'discount_pct'   => 0,
            'discount_amount'=> 0,
            'taxable_amount' => $amountPaid,
            'gst_amount'     => 0,
            'total_amount'   => $amountPaid,
            'paid_amount'    => $amountPaid,
            'balance_due'    => 0,
            'status'         => 'paid',
            'membership_id'  => $membership->id,
            'notes'          => 'AOCP Membership — ' . $plan->plan_name,
            'created_by'     => Auth::id(),
        ]);

        // 3. Add line item
        $item = new InvoiceItem([
            'invoice_id'  => $invoice->id,
            'description' => 'AOCP Membership: ' . $plan->plan_name,
            'unit_price'  => $amountPaid,
            'qty'         => 1,
            'disc_pct'    => 0,
            'disc_amount' => 0,
            'net_amount'  => $amountPaid,
            'gst_pct'     => 0,
            'gst_amount'  => 0,
            'total'       => $amountPaid,
            'sort_order'  => 1,
        ]);
        $item->save();

        // 4. Record payment
        $payment = InvoicePayment::create([
            'invoice_id'    => $invoice->id,
            'patient_id'    => $patient->id,
            'amount'        => $amountPaid,
            'payment_mode'  => $request->payment_mode,
            'payment_date'  => $enrollDate,
            'notes'         => 'Membership enrollment — ' . $plan->plan_name,
            'created_by'    => Auth::id(),
        ]);

        // 5. Generate Receipt
        Receipt::create([
            'receipt_number'     => Receipt::nextNumber(),
            'invoice_id'         => $invoice->id,
            'invoice_payment_id' => $payment->id,
            'patient_id'         => $patient->id,
            'amount'             => $amountPaid,
            'payment_mode'       => $request->payment_mode,
            'receipt_date'       => $enrollDate,
            'invoice_total'      => $amountPaid,
            'amount_paid_before' => 0,
            'balance_after'      => 0,
            'notes'              => 'AOCP Membership — ' . $plan->plan_name,
            'created_by'         => Auth::id(),
        ]);

        // 6. Generate FinalBill (invoice is fully paid at enrollment)
        FinalBill::generateFromInvoice($invoice, Auth::id());

        // 7. Finance mirror
        FinanceTransaction::create([
            'type'              => 'income',
            'direction'         => 'credit',
            'source_type'       => InvoicePayment::class,
            'source_id'         => $payment->id,
            'amount'            => $amountPaid,
            'net_amount'        => $amountPaid,
            'payment_mode'      => $request->payment_mode,
            'patient_id'        => $patient->id,
            'status'            => 'active',
            'transaction_date'  => $enrollDate,
            'notes'             => 'AOCP Membership — ' . $plan->plan_name,
            'created_by'        => Auth::id(),
        ]);

        // 8. Redirect to printable receipt/invoice
        return redirect()->route('billing.print', $invoice->id)
                         ->with('success', 'AOCP Membership enrolled. Receipt generated.');
    }

    // ── Delete Invoice with Auth ─────────────────────────────────────────────
    // Requires reason text + login password. Logs to billing_audit_logs.
    // Paid invoices are blocked — they need a manager override (future feature).

    public function destroyWithAuth(Request $request, Invoice $invoice)
    {
        $request->validate([
            'reason'   => 'required|string|min:5|max:500',
            'password' => 'required|string',
        ]);

        // Verify caller's password
        if (! Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password — deletion not authorised.'])
                         ->withInput();
        }

        // Hard block on paid invoices
        if ($invoice->status === 'paid') {
            return back()->with('error', 'Paid invoices cannot be deleted. Reverse the payments first or contact the administrator.');
        }

        $patientId  = $invoice->patient_id;
        $displayRef = $invoice->invoice_number ?? ('Invoice #' . $invoice->id);

        // Snapshot + audit log BEFORE soft-delete
        BillingAuditLog::record('delete', $invoice, $request->reason, auth()->id(), $displayRef);

        $invoice->delete();

        return redirect()
            ->route('patients.show', $patientId)
            ->with('success', "Invoice {$displayRef} deleted. Reason recorded in audit log.");
    }

    // ── Gate Edit with Auth ──────────────────────────────────────────────────
    // POST to this route before the user is allowed onto the edit form.
    // Validates reason + password, stores reason in session, then redirects to edit.

    public function editWithAuth(Request $request, Invoice $invoice)
    {
        $request->validate([
            'reason'   => 'required|string|min:5|max:500',
            'password' => 'required|string',
        ]);

        if (! Hash::check($request->password, auth()->user()->password)) {
            return back()->withErrors(['password' => 'Incorrect password — edit not authorised.'])
                         ->withInput();
        }

        if ($invoice->status === 'paid') {
            return back()->with('error', 'Paid invoices cannot be edited. Record a credit/adjustment instead.');
        }

        // Log the intent; snapshot will be updated again on actual save
        BillingAuditLog::record('edit_intent', $invoice, $request->reason, auth()->id(), $invoice->invoice_number);

        // Store reason so update() can attach it to the final audit entry
        session(['invoice_edit_reason_' . $invoice->id => $request->reason]);

        return redirect()->route('billing.edit', $invoice)
            ->with('info', 'Edit authorised. Reason: "' . $request->reason . '"');
    }

    // ── AJAX: Get membership benefits for a patient ───────────────────────────
    // Called when front desk changes the patient on invoice form.
    // Returns JSON benefit summary for auto-applying to totals.

    public function membershipBenefits(Request $request)
    {
        $request->validate(['patient_id' => 'required|integer|exists:patients,id']);

        // Parse line items from request if provided (for accurate free-item matching)
        $lineItems = [];
        foreach ($request->input('items', []) as $row) {
            if (!empty($row['description'])) {
                $lineItems[] = [
                    'name'   => $row['description'],
                    'amount' => (float) ($row['unit_price'] ?? 0),
                    'qty'    => (int) ($row['qty'] ?? 1),
                ];
            }
        }

        $subtotal = (float) $request->input('subtotal', 0);

        $result = MembershipBenefitService::forPatient(
            (int) $request->patient_id,
            $lineItems,
            $subtotal
        );

        return response()->json($result);
    }

    // ── Print ────────────────────────────────────────────────────────────────

    public function printInvoice(Invoice $invoice)
    {
        $invoice->load(['patient', 'items', 'payments']);

        // Fetch clinic settings for the header
        $clinic = \App\Models\AppSetting::whereIn('key', [
            'clinic_name', 'clinic_address', 'clinic_phone', 'clinic_email', 'clinic_gst_no'
        ])->pluck('value', 'key');

        return view('billing.print', compact('invoice', 'clinic'));
    }
}
