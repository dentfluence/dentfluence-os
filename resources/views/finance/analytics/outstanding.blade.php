@extends('layouts.app')
@section('page-title', 'Outstanding Liabilities — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Outstanding
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Outstanding Liabilities
            </h1>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Patient Outstanding</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">Rs. {{ number_format($kpis['patient_outstanding'],0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $kpis['overdue_patient_cnt'] }} overdue invoices</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Vendor Payables</p>
            <p class="text-2xl font-bold text-red-600 mt-1">Rs. {{ number_format($kpis['vendor_outstanding'],0) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $kpis['overdue_vendor_cnt'] }} overdue bills</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Lab Outstanding</p>
            <p class="text-2xl font-bold text-purple-600 mt-1">Rs. {{ number_format($kpis['lab_outstanding'],0) }}</p>
            <p class="text-xs text-gray-400 mt-1">Approved, awaiting payment</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4 col-span-3">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Liability</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">
                Rs. {{ number_format($kpis['patient_outstanding'] + $kpis['vendor_outstanding'] + $kpis['lab_outstanding'] + $kpis['procurement_due'],0) }}
            </p>
        </div>
    </div>

    {{-- Patient outstanding --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7] flex items-center justify-between">
            <p class="text-sm font-medium text-gray-700">Patient Outstanding Invoices</p>
            <a href="{{ route('finance.income', ['status' => 'unpaid']) }}" class="text-xs text-[#6a0f70] hover:underline">Income Ledger →</a>
        </div>
        @if($patientOutstanding->isEmpty())
            <div class="py-8 text-center text-gray-400 text-sm">No outstanding patient invoices</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Paid</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Balance</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($patientOutstanding as $inv)
                @php $isOverdue = $inv->due_date && $inv->due_date->isPast() && $inv->status !== 'paid'; @endphp
                <tr class="{{ $isOverdue ? 'bg-amber-50' : 'hover:bg-[#fdf8ff]' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('billing.show', $inv->id) }}" class="text-blue-600 hover:underline font-mono text-xs">
                            {{ $inv->invoice_number }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        @if($inv->patient)
                        <a href="{{ route('patients.show', $inv->patient_id) }}" class="text-[#6a0f70] hover:underline">{{ $inv->patient->name }}</a>
                        <div class="text-xs text-gray-400">{{ $inv->patient->phone }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($inv->total_amount,0) }}</td>
                    <td class="px-4 py-3 text-right text-green-600">Rs. {{ number_format($inv->paid_amount,0) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-amber-700">Rs. {{ number_format($inv->balance_due,0) }}</td>
                    <td class="px-4 py-3 text-right text-xs {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                        {{ $inv->due_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-block px-2 py-0.5 text-xs rounded-full
                            {{ $inv->status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($inv->status) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($patientOutstanding->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $patientOutstanding->links() }}</div>
        @endif
        @endif
    </div>

    {{-- Vendor payables --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7] flex items-center justify-between">
            <p class="text-sm font-medium text-gray-700">Vendor Payables (Unpaid Expenses)</p>
            <a href="{{ route('finance.expenses', ['tab'=>'unpaid']) }}" class="text-xs text-[#6a0f70] hover:underline">All Unpaid →</a>
        </div>
        @if($vendorOutstanding->isEmpty())
            <div class="py-8 text-center text-gray-400 text-sm">No outstanding vendor bills</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Expense</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Due Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($vendorOutstanding as $exp)
                @php $isOverdue = $exp->due_date && $exp->due_date->isPast(); @endphp
                <tr class="{{ $isOverdue ? 'bg-red-50' : 'hover:bg-[#fdf8ff]' }}">
                    <td class="px-4 py-3 text-gray-800">{{ $exp->title }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $exp->vendor?->vendor_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $exp->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold {{ $isOverdue ? 'text-red-700' : 'text-gray-900' }}">Rs. {{ number_format($exp->total_amount,0) }}</td>
                    <td class="px-4 py-3 text-right text-xs {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                        {{ $exp->due_date?->format('d M Y') ?? '—' }}
                        @if($isOverdue)<span class="ml-1 text-red-500">({{ $exp->due_date->diffInDays(today()) }}d overdue)</span>@endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($vendorOutstanding->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $vendorOutstanding->links() }}</div>
        @endif
        @endif
    </div>

    {{-- Lab outstanding --}}
    @if($labOutstanding->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Lab Reconciliation Outstanding</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Ref</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Lab</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Period</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Agreed</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($labOutstanding as $rec)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $rec->reconciliation_ref }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $rec->labVendor?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ \Carbon\Carbon::create($rec->billing_year,$rec->billing_month)->format('M Y') }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-purple-700">Rs. {{ number_format($rec->agreed_amount,0) }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('lab.reconciliation.show',$rec->id) }}" class="text-xs text-[#6a0f70] hover:underline">Pay →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>
@endsection
