<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\BillingAuditLog;
use App\Models\Finance\FinanceTransaction;
use App\Models\Patient;
use App\Models\RoleBillingPermission;
use App\Models\Treatment;
use App\Models\Wallet;
use App\Models\WalletCampaign;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    // ── Index: all patient wallets + dashboard cards ─────────────────────────

    public function index(Request $request)
    {
        // Promotional campaigns (all, newest first)
        $campaigns = WalletCampaign::orderByDesc('created_at')->limit(10)->get();

        // Individual credit wallets — patients with any balance.
        // whereHas('patient') excludes wallets whose patient was deleted, so the view
        // never tries to build a patients.show URL from a null patient.
        //
        // Filter, because "who owes us nothing but is holding a coupon" and "whose
        // money are we actually sitting on" are two different questions and staff
        // ask them separately:
        //   credit = holds the patient's own money (a liability, refundable)
        //   promo  = holds only a clinic-funded concession (expires, not refundable)
        $filter = in_array($request->input('filter'), ['credit', 'promo'], true)
            ? $request->input('filter')
            : 'all';

        $base = fn () => Wallet::whereHas('patient')->where('balance_total', '>', 0);

        $walletCounts = [
            'all'    => $base()->count(),
            'credit' => $base()->where('balance_patient_credit', '>', 0)->count(),
            'promo'  => $base()->where('balance_promotional', '>', 0)
                               ->where('balance_patient_credit', '<=', 0)->count(),
        ];

        $creditWallets = $base()
            ->with('patient')
            ->when($filter === 'credit', fn ($q) => $q->where('balance_patient_credit', '>', 0))
            ->when($filter === 'promo',  fn ($q) => $q->where('balance_promotional', '>', 0)
                                                      ->where('balance_patient_credit', '<=', 0))
            ->orderByDesc('balance_patient_credit')
            ->orderByDesc('balance_promotional')
            ->paginate(25)
            ->withQueryString();

        // ── Dashboard cards ──────────────────────────────────────────────────

        $patientsWithBalance = Wallet::where('balance_total', '>', 0)->count();

        // ── Dashboard cards ──────────────────────────────────────────────────
        // Promotional and patient credit are economically OPPOSITE things and
        // must never be totalled into one figure:
        //
        //   Promotional / clinic-funded = a concession the clinic is giving away.
        //                                 Expires. Never refundable. Marketing spend.
        //   Patient credit (U8)         = the patient's own money, held by the
        //                                 clinic. Never expires. Refundable. A LIABILITY.
        //
        // The old cards showed one blended "Total Outstanding" and then repeated
        // it as "Active Balance" ($activeBalance = $totalOutstanding), so two of
        // five cards carried the same blended number and neither told you which
        // half was clinic money and which half was the patient's.

        // Balances held right now.
        $promoAvailable = (float) Wallet::sum('balance_promotional');
        $patientCredit  = (float) Wallet::sum('balance_patient_credit');

        // Promotional consumed — concession actually given away against invoices.
        $promoUsedTotal = (float) WalletTransaction::where('direction', 'debit')
            ->where('credit_type', 'promotional')->sum('amount');

        $promoUsedMonth = (float) WalletTransaction::where('direction', 'debit')
            ->where('credit_type', 'promotional')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->sum('amount');

        // Patient credit consumed — the patient's own money applied to invoices.
        // Withdrawals are excluded on purpose: a refund is money handed BACK,
        // not credit spent on treatment, and lumping the two together would
        // overstate how much stored credit the clinic has actually earned out.
        $creditUsedTotal = (float) WalletTransaction::where('direction', 'debit')
            ->where('funding', 'patient')
            ->where('source', '!=', 'withdrawal')->sum('amount');

        $creditUsedMonth = (float) WalletTransaction::where('direction', 'debit')
            ->where('funding', 'patient')
            ->where('source', '!=', 'withdrawal')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)->sum('amount');

        return view('finance.wallets.index', compact(
            'campaigns', 'creditWallets', 'patientsWithBalance', 'filter', 'walletCounts',
            'promoAvailable', 'promoUsedTotal', 'promoUsedMonth',
            'patientCredit', 'creditUsedTotal', 'creditUsedMonth'
        ));
    }

    // ── Transaction Register: all patients, filterable ───────────────────────

    public function register(Request $request)
    {
        // Build base query (no ordering yet, for clean cloning for totals)
        $base = WalletTransaction::query();

        if ($request->filled('q')) {
            $q = $request->q;
            $base->whereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%")
                                                     ->orWhere('phone', 'like', "%{$q}%"));
        }
        if ($request->filled('patient_id')) {
            $base->where('patient_id', $request->patient_id);
        }
        if ($request->filled('from')) {
            $base->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $base->whereDate('created_at', '<=', $request->to);
        }

        // Totals on base query (before pagination)
        $totalCredits = (clone $base)->where('direction', 'credit')->sum('amount');
        $totalDebits  = (clone $base)->where('direction', 'debit')->sum('amount');

        // Paginated result with relationships
        $transactions = (clone $base)
            ->with(['patient', 'invoice'])
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        // Patient list for dropdown
        $patients = Patient::orderBy('name')->select('id', 'name')->get();

        return view('finance.wallets.register', compact(
            'transactions', 'totalCredits', 'totalDebits', 'patients'
        ));
    }

    // ── Register Export ──────────────────────────────────────────────────────

    public function registerExport(Request $request)
    {
        $format = $request->input('format', 'excel');

        $query = WalletTransaction::with(['patient', 'invoice'])
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $q = $request->q;
            $query->whereHas('patient', fn($p) => $p->where('name', 'like', "%{$q}%")
                                                      ->orWhere('phone', 'like', "%{$q}%"));
        }
        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $transactions = $query->get();

        if ($format === 'pdf') {
            return view('finance.wallets.register-pdf', compact('transactions', 'request'));
        }

        // ── Excel export ─────────────────────────────────────────────────────
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Wallet Register');

        $headers = [
            'A' => 'Date',
            'B' => 'Patient',
            'C' => 'Phone',
            'D' => 'Credit (₹)',
            'E' => 'Debit (₹)',
            'F' => 'Transaction Type',
            'G' => 'Source',
            'H' => 'Invoice No',
            'I' => 'Notes',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($transactions as $tx) {
            $sheet->setCellValue("A{$row}", $tx->created_at->format('d-m-Y'));
            $sheet->setCellValue("B{$row}", $tx->patient?->name ?? '');
            $sheet->setCellValue("C{$row}", $tx->patient?->phone ?? '');
            $sheet->setCellValue("D{$row}", $tx->direction === 'credit' ? (float) $tx->amount : '');
            $sheet->setCellValue("E{$row}", $tx->direction === 'debit'  ? (float) $tx->amount : '');
            $sheet->setCellValue("F{$row}", ucfirst($tx->credit_type ?? '') . ($tx->campaign_name ? ' — ' . $tx->campaign_name : ''));
            $sheet->setCellValue("G{$row}", ucwords(str_replace('_', ' ', $tx->source ?? '')));
            $sheet->setCellValue("H{$row}", $tx->invoice_number ?? ($tx->invoice?->invoice_number ?? ''));
            $sheet->setCellValue("I{$row}", $tx->notes ?? '');
            $row++;
        }

        // Totals row
        $totalCredits = $transactions->where('direction', 'credit')->sum('amount');
        $totalDebits  = $transactions->where('direction', 'debit')->sum('amount');
        $sheet->setCellValue("C{$row}", 'TOTAL');
        $sheet->setCellValue("D{$row}", $totalCredits);
        $sheet->setCellValue("E{$row}", $totalDebits);
        $sheet->getStyle("C{$row}:E{$row}")->getFont()->setBold(true);

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'wallet_register_' . now()->format('Y-m-d') . '.xlsx';
        $temp     = tempnam(sys_get_temp_dir(), 'wlt_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Show: per-patient ledger ─────────────────────────────────────────────

    public function show(Patient $patient)
    {
        $wallet = Wallet::forPatient($patient->id);
        $wallet->recalculate();

        $transactions = $wallet->transactions()->with('invoice')->get();

        // Calculate running balance (chronological, oldest first)
        $chronological = $transactions->sortBy('created_at')->values();
        $running       = 0.0;
        $withBalance   = $chronological->map(function ($tx) use (&$running) {
            $running += $tx->direction === 'credit' ? (float) $tx->amount : -(float) $tx->amount;
            $tx->running_balance = max(0, round($running, 2));
            return $tx;
        })->sortByDesc('created_at')->values(); // re-sort newest first for display

        // Summary stats for the ledger header
        $totalCredits   = $transactions->where('direction', 'credit')->sum('amount');
        $totalDebits    = $transactions->where('direction', 'debit')->sum('amount');
        $totalRefunds   = $transactions->where('source', 'refund')->sum('amount');
        $totalUtilized  = $transactions->where('source', 'invoice_debit')->sum('amount');

        return view('finance.wallets.show', compact(
            'patient', 'wallet', 'withBalance',
            'totalCredits', 'totalDebits', 'totalRefunds', 'totalUtilized'
        ));
    }

    // ── Credit: add balance form ─────────────────────────────────────────────

    public function creditForm(Patient $patient)
    {
        $wallet     = Wallet::forPatient($patient->id);
        $treatments = Treatment::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('finance.wallets.credit', compact('patient', 'wallet', 'treatments'));
    }

    public function credit(Request $request, Patient $patient)
    {
        $request->validate([
            'credit_type'           => 'required|in:promotional,permanent',
            'amount'                => 'required|numeric|min:1',
            // Promo expiry uses field 'expiry_date'; permanent uses 'perm_expiry_date'
            // (separate names prevent the hidden perm field from overwriting promo on submit)
            'expiry_date'           => $request->credit_type === 'promotional'
                ? ['required', 'date', 'after:today']
                : ['nullable'],
            'perm_expiry_date'      => ['nullable', 'date', 'after:today'],
            'campaign_name'         => 'nullable|string|max:200',
            'treatment_scope'       => 'nullable|in:all,specific',
            'applicable_treatments' => 'nullable|array',
            'applicable_treatments.*' => 'integer|exists:treatments,id',
            'notes'                 => 'nullable|string|max:300',
        ]);

        // Resolve which expiry date applies
        $expiryDate = $request->credit_type === 'promotional'
            ? $request->expiry_date
            : ($request->perm_has_expiry ? $request->perm_expiry_date : null);

        $applicableTreatments = null;
        if (
            $request->credit_type === 'promotional' &&
            $request->treatment_scope === 'specific' &&
            ! empty($request->applicable_treatments)
        ) {
            $applicableTreatments = array_map('intval', $request->applicable_treatments);
        }

        $this->walletService->credit(
            patientId:            $patient->id,
            amount:               (float) $request->amount,
            creditType:           $request->credit_type,
            expiryDate:           $expiryDate ?: null,
            notes:                $request->notes,
            createdBy:            auth()->id(),
            campaignName:         $request->credit_type === 'promotional' ? $request->campaign_name : null,
            applicableTreatments: $applicableTreatments,
            // U8 rules 12/13 — Add Credit issues CLINIC money. No cash is received
            // on this path, so it is a concession: not a liability, and never
            // cash-refundable. Cash-backed credit arrives via receiveAdvance().
            funding:              \App\Services\WalletService::FUNDING_CLINIC,
        );

        return redirect()->route('finance.wallets.show', $patient)
            ->with('success', '₹' . number_format($request->amount, 0) . ' wallet credit added for ' . $patient->name . '.');
    }

    // ── Permission helper ─────────────────────────────────────────────────────
    // Admin bypasses; everyone else is checked against role_billing_permissions.

    private function ensureBilling(string $actionKey): void
    {
        $user = auth()->user();
        if ($user->isAdminRole()) {
            return;
        }
        $role = $user->roleModel;
        if (! $role || ! $role->billingCan($actionKey)) {
            abort(403, 'You do not have permission for this wallet action.');
        }
    }

    // ── Receive Advance / Recharge (money INTO wallet, no invoice) ────────────
    // A patient can pay money with no invoice on record; it becomes wallet credit
    // and is available for future invoices. Recorded as income in finance ledger.

    public function receiveAdvance(Request $request, Patient $patient)
    {
        $this->ensureBilling(RoleBillingPermission::ADVANCE_ADJUSTMENT);

        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'payment_mode' => 'required|in:cash,card,debit_card,upi,cheque,netbanking,bank_transfer,other',
            'payment_date' => 'required|date',
            'notes'        => 'nullable|string|max:300',
        ]);

        // Shared brain — same deposit + FinanceTransaction + audit chain the
        // mobile API uses (WalletService::receiveAdvance).
        $this->walletService->receiveAdvance(
            patient:     $patient,
            amount:      (float) $request->amount,
            paymentMode: $request->payment_mode,
            paymentDate: $request->payment_date,
            notes:       $request->notes,
            createdBy:   auth()->id(),
        );

        $msg = '₹' . number_format($request->amount, 0) . ' advance added to ' . $patient->name . "'s wallet.";

        // Came from the patient profile? Return there; otherwise the wallet ledger.
        if ($request->filled('from_patient')) {
            return redirect()->route('patients.show', $request->from_patient)->with('success', $msg);
        }

        return redirect()->route('finance.wallets.show', $patient)->with('success', $msg);
    }

    // ── Refund (money OUT of wallet, back to patient) ─────────────────────────

    /**
     * A1 — FULL wallet refund. Tulip Dental does not do partial refunds.
     *
     * The old version validated the requested amount against balance_permanent
     * but WalletService::withdraw() capped at balance_patient_credit, so a
     * clinic-funded gift made the form offer money that could never actually be
     * withdrawn: withdraw() returned 0.0, the closure returned early, no wallet
     * row, no finance row, no audit row — and the flash message reported
     * $request->amount as refunded. Cash left the drawer with nothing on the
     * books. Eligibility and execution now live in one place and every failure
     * throws.
     */
    public function refund(Request $request, Patient $patient)
    {
        $this->ensureBilling(RoleBillingPermission::WALLET_REFUND);

        $request->validate([
            // No arbitrary amount. If one is submitted it is a CONFIRMATION
            // value only — the service rejects it unless it equals the full
            // refundable balance, so a request for a partial refund fails
            // loudly instead of quietly becoming a full one.
            'amount'       => 'nullable|numeric',
            'payment_mode' => 'required|in:cash,upi,bank_transfer,cheque,other',
            'refund_date'  => 'required|date',
            'reason'       => 'required|string|min:3|max:300',
        ]);

        $result = $this->walletService->refundFullPatientCredit(
            patientId:      $patient->id,
            paymentMode:    $request->payment_mode,
            refundDate:     $request->refund_date,
            reason:         $request->reason,
            createdBy:      auth()->id(),
            expectedAmount: $request->filled('amount') ? (float) $request->amount : null,
        );

        return redirect()->route('finance.wallets.show', $patient)
            ->with('success', '₹' . number_format($result['refunded'], 2)
                . ' refunded from ' . $patient->name . "'s wallet — full patient credit returned.");
    }

    // ── Adjustment (manual correction, credit or debit) ───────────────────────

    public function adjust(Request $request, Patient $patient)
    {
        $this->ensureBilling(RoleBillingPermission::WALLET_ADJUSTMENT);

        $request->validate([
            'amount'    => 'required|numeric|min:1',
            'direction' => 'required|in:credit,debit',
            'reason'    => 'required|string|min:3|max:300',
        ]);

        $tx = $this->walletService->adjust(
            patientId: $patient->id,
            amount:    (float) $request->amount,
            direction: $request->direction,
            reason:    $request->reason,
            createdBy: auth()->id(),
        );

        if (! $tx) {
            return back()->withErrors(['amount' => 'Adjustment could not be applied (debit may exceed balance).'])->withInput();
        }

        BillingAuditLog::record('wallet_adjustment', $tx,
            ucfirst($request->direction) . ' Rs. ' . number_format($request->amount, 2) . '. ' . $request->reason,
            auth()->id(), 'Wallet · ' . $patient->name);

        return redirect()->route('finance.wallets.show', $patient)
            ->with('success', 'Wallet adjustment recorded for ' . $patient->name . '.');
    }

    // ── Credit Note: printable ────────────────────────────────────────────────

    public function creditNote(Patient $patient, WalletTransaction $transaction)
    {
        abort_if($transaction->patient_id !== $patient->id, 404);

        // Credits (advance receipt / credit note) and the ONE debit that is a
        // patient-facing document: a wallet refund. Every other debit is an
        // internal consumption against an invoice — the invoice receipt is that
        // document, so those stay blocked. Reusing this route deliberately: a
        // refund voucher is the same kind of object, and a second route would
        // just be a duplicate.
        $isRefund = $transaction->direction === 'debit'
                 && $transaction->source    === 'withdrawal';

        abort_if($transaction->direction !== 'credit' && ! $isRefund, 404);

        $clinicName    = AppSetting::get('clinic_name', config('app.name'));
        $clinicAddress = AppSetting::get('clinic_address', '');
        $clinicPhone   = AppSetting::get('clinic_phone', '');
        $clinicEmail   = AppSetting::get('clinic_email', '');
        $clinicLogo    = AppSetting::get('clinic_logo', null);

        return view('finance.wallets.credit-note', compact(
            'patient', 'transaction',
            'clinicName', 'clinicAddress', 'clinicPhone', 'clinicEmail', 'clinicLogo'
        ));
    }
}
