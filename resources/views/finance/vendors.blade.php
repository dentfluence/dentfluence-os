@extends('layouts.app')
@section('page-title', 'Vendors — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- ── HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Vendors
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Vendors</h1>
        </div>
        <a href="{{ route('finance.vendors.create') }}"
           class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Vendor
        </a>
    </div>

    @session('success')
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ $value }}</div>
    @endsession

    {{-- ── FILTER ── --}}
    <form method="GET" action="{{ route('finance.vendors') }}" class="bg-white border border-[#e8d5f0] p-4">
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Vendor or company name..."
                       class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Type</label>
                <select name="type" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Types</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">Filter</button>
            <a href="{{ route('finance.vendors') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
        </div>
    </form>

    {{-- ── TABLE ── --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        @if($vendors->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">
            No vendors yet. <a href="{{ route('finance.vendors.create') }}" class="text-[#6a0f70] hover:underline">Add one →</a>
        </div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">GSTIN</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Expenses</th>
                    <th class="text-center px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($vendors as $vendor)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800">{{ $vendor->company_name ?: $vendor->vendor_name }}</div>
                        @if($vendor->company_name && $vendor->vendor_name !== $vendor->company_name)
                            <div class="text-xs text-gray-400">{{ $vendor->vendor_name }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-sm capitalize">{{ $types[$vendor->vendor_type] ?? $vendor->vendor_type }}</td>
                    <td class="px-4 py-3 text-gray-600 text-sm">
                        {{ $vendor->phone ?? '' }}
                        @if($vendor->email)
                            <div class="text-xs text-gray-400">{{ $vendor->email }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs font-mono text-gray-600">{{ $vendor->gstin ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-sm text-gray-700">{{ $vendor->expenses_count }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $vendor->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $vendor->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('finance.vendors.edit', $vendor) }}" class="text-xs text-[#6a0f70] hover:underline mr-2">Edit</a>
                        <form method="POST" action="{{ route('finance.vendors.destroy', $vendor) }}" class="inline"
                              onsubmit="return confirm('Remove this vendor?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Del</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($vendors->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $vendors->links() }}</div>
        @endif
        @endif
    </div>

</div>
@endsection
