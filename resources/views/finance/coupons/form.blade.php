@extends('layouts.app')
@section('page-title', $coupon ? 'Edit Coupon' : 'New Coupon Code')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <div class="mb-6">
        <a href="{{ route('finance.dashboard') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] mr-3">← Finance</a>
        <a href="{{ route('finance.coupons.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Back to Coupons</a>
        <h1 class="text-xl font-bold text-gray-800 mt-1">{{ $coupon ? 'Edit Coupon: ' . $coupon->code : 'New Coupon Code' }}</h1>
    </div>

    <form method="POST"
          action="{{ $coupon ? route('finance.coupons.update', $coupon) : route('finance.coupons.store') }}"
          class="bg-white border border-gray-200 p-6 space-y-5">
        @csrf
        @if($coupon) @method('PUT') @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Code --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Coupon Code <span class="text-red-500">*</span></label>
            <input type="text" name="code"
                   value="{{ old('code', $coupon?->code) }}"
                   placeholder="e.g. WELCOME20"
                   style="text-transform:uppercase"
                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70] font-mono uppercase"
                   required>
            <p class="text-xs text-gray-400 mt-1">Alphanumeric, no spaces. Patients enter this at billing.</p>
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <input type="text" name="description"
                   value="{{ old('description', $coupon?->description) }}"
                   placeholder="e.g. Welcome offer for new patients"
                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
        </div>

        {{-- Discount type + value --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type <span class="text-red-500">*</span></label>
                <select name="discount_type"
                        class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]"
                        required>
                    <option value="flat"       {{ old('discount_type', $coupon?->discount_type) === 'flat' ? 'selected' : '' }}>Flat Rs.  Amount</option>
                    <option value="percentage" {{ old('discount_type', $coupon?->discount_type) === 'percentage' ? 'selected' : '' }}>Percentage %</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value <span class="text-red-500">*</span></label>
                <input type="number" name="discount_value" step="0.01" min="0"
                       value="{{ old('discount_value', $coupon?->discount_value) }}"
                       placeholder="e.g. 200 or 10"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]"
                       required>
            </div>
        </div>

        {{-- Min invoice amount --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Minimum Invoice Amount (Rs. )</label>
            <input type="number" name="min_invoice_amount" step="0.01" min="0"
                   value="{{ old('min_invoice_amount', $coupon?->min_invoice_amount ?? 0) }}"
                   placeholder="0 = no minimum"
                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
        </div>

        {{-- Usage limits --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Uses (Global)</label>
                <input type="number" name="max_uses_global" min="0"
                       value="{{ old('max_uses_global', $coupon?->max_uses_global ?? 0) }}"
                       placeholder="0 = unlimited"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                <p class="text-xs text-gray-400 mt-1">0 = unlimited uses across all patients.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Uses Per Patient</label>
                <input type="number" name="max_uses_per_patient" min="1"
                       value="{{ old('max_uses_per_patient', $coupon?->max_uses_per_patient ?? 1) }}"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        {{-- Validity dates --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valid From</label>
                <input type="date" name="valid_from"
                       value="{{ old('valid_from', $coupon?->valid_from?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                <input type="date" name="valid_until"
                       value="{{ old('valid_until', $coupon?->valid_until?->format('Y-m-d')) }}"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        {{-- Active toggle --}}
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   {{ old('is_active', $coupon?->is_active ?? true) ? 'checked' : '' }}
                   class="w-4 h-4 text-[#6a0f70]">
            <label for="is_active" class="text-sm font-medium text-gray-700">Active (can be applied to invoices)</label>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="bg-[#6a0f70] text-white text-sm px-5 py-2 hover:bg-[#380740] transition-colors">
                {{ $coupon ? 'Update Coupon' : 'Create Coupon' }}
            </button>
            <a href="{{ route('finance.coupons.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>

</div>

<script>
// Auto-uppercase the code field
document.querySelector('[name="code"]').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/\s+/g, '');
});
</script>
@endsection
