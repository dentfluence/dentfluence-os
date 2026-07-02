@extends('layouts.app')
@section('page-title', ($vendor ? 'Edit' : 'Add') . ' Vendor — Finance')

@section('content')
<div class="p-6 max-w-2xl">

    <div class="mb-6">
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
            &nbsp;/&nbsp; <a href="{{ route('finance.vendors') }}" class="hover:text-[#6a0f70]">Vendors</a>
            &nbsp;/&nbsp; {{ $vendor ? 'Edit' : 'Add Vendor' }}
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
            {{ $vendor ? 'Edit Vendor' : 'Add Vendor' }}
        </h1>
    </div>

    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 mb-5">{{ $errors->first() }}</div>
    @endif

    <form method="POST"
          action="{{ $vendor ? route('finance.vendors.update', $vendor) : route('finance.vendors.store') }}"
          class="bg-white border border-[#e8d5f0] p-6 space-y-5">
        @csrf
        @if($vendor) @method('PUT') @endif

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Vendor Name <span class="text-red-500">*</span></label>
                <input type="text" name="vendor_name" value="{{ old('vendor_name', $vendor?->vendor_name) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Company Name</label>
                <input type="text" name="company_name" value="{{ old('company_name', $vendor?->company_name) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Vendor Type <span class="text-red-500">*</span></label>
            <select name="vendor_type" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                @foreach(['lab'=>'Lab','implant_company'=>'Implant Company','dental_supplier'=>'Dental Supplier','marketing_agency'=>'Marketing Agency','software_vendor'=>'Software Vendor','consultant'=>'Consultant','ca'=>'CA / Accountant','utility_provider'=>'Utility Provider','equipment_supplier'=>'Equipment Supplier','other'=>'Other'] as $key => $label)
                    <option value="{{ $key }}" {{ old('vendor_type', $vendor?->vendor_type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Phone</label>
                <input type="text" name="phone" value="{{ old('phone', $vendor?->phone) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $vendor?->email) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">GSTIN</label>
                <input type="text" name="gstin" value="{{ old('gstin', $vendor?->gstin) }}" placeholder="22AAAAA0000A1Z5"
                       class="w-full border border-gray-300 text-sm px-3 py-2 font-mono focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">PAN</label>
                <input type="text" name="pan" value="{{ old('pan', $vendor?->pan) }}" placeholder="AAAAA0000A"
                       class="w-full border border-gray-300 text-sm px-3 py-2 font-mono focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Address</label>
            <textarea name="address" rows="2"
                      class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">{{ old('address', $vendor?->address) }}</textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">City</label>
                <input type="text" name="city" value="{{ old('city', $vendor?->city) }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Credit Days</label>
                <input type="number" name="credit_days" value="{{ old('credit_days', $vendor?->credit_days ?? 0) }}" min="0"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        <fieldset class="border border-gray-200 p-4">
            <legend class="text-xs text-gray-500 uppercase tracking-wider px-2">Bank Details (for NEFT)</legend>
            <div class="grid grid-cols-2 gap-4 mt-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Bank Name</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $vendor?->bank_name) }}"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">IFSC Code</label>
                    <input type="text" name="ifsc_code" value="{{ old('ifsc_code', $vendor?->ifsc_code) }}"
                           class="w-full border border-gray-300 text-sm px-3 py-2 font-mono focus:outline-none focus:border-[#6a0f70]">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Number</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $vendor?->account_number) }}"
                           class="w-full border border-gray-300 text-sm px-3 py-2 font-mono focus:outline-none focus:border-[#6a0f70]">
                </div>
            </div>
        </fieldset>

        <div>
            <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Notes</label>
            <textarea name="notes" rows="2"
                      class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">{{ old('notes', $vendor?->notes) }}</textarea>
        </div>

        @if($vendor)
        <div class="flex items-center gap-3">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" id="activeToggle" value="1"
                   {{ $vendor->is_active ? 'checked' : '' }}
                   class="h-4 w-4 text-[#6a0f70] border-gray-300">
            <label for="activeToggle" class="text-sm text-gray-700">Active</label>
        </div>
        @endif

        <div class="flex items-center gap-4 pt-2 border-t border-gray-100">
            <button type="submit" dusk="vendor-save" class="bg-[#6a0f70] text-white text-sm px-6 py-2 hover:bg-[#380740] transition-colors">
                {{ $vendor ? 'Update Vendor' : 'Save Vendor' }}
            </button>
            <a href="{{ route('finance.vendors') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
