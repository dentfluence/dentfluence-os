@extends('layouts.app')
@section('page-title', 'Wallet Transaction Register')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    <a href="{{ route('finance.wallet.index') }}" class="inline-block text-sm text-gray-500 hover:text-[#6a0f70] mb-4">← Wallets</a>

    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Wallet Transaction Register</h1>
            <p class="text-sm text-gray-500 mt-0.5">Complete audit trail of all wallet credits and debits.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('finance.wallet.register.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
               target="_blank"
               class="border border-gray-300 text-gray-600 text-sm px-3 py-2 hover:bg-gray-50 transition-colors">
                ↓ PDF
            </a>
            <a href="{{ route('finance.wallet.register.export', array_merge(request()->query(), ['format' => 'excel'])) }}"
               class="border border-gray-300 text-gray-600 text-sm px-3 py-2 hover:bg-gray-50 transition-colors">
                ↓ Excel
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('finance.wallet.register') }}" class="bg-white border border-gray-200 rounded-lg p-4 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Search patient name / phone..."
                   class="border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">

            <select name="patient_id" class="border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                <option value="">All Patients</option>
                @foreach($patients as $p)
                    <option value="{{ $p->id }}" @selected(request('patient_id') == $p->id)>{{ $p->name }}</option>
                @endforeach
            </select>

            <input type="date" name="from" value="{{ request('from') }}"
                   class="border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]"
                   placeholder="From date">

            <input type="date" name="to" value="{{ request('to') }}"
                   class="border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]"
                   placeholder="To date">
        </div>
        <div class="flex gap-2 mt-3">
            <button type="submit"
                    class="bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                Apply Filters
            </button>
            <a href="{{ route('finance.wallet.register') }}"
               class="border border-gray-300 text-gray-600 text-sm px-4 py-2 hover:bg-gray-50 transition-colors">
                Clear
            </a>
        </div>
    </form>

    {{-- Summary totals --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
        <div class="bg-white border border-gray-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Credits</div>
            <div class="text-xl font-bold text-green-600">Rs. {{ number_format($totalCredits, 0) }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Debits</div>
            <div class="text-xl font-bold text-red-500">Rs. {{ number_format($totalDebits, 0) }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Net</div>
            <div class="text-xl font-bold text-[#6a0f70]">Rs. {{ number_format($totalCredits - $totalDebits, 0) }}</div>
        </div>
    </div>

    {{-- Register table --}}
    <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-100">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Date</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Patient</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 text-green-700">Credit</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 text-red-600">Debit</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Type</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Source</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Invoice</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($transactions as $tx)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                            {{ $tx->created_at->format('d M Y') }}
                            <div class="text-gray-400">{{ $tx->created_at->format('h:i A') }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('finance.wallets.show', $tx->patient) }}"
                               class="font-medium text-gray-800 hover:text-[#6a0f70] text-sm">
                                {{ $tx->patient?->name ?? '—' }}
                            </a>
                            <div class="text-xs text-gray-400">{{ $tx->patient?->phone }}</div>
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-green-600">
                            @if($tx->direction === 'credit')
                                +Rs. {{ number_format($tx->amount, 0) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-red-500">
                            @if($tx->direction === 'debit')
                                −Rs. {{ number_format($tx->amount, 0) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($tx->credit_type === 'promotional')
                                <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">Promotional</span>
                                @if($tx->campaign_name)
                                    <div class="text-xs text-amber-600 mt-0.5">{{ $tx->campaign_name }}</div>
                                @endif
                            @elseif($tx->credit_type === 'permanent')
                                <span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded">Credit</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 capitalize">
                            {{ ucwords(str_replace('_', ' ', $tx->source ?? '')) }}
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @php
                                $invNo = $tx->invoice_number ?? $tx->invoice?->invoice_number;
                            @endphp
                            @if($invNo)
                                <a href="{{ route('billing.show', $tx->invoice_id) }}"
                                   class="text-[#6a0f70] hover:underline font-medium">
                                    {{ $invNo }}
                                </a>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                            {{ $tx->notes ?: '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">
                            No wallet transactions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>

</div>
@endsection
