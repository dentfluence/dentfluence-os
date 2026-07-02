@extends('layouts.app')

@section('title', 'Add Treatment — Clinic Knowledge Base')

@section('content')
{{--
    Add Treatment form.
    Posts to route('treatments.store') which maps to TreatmentController@store.
    Field names + validation rules mirror that controller exactly.
--}}
<div class="p-6 max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('treatments.index') }}"
           class="text-sm text-gray-500 hover:text-[#6a0f70]">&larr; Back to Treatments</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-1">Add Treatment</h1>
        <p class="text-sm text-gray-500">Create a new treatment in the catalogue. You can add the SOP and rules afterwards.</p>
    </div>

    {{-- Validation errors (shown together at the top) --}}
    @if ($errors->any())
        <div class="mb-4 border border-red-200 bg-red-50 text-red-700 text-sm p-3 rounded">
            Please fix the highlighted fields below.
        </div>
    @endif

    <form method="POST" action="{{ route('treatments.store') }}"
          class="bg-white border border-gray-200 rounded-lg p-6 space-y-5">
        @csrf

        {{-- Category --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
            <select name="treatment_category_id" required
                    class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                <option value="">— Select a category —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('treatment_category_id') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
            @error('treatment_category_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Name + Code --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Code <span class="text-gray-400 text-xs">(optional)</span></label>
                <input type="text" name="code" value="{{ old('code') }}" maxlength="30"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-gray-400 text-xs">(optional)</span></label>
            <textarea name="description" rows="3"
                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">{{ old('description') }}</textarea>
            @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Duration + Colour --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default duration (minutes) <span class="text-red-500">*</span></label>
                <input type="number" name="default_duration_minutes" value="{{ old('default_duration_minutes', 30) }}"
                       min="5" max="480" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('default_duration_minutes')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Colour tag</label>
                <input type="color" name="color" value="{{ old('color', '#6a0f70') }}"
                       class="h-9 w-16 border border-gray-300 rounded">
                @error('color')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Pricing --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Default price (₹) <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" name="default_price" value="{{ old('default_price', 0) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('default_price')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Min price (₹)</label>
                <input type="number" step="0.01" min="0" name="min_price" value="{{ old('min_price') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('min_price')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max price (₹)</label>
                <input type="number" step="0.01" min="0" name="max_price" value="{{ old('max_price') }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('max_price')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- GST + Sort order --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">GST % <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" max="100" name="gst_pct" value="{{ old('gst_pct', 0) }}" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('gst_pct')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Billing unit</label>
                <select name="unit_basis"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                    <option value="per_tooth"   @selected(old('unit_basis','per_tooth')==='per_tooth')>Per tooth (qty = teeth selected)</option>
                    <option value="whole_mouth" @selected(old('unit_basis')==='whole_mouth')>Whole mouth / per visit (qty = 1)</option>
                    <option value="per_arch"    @selected(old('unit_basis')==='per_arch')>Per arch</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">Drives auto-quantity on invoices from selected teeth.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                @error('sort_order')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        {{-- Active toggle --}}
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" id="is_active" @checked(old('is_active', true))
                   class="rounded border-gray-300 text-[#6a0f70] focus:ring-[#6a0f70]">
            <label for="is_active" class="text-sm text-gray-700">Active (available for use in treatment plans)</label>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
            <button type="submit"
                    class="bg-[#6a0f70] text-white text-sm font-medium px-5 py-2 rounded hover:bg-[#560c5b] transition-colors">
                Create Treatment
            </button>
            <a href="{{ route('treatments.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>
</div>
@endsection
