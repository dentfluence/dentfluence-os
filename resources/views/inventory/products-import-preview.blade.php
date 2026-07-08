@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto py-8 px-4">

    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('inventory.products.import') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] flex items-center gap-1 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back — re-upload
        </a>
        <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Preview Import</h1>
        <p class="text-sm text-gray-500 mt-1">Showing first 10 of <strong>{{ $totalRows }}</strong> rows.</p>
    </div>

    {{-- Stats bar --}}
    <div class="flex gap-4 mb-5">
        <div class="flex-1 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 text-center">
            <div class="text-2xl font-bold text-blue-700">{{ $totalRows }}</div>
            <div class="text-xs text-blue-600 mt-0.5">Total Rows</div>
        </div>
        <div class="flex-1 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-center">
            <div class="text-2xl font-bold text-green-700">{{ count($valid) }}</div>
            <div class="text-xs text-green-600 mt-0.5">Ready to Import</div>
        </div>
        <div class="flex-1 bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-center">
            <div class="text-2xl font-bold text-red-700">{{ count($errors) }}</div>
            <div class="text-xs text-red-600 mt-0.5">Will Be Skipped</div>
        </div>
    </div>

    {{-- Errors --}}
    @if(count($errors) > 0)
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <div class="text-sm font-semibold text-red-800 mb-1">Rows that will be skipped:</div>
            <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5 max-h-40 overflow-y-auto">
                @foreach($errors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Preview table — Products --}}
    <h2 class="text-sm font-semibold text-gray-700 mb-2">Products</h2>
    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        @foreach(['Product Name','Category','Brand','Packaging','Qty','Unit','Purchase Price','MRP','Min Stock','Vendor'] as $col)
                            <th class="text-left px-3 py-2 text-gray-600 font-semibold whitespace-nowrap text-xs">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($preview as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-800 font-medium max-w-[160px] truncate">{{ $row['product_name'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 max-w-[120px] truncate">{{ $row['category'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 max-w-[120px] truncate">{{ $row['brand'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['packaging_type'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['qty_in_packaging'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['packaging_unit_label'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['last_purchase_price'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['mrp'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['minimum_qty'] ?: '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 max-w-[120px] truncate">{{ $row['vendor_name'] ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-4 py-6 text-center text-gray-400">No product rows parsed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($totalRows > 10)
            <div class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
                … and {{ $totalRows - 10 }} more rows (not shown)
            </div>
        @endif
    </div>

    {{-- Preview table — Vendors (only if the workbook had a Vendors sheet) --}}
    @if($totalVendors > 0)
        <h2 class="text-sm font-semibold text-gray-700 mb-2">
            Vendors <span class="text-gray-400 font-normal">({{ count($validVendors) }} ready, {{ count($vendorErrors) }} skipped)</span>
        </h2>
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            @foreach(['Vendor Name','Contact Person','Phone','Email','City'] as $col)
                                <th class="text-left px-3 py-2 text-gray-600 font-semibold whitespace-nowrap text-xs">{{ $col }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($vendorPreview as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-800 font-medium max-w-[160px] truncate">{{ $row['vendor_name'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 max-w-[120px] truncate">{{ $row['contact_person'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['phone'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 max-w-[140px] truncate">{{ $row['email'] ?: '—' }}</td>
                                <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $row['city'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($totalVendors > 10)
                <div class="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
                    … and {{ $totalVendors - 10 }} more rows (not shown)
                </div>
            @endif
        </div>
    @endif

    {{-- Combined error list --}}
    @if(count($errors) + count($vendorErrors) > 0)
        <div class="mb-6 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <div class="text-sm font-semibold text-red-800 mb-1">Rows that will be skipped:</div>
            <ul class="text-xs text-red-700 list-disc list-inside space-y-0.5 max-h-40 overflow-y-auto">
                @foreach($errors as $err)
                    <li>{{ $err }}</li>
                @endforeach
                @foreach($vendorErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Confirm form --}}
    <form action="{{ route('inventory.products.import.store') }}" method="POST" class="bg-white border border-gray-200 rounded-lg p-5">
        @csrf
        <div class="flex gap-3">
            <a href="{{ route('inventory.products.import') }}"
                class="flex-1 text-center border border-gray-300 text-gray-700 py-2.5 text-sm font-medium hover:bg-gray-50 transition-colors rounded">
                ← Cancel
            </a>
            <button type="submit" {{ (count($valid) + count($validVendors)) === 0 ? 'disabled' : '' }}
                class="flex-1 bg-[#6a0f70] text-white py-2.5 px-6 text-sm font-medium hover:bg-[#380740] transition-colors rounded disabled:opacity-40 disabled:cursor-not-allowed">
                Import {{ count($valid) }} Product{{ count($valid) !== 1 ? 's' : '' }}
                @if(count($validVendors) > 0)
                    &amp; {{ count($validVendors) }} Vendor{{ count($validVendors) !== 1 ? 's' : '' }}
                @endif
            </button>
        </div>
    </form>
</div>
@endsection
