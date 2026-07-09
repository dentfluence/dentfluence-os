<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceVendor;
use App\Models\Finance\FinanceVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * VoucherController — Phase 2 Voucher System.
 *
 * Routes:
 *   GET    /finance/vouchers                 index()
 *   GET    /finance/vouchers/{voucher}       show()
 *   GET    /finance/vouchers/{voucher}/print printView()
 *   GET    /finance/vouchers/export          export()
 *   DELETE /finance/vouchers/{voucher}       destroy()  (admin only — marks cancelled; no hard delete)
 *
 * Vouchers are created automatically in FinanceController::expenseMarkPaid().
 * This controller only handles viewing, printing, and exporting.
 */
class VoucherController extends Controller
{
    // ── INDEX ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $from        = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to          = $request->filled('to')   ? $request->to   : today()->toDateString();
        $search      = $request->input('search');
        $vendorId    = $request->input('vendor_id');
        $mode        = $request->input('payment_mode');
        $showVoided  = $request->boolean('show_voided');

        $query = FinanceVoucher::with(['expense.category', 'vendor', 'createdBy', 'voidedBy'])
            ->whereBetween('voucher_date', [$from, $to])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id');

        if (! $showVoided) {
            $query->active();
        }

        if ($search) {
            $query->where(fn($q) => $q
                ->where('voucher_number', 'like', "%{$search}%")
                ->orWhere('vendor_name', 'like', "%{$search}%")
                ->orWhere('purpose', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%"));
        }

        if ($vendorId) { $query->where('vendor_id', $vendorId); }
        if ($mode)     { $query->where('payment_mode', $mode); }

        $vouchers = $query->paginate(25)->withQueryString();

        // Summary strip — active vouchers only, voided ones don't count as paid out
        $summary = FinanceVoucher::whereBetween('voucher_date', [$from, $to])
            ->active()
            ->selectRaw('SUM(amount) as total, COUNT(*) as cnt')
            ->first();

        $vendors = FinanceVendor::where('is_active', true)->orderBy('vendor_name')->get(['id', 'vendor_name']);
        $modes   = ['cash', 'upi', 'card', 'bank_transfer', 'cheque', 'other'];

        return view('finance.vouchers', compact(
            'vouchers', 'summary', 'vendors', 'modes',
            'from', 'to', 'search', 'vendorId', 'mode', 'showVoided'
        ));
    }

    // ── SHOW ──────────────────────────────────────────────────────────────

    public function show(FinanceVoucher $voucher)
    {
        $voucher->load(['expense.category', 'vendor', 'createdBy', 'approvedBy']);
        return view('finance.voucher-show', compact('voucher'));
    }

    // ── PRINT VIEW ────────────────────────────────────────────────────────

    public function printView(FinanceVoucher $voucher)
    {
        $voucher->load(['expense.category', 'vendor', 'createdBy', 'approvedBy']);
        return view('finance.voucher-print', compact('voucher'));
    }

    // ── EXCEL EXPORT ──────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $from     = $request->filled('from') ? $request->from : today()->startOfMonth()->toDateString();
        $to       = $request->filled('to')   ? $request->to   : today()->toDateString();
        $vendorId = $request->input('vendor_id');
        $mode     = $request->input('payment_mode');

        $query = FinanceVoucher::with(['expense.category', 'vendor', 'createdBy'])
            ->whereBetween('voucher_date', [$from, $to])
            ->active() // voided vouchers are excluded from the CA-facing export
            ->orderByDesc('voucher_date');

        if ($vendorId) { $query->where('vendor_id', $vendorId); }
        if ($mode)     { $query->where('payment_mode', $mode); }

        $vouchers = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Vouchers');

        // ── Header row ────────────────────────────────────────────────────
        $headers = [
            'A' => 'Voucher No',
            'B' => 'Date',
            'C' => 'Vendor',
            'D' => 'Purpose',
            'E' => 'Category',
            'F' => 'Amount (₹)',
            'G' => 'Payment Mode',
            'H' => 'Clinic Account',
            'I' => 'UTR / Reference',
            'J' => 'Cheque Number',
            'K' => 'Created By',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
        }

        // ── Data rows ─────────────────────────────────────────────────────
        $row = 2;
        foreach ($vouchers as $v) {
            $sheet->setCellValue("A{$row}", $v->voucher_number);
            $sheet->setCellValue("B{$row}", $v->voucher_date->format('d-m-Y'));
            $sheet->setCellValue("C{$row}", $v->vendor_name ?? ($v->vendor?->vendor_name ?? 'N/A'));
            $sheet->setCellValue("D{$row}", $v->purpose ?? '');
            $sheet->setCellValue("E{$row}", $v->expense?->category?->name ?? '');
            $sheet->setCellValue("F{$row}", $v->amount);
            $sheet->setCellValue("G{$row}", ucfirst(str_replace('_', ' ', $v->payment_mode ?? '')));
            $sheet->setCellValue("H{$row}", $v->clinic_account_name ?? '');
            $sheet->setCellValue("I{$row}", $v->reference ?? '');
            $sheet->setCellValue("J{$row}", $v->cheque_number ?? '');
            $sheet->setCellValue("K{$row}", $v->createdBy?->name ?? '');
            $row++;
        }

        // ── Totals row ────────────────────────────────────────────────────
        $sheet->setCellValue("E{$row}", 'TOTAL');
        $sheet->setCellValue("F{$row}", $vouchers->sum('amount'));
        $sheet->getStyle("E{$row}:F{$row}")->getFont()->setBold(true);

        $filename = 'vouchers_' . $from . '_to_' . $to . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $temp   = tempnam(sys_get_temp_dir(), 'voucher_');
        $writer->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── PDF EXPORT (HTML print) ───────────────────────────────────────────

    public function pdfExport(FinanceVoucher $voucher)
    {
        // Redirect to print view — browser handles PDF via window.print()
        return redirect()->route('finance.vouchers.print', $voucher);
    }

    // ── VOID (admin only — never a hard delete) ────────────────────────────

    /**
     * Void a voucher. The row is never deleted — it's marked status='voided'
     * with a required reason, so it stays visible in the register (struck
     * through) and the CA export. To correct the underlying payment, void this
     * one and record a fresh expense/payment, which generates a new voucher.
     */
    public function destroy(Request $request, FinanceVoucher $voucher)
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403, 'Only an admin can void a voucher.');
        }

        if ($voucher->isVoided()) {
            return back()->with('success', 'Voucher is already voided.');
        }

        $data = $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        $voucher->update([
            'status'      => 'voided',
            'void_reason' => $data['void_reason'],
            'voided_at'   => now(),
            'voided_by'   => auth()->id(),
        ]);

        return back()->with('success', "Voucher {$voucher->voucher_number} voided.");
    }
}
