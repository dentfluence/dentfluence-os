{{-- resources/views/billing/index.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Billing')

@section('content')
<div class="p-4 md:p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-800">Invoices</h2>
        <a href="{{ route('billing.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Invoice
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Total Billed</p>
            <p class="text-lg font-bold text-gray-800">Rs. {{ number_format($summary->total_billed ?? 0, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Collected</p>
            <p class="text-lg font-bold text-green-600">Rs. {{ number_format($summary->total_collected ?? 0, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Outstanding</p>
            <p class="text-lg font-bold text-red-500">Rs. {{ number_format($summary->total_outstanding ?? 0, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 mb-1">Invoices</p>
            <p class="text-lg font-bold text-gray-800">{{ $summary->total ?? 0 }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('billing.index') }}"
          class="flex flex-wrap gap-3 items-end bg-white border border-gray-200 rounded-xl p-4">
        <div class="flex-1 min-w-48">
            <label class="block text-xs text-gray-500 mb-1">Search patient / invoice #</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Name, phone or INV-..."
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status"
                    class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                @foreach(['draft','sent','partial','paid','cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>
                        {{ ucfirst($s) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                Filter
            </button>
            <a href="{{ route('billing.index') }}"
               class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200">
                Reset
            </a>
        </div>
    </form>

    {{-- Table --}}
    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Invoice #</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                    <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Total</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Paid</th>
                    <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Balance</th>
                    <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($invoices as $inv)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-mono text-blue-600">
                        <a href="{{ route('billing.show', $inv) }}" class="hover:underline">
                            {{ $inv->invoice_number }}
                        </a>
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800">{{ $inv->patient->name ?? '—' }}</p>
                        <p class="text-xs text-gray-400">{{ $inv->patient->phone ?? '' }}</p>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $inv->invoice_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-right font-medium">Rs. {{ number_format($inv->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right text-green-600">Rs. {{ number_format($inv->paid_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right {{ $inv->balance_due > 0 ? 'text-red-500' : 'text-gray-400' }}">
                        Rs. {{ number_format($inv->balance_due, 2) }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php
                            $colors = [
                                'paid'      => 'bg-green-100 text-green-700',
                                'partial'   => 'bg-yellow-100 text-yellow-700',
                                'draft'     => 'bg-gray-100 text-gray-600',
                                'sent'      => 'bg-blue-100 text-blue-700',
                                'cancelled' => 'bg-red-100 text-red-600',
                                'refunded'  => 'bg-purple-100 text-purple-700',
                            ];
                            $cls = $colors[$inv->status] ?? 'bg-gray-100 text-gray-600';
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $cls }}">
                            {{ ucfirst($inv->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('billing.show', $inv) }}"
                               class="text-gray-400 hover:text-blue-600" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                                             -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </a>
                            @if(!in_array($inv->status, ['paid','cancelled']))
                            <a href="{{ route('billing.edit', $inv) }}"
                               class="text-gray-400 hover:text-yellow-600" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5
                                             m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </a>
                            <form method="POST" action="{{ route('billing.destroy', $inv) }}"
                                  onsubmit="return confirm('Delete this invoice?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-gray-400 hover:text-red-500" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858
                                                 L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                        No invoices found.
                        <a href="{{ route('billing.create') }}" class="text-blue-600 hover:underline ml-1">Create one</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($invoices->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $invoices->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
