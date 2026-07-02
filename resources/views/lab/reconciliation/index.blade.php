@extends('layouts.app')
@section('page-title', 'Lab Reconciliation')

@section('content')
<div class="p-6 space-y-5">

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('lab.index') }}" class="hover:text-indigo-600">Lab</a>
                &nbsp;/&nbsp; Reconciliation
            </p>
            <h1 class="text-2xl font-semibold text-indigo-700 mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Monthly Lab Reconciliation
            </h1>
        </div>
        <a href="{{ route('lab.reconciliation.create') }}"
           class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            New Reconciliation
        </a>
    </div>

    {{-- FLASH --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- KPI STRIP --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        @foreach([
            ['label'=>'Draft',          'value'=>$kpis['draft'],                         'color'=>'gray'],
            ['label'=>'Pending Review', 'value'=>$kpis['pending_review'],                 'color'=>'amber'],
            ['label'=>'Approved',       'value'=>$kpis['approved'],                       'color'=>'green'],
            ['label'=>'Approved Value', 'value'=>'Rs. '.number_format($kpis['total_approved'],2), 'color'=>'indigo'],
            ['label'=>'Disputed',       'value'=>$kpis['disputed'],                       'color'=>'red'],
        ] as $k)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $k['label'] }}</p>
            <p class="text-xl font-bold text-{{ $k['color'] }}-600">{{ $k['value'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- FILTERS --}}
    <form method="GET" class="flex flex-wrap gap-3 bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
        <select name="vendor_id" class="text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All Vendors</option>
            @foreach($vendors as $v)
                <option value="{{ $v->id }}" @selected($vendorId == $v->id)>{{ $v->name }}</option>
            @endforeach
        </select>

        <select name="status" class="text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All Statuses</option>
            @foreach(App\Models\LabMonthlyReconciliation::STATUS_LABELS as $k => $label)
                <option value="{{ $k }}" @selected($status === $k)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="year" class="text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
            @foreach(range(now()->year, 2023) as $y)
                <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
            @endforeach
        </select>

        <button type="submit" class="text-sm bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">Filter</button>
        <a href="{{ route('lab.reconciliation.index') }}" class="text-sm text-gray-500 px-3 py-2 rounded-lg hover:bg-gray-50">Clear</a>
    </form>

    {{-- TABLE --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-widest">
                <tr>
                    <th class="px-4 py-3 text-left">Ref</th>
                    <th class="px-4 py-3 text-left">Vendor</th>
                    <th class="px-4 py-3 text-left">Period</th>
                    <th class="px-4 py-3 text-right">Our Total</th>
                    <th class="px-4 py-3 text-right">Vendor Total</th>
                    <th class="px-4 py-3 text-right">Agreed</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($reconciliations as $rec)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono font-semibold text-indigo-700">
                        <a href="{{ route('lab.reconciliation.show', $rec) }}" class="hover:underline">
                            {{ $rec->reconciliation_ref }}
                        </a>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $rec->labVendor?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $rec->getBillingPeriodLabel() }}</td>
                    <td class="px-4 py-3 text-right font-mono text-gray-700">Rs. {{ number_format($rec->our_total, 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono text-gray-700">Rs. {{ number_format($rec->vendor_total, 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-indigo-700">Rs. {{ number_format($rec->agreed_amount, 2) }}</td>
                    <td class="px-4 py-3 text-center">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium {{ App\Models\LabMonthlyReconciliation::STATUS_COLORS[$rec->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ App\Models\LabMonthlyReconciliation::STATUS_LABELS[$rec->status] ?? $rec->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <a href="{{ route('lab.reconciliation.show', $rec) }}"
                           class="text-indigo-600 hover:underline text-xs font-medium">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">No reconciliations found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- PAGINATION --}}
    <div>{{ $reconciliations->links() }}</div>
</div>
@endsection
