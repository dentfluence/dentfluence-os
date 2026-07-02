@extends('layouts.app')
@section('page-title', 'Coupon Codes')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    <a href="{{ route('finance.dashboard') }}" class="inline-block text-sm text-gray-500 hover:text-[#6a0f70] mb-4">← Finance</a>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Coupon Codes</h1>
            <p class="text-sm text-gray-500 mt-0.5">Create and manage discount coupons for invoices.</p>
        </div>
        <a href="{{ route('finance.coupons.create') }}"
           class="bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
            + New Coupon
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Dashboard Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
        <div class="bg-white border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-800">{{ $totalCoupons }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Coupons Created</p>
        </div>
        <div class="bg-white border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $activeCoupons }}</p>
            <p class="text-xs text-gray-500 mt-1">Active Coupons</p>
        </div>
        <div class="bg-white border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-gray-400">{{ $expiredInactive }}</p>
            <p class="text-xs text-gray-500 mt-1">Expired / Inactive</p>
        </div>
        <div class="bg-white border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ $totalRedemptions }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Redemptions</p>
        </div>
        <div class="bg-white border border-gray-200 p-4 text-center">
            <p class="text-2xl font-bold text-emerald-600">Rs. {{ number_format($totalDiscountGiven, 0) }}</p>
            <p class="text-xs text-gray-500 mt-1">Total Discount Given</p>
        </div>
        <div class="bg-white border border-gray-200 p-4 text-center">
            @if($mostUsedCoupon)
                <p class="text-lg font-bold text-[#6a0f70] font-mono leading-tight">{{ $mostUsedCoupon->code }}</p>
                <p class="text-xs text-gray-400">{{ $mostUsedCoupon->uses_count }} uses</p>
            @else
                <p class="text-2xl font-bold text-gray-300">—</p>
            @endif
            <p class="text-xs text-gray-500 mt-1">Most Used Coupon</p>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Code</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Description</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Discount</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Uses</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Valid Until</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($coupons as $coupon)
                    <tr class="{{ $coupon->trashed() ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3 font-mono font-semibold text-[#6a0f70]">
                            {{ $coupon->code }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $coupon->description ?: '—' }}</td>
                        <td class="px-4 py-3 font-medium">{{ $coupon->discountLabel() }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $coupon->uses_count }}
                            @if($coupon->max_uses_global > 0)
                                / {{ $coupon->max_uses_global }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            @if($coupon->valid_until)
                                {{ $coupon->valid_until->format('d M Y') }}
                                @if($coupon->isExpired())
                                    <span class="text-red-500 text-xs ml-1">Expired</span>
                                @endif
                            @else
                                <span class="text-gray-400">No expiry</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($coupon->trashed())
                                <span class="bg-gray-100 text-gray-500 text-xs px-2 py-0.5">Deleted</span>
                            @elseif($coupon->is_active)
                                <span class="bg-green-100 text-green-700 text-xs px-2 py-0.5">Active</span>
                            @else
                                <span class="bg-red-100 text-red-600 text-xs px-2 py-0.5">Inactive</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 flex items-center gap-2">
                            @unless($coupon->trashed())
                                <a href="{{ route('finance.coupons.edit', $coupon) }}"
                                   class="text-blue-600 hover:underline text-xs">Edit</a>

                                <form method="POST" action="{{ route('finance.coupons.toggle', $coupon) }}">
                                    @csrf
                                    <button type="submit" class="text-xs {{ $coupon->is_active ? 'text-amber-600' : 'text-green-600' }} hover:underline">
                                        {{ $coupon->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('finance.coupons.destroy', $coupon) }}"
                                      onsubmit="return confirm('Delete this coupon?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:underline text-xs">Delete</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">
                            No coupon codes yet. <a href="{{ route('finance.coupons.create') }}" class="text-[#6a0f70] hover:underline">Create the first one →</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $coupons->links() }}</div>

</div>
@endsection
