@extends('layouts.app')
@section('page-title', 'Financial Audit Log — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Audit Log
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Financial Audit History
            </h1>
        </div>
    </div>

    {{-- Date filter --}}
    <form method="GET" action="{{ route('finance.analytics.audit') }}" class="bg-white border border-[#e8d5f0] p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740]">Filter</button>
            <a href="{{ route('finance.analytics.audit') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
        </div>
    </form>

    {{-- Summary strip --}}
    <div class="grid grid-cols-5 gap-3">
        @foreach([
            ['label'=>'Payments',         'val'=>'Rs. '.number_format($summary['payment_total'],0),    'color'=>'text-green-600'],
            ['label'=>'Vouchers',         'val'=>'Rs. '.number_format($summary['voucher_total'],0),    'color'=>'text-[#6a0f70]'],
            ['label'=>'Expenses',         'val'=>'Rs. '.number_format($summary['expense_total'],0),    'color'=>'text-red-600'],
            ['label'=>'Lab Recon',        'val'=>'Rs. '.number_format($summary['reconcil_total'],0),   'color'=>'text-purple-600'],
            ['label'=>'Vendor Invoices',  'val'=>'Rs. '.number_format($summary['vendor_inv_total'],0), 'color'=>'text-blue-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-3">
            <p class="text-xs text-gray-400 uppercase tracking-widest">{{ $c['label'] }}</p>
            <p class="text-lg font-bold {{ $c['color'] }} mt-1">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Tab navigation --}}
    <div class="border-b border-[#e8d5f0]">
        <nav class="flex gap-0 -mb-px">
            @foreach([
                'payments'      => 'Patient Payments',
                'vouchers'      => 'Vouchers',
                'expenses'      => 'Expenses',
                'lab'           => 'Lab Reconciliation',
                'procurement'   => 'Vendor Invoices',
            ] as $tab => $label)
            <button onclick="showTab('{{ $tab }}')" id="tab-{{ $tab }}"
                    class="tab-btn px-5 py-2.5 text-sm border-b-2 transition-colors
                           {{ $type === $tab || ($type === '' && $tab === 'payments')
                              ? 'border-[#6a0f70] text-[#6a0f70] font-medium'
                              : 'border-transparent text-gray-500 hover:text-[#6a0f70]' }}">
                {{ $label }}
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ── Patient Payments ── --}}
    <div id="panel-payments" class="audit-panel {{ $type !== 'payments' && $type !== '' ? 'hidden' : '' }}">
        @if($recentPayments->isEmpty())
            <div class="py-8 text-center text-gray-400 bg-white border border-[#e8d5f0]">No payments in this period.</div>
        @else
        <div class="bg-white border border-[#e8d5f0] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[#f9f4fb]">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Mode</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Reference</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentPayments as $p)
                    <tr class="hover:bg-[#fdf8ff]">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $p->payment_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('patients.show', $p->invoice?->patient_id ?? 0) }}" class="text-[#6a0f70] hover:underline">
                                {{ $p->invoice?->patient?->name ?? '—' }}
                            </a>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('billing.show', $p->invoice_id) }}" class="text-blue-600 hover:underline">
                                {{ $p->invoice?->invoice_number ?? '—' }}
                            </a>
                        </td>
                        <td class="px-4 py-3 capitalize text-gray-600">{{ $p->payment_mode ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $p->reference_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-green-700">Rs. {{ number_format($p->amount,0) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Vouchers ── --}}
    <div id="panel-vouchers" class="audit-panel {{ $type !== 'vouchers' ? 'hidden' : '' }}">
        @if($recentVouchers->isEmpty())
            <div class="py-8 text-center text-gray-400 bg-white border border-[#e8d5f0]">No vouchers in this period.</div>
        @else
        <div class="bg-white border border-[#e8d5f0] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[#f9f4fb]">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Voucher No.</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Purpose</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Mode</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentVouchers as $v)
                    <tr class="hover:bg-[#fdf8ff]">
                        <td class="px-4 py-3 font-mono text-xs text-[#6a0f70]">{{ $v->voucher_number }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $v->voucher_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $v->vendor_name ?? $v->expense?->vendor?->vendor_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $v->purpose ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize text-gray-600 text-xs">{{ $v->payment_mode ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-[#6a0f70]">Rs. {{ number_format($v->amount,0) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('finance.vouchers.show', $v->id) }}" class="text-xs text-[#6a0f70] hover:underline">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Expenses ── --}}
    <div id="panel-expenses" class="audit-panel {{ $type !== 'expenses' ? 'hidden' : '' }}">
        @if($recentExpenses->isEmpty())
            <div class="py-8 text-center text-gray-400 bg-white border border-[#e8d5f0]">No expenses in this period.</div>
        @else
        <div class="bg-white border border-[#e8d5f0] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[#f9f4fb]">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Title</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentExpenses as $exp)
                    <tr class="hover:bg-[#fdf8ff]">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $exp->expense_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $exp->title }}</td>
                        <td class="px-4 py-3 text-gray-500 text-xs">{{ $exp->category?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600 text-xs">{{ $exp->vendor?->vendor_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">Rs. {{ number_format($exp->total_amount,0) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full
                                {{ $exp->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ ucfirst($exp->payment_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-xs text-gray-400">{{ $exp->updated_at?->format('d M Y, H:i') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Lab Reconciliation ── --}}
    <div id="panel-lab" class="audit-panel {{ $type !== 'lab' ? 'hidden' : '' }}">
        @if($recentReconciliations->isEmpty())
            <div class="py-8 text-center text-gray-400 bg-white border border-[#e8d5f0]">No reconciliations in this period.</div>
        @else
        <div class="bg-white border border-[#e8d5f0] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[#f9f4fb]">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Ref</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Lab</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Period</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Our Total</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Agreed</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentReconciliations as $rec)
                    @php $colors = \App\Models\LabMonthlyReconciliation::STATUS_COLORS; @endphp
                    <tr class="hover:bg-[#fdf8ff]">
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $rec->reconciliation_ref }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $rec->labVendor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ \Carbon\Carbon::create($rec->billing_year,$rec->billing_month)->format('M Y') }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($rec->our_total,0) }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-[#6a0f70]">Rs. {{ number_format($rec->agreed_amount,0) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $colors[$rec->status] ?? 'bg-gray-100 text-gray-600' }}">
                                {{ \App\Models\LabMonthlyReconciliation::STATUS_LABELS[$rec->status] ?? ucfirst($rec->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('lab.reconciliation.show',$rec->id) }}" class="text-xs text-[#6a0f70] hover:underline">View →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Vendor Invoices ── --}}
    <div id="panel-procurement" class="audit-panel {{ $type !== 'procurement' ? 'hidden' : '' }}">
        @if($recentVendorInvoices->isEmpty())
            <div class="py-8 text-center text-gray-400 bg-white border border-[#e8d5f0]">No vendor invoices in this period.</div>
        @else
        <div class="bg-white border border-[#e8d5f0] overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-[#f9f4fb]">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice No.</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">PO Ref</th>
                        <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice Date</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                        <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($recentVendorInvoices as $vi)
                    <tr class="hover:bg-[#fdf8ff]">
                        <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $vi->invoice_number }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $vi->financeVendor?->vendor_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $vi->purchaseOrder?->order_no ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $vi->invoice_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold text-[#6a0f70]">Rs. {{ number_format($vi->total_amount,0) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="inline-block px-2 py-0.5 text-xs rounded-full
                                {{ $vi->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ ucfirst($vi->status) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.audit-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-[#6a0f70]','text-[#6a0f70]','font-medium');
        b.classList.add('border-transparent','text-gray-500');
    });
    document.getElementById('panel-'+tab).classList.remove('hidden');
    const btn = document.getElementById('tab-'+tab);
    btn.classList.remove('border-transparent','text-gray-500');
    btn.classList.add('border-[#6a0f70]','text-[#6a0f70]','font-medium');
}
// Activate initial tab
const activeTab = '{{ $type ?: "payments" }}';
showTab(activeTab);
</script>
@endsection
