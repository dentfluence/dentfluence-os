@extends('layouts.app')
@section('page-title', $plan ? 'Edit Membership Tier' : 'New Membership Tier')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('finance.dashboard') }}"
           class="text-gray-400 hover:text-[#6a0f70] text-sm">← Finance</a>
        <span class="text-gray-300">/</span>
        <a href="{{ route('finance.membership.index') }}"
           class="text-gray-400 hover:text-gray-600 text-sm">← Membership Tiers</a>
        <span class="text-gray-300">/</span>
        <h1 class="text-xl font-bold text-gray-800">
            {{ $plan ? 'Edit: ' . $plan->plan_name : 'New Membership Tier' }}
        </h1>
    </div>

    <form method="POST"
          action="{{ $plan ? route('finance.membership.update', $plan) : route('finance.membership.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-6 space-y-6">
        @csrf
        @if($plan) @method('PUT') @endif

        {{-- Basic info --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Plan Name <span class="text-red-500">*</span>
                </label>
                <input type="text" name="plan_name"
                       value="{{ old('plan_name', $plan?->plan_name) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                       placeholder="e.g. AOCP Basic, AOCP Premium"
                       required>
                @error('plan_name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="2"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                          placeholder="Brief description shown to front desk">{{ old('description', $plan?->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Price (Rs. ) <span class="text-red-500">*</span>
                </label>
                <input type="number" name="price" step="0.01" min="0"
                       value="{{ old('price', $plan?->price) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                       required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Duration <span class="text-red-500">*</span>
                </label>
                <select name="duration"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                        required>
                    @foreach(['monthly' => '1 Month', 'quarterly' => '3 Months', 'half_yearly' => '6 Months', 'yearly' => '1 Year'] as $val => $label)
                        <option value="{{ $val }}"
                            {{ old('duration', $plan?->duration) === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Benefits --}}
        <div class="border-t border-gray-100 pt-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Benefits</h3>

            @php $b = $plan?->getBenefitList() ?? []; @endphp

            <div class="space-y-3">

                {{-- Checkboxes --}}
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="free_consultation" value="1"
                               class="rounded border-gray-300 text-indigo-600"
                               {{ old('free_consultation', $b['free_consultation'] ?? false) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Free consultation</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="free_xray" value="1"
                               class="rounded border-gray-300 text-indigo-600"
                               {{ old('free_xray', $b['free_xray'] ?? false) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Free X-ray</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="free_scaling" value="1"
                               class="rounded border-gray-300 text-indigo-600"
                               {{ old('free_scaling', $b['free_scaling'] ?? false) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">Free single scaling</span>
                    </label>
                </div>

                {{-- % Discount --}}
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-700 w-48">Treatment discount (%)</label>
                    <input type="number" name="benefit_discount_percent" min="0" max="100" step="0.5"
                           value="{{ old('benefit_discount_percent', $b['discount_percent'] ?? 0) }}"
                           class="w-24 border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
                    <span class="text-xs text-gray-400">Applied to all treatments on invoice</span>
                </div>

                {{-- Free treatments --}}
                <div>
                    <label class="block text-sm text-gray-700 mb-1">
                        Additional free treatments
                        <span class="text-xs text-gray-400 ml-1">(comma-separated treatment names)</span>
                    </label>
                    <input type="text" name="free_treatments_text"
                           value="{{ old('free_treatments_text', implode(', ', $b['free_treatments'] ?? [])) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                           placeholder="e.g. Fluoride, Pit & Fissure Sealant">
                    <p class="text-xs text-gray-400 mt-1">
                        Names matched against invoice line items — partial match (case-insensitive).
                    </p>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Internal notes</label>
                    <input type="text" name="benefit_notes"
                           value="{{ old('benefit_notes', $b['notes'] ?? '') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                           placeholder="Shown to front desk when benefit applies">
                </div>
            </div>
        </div>

        {{-- ── Family Options ─────────────────────────────────────────────── --}}
        @php
            $familyOption    = old('family_option', $plan?->family_option ?? 'none');
            $addonPrice      = old('addon_price',    $plan?->addon_price   ?? '');
            $maxFamilyMembers = old('max_family_members', $plan?->max_family_members ?? 4);
        @endphp

        <div class="border-t border-gray-100 pt-5"
             x-data="{
                familyOption: '{{ $familyOption }}',
                get isFamilyOn()  { return this.familyOption !== 'none'; },
                get isAddon()     { return this.familyOption === 'addon'; },
                get isBundle()    { return this.familyOption === 'bundle'; },
             }">

            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-700">Family Membership Options</h3>
                <span class="text-xs text-gray-400">Leave as "None" if this plan is individual-only</span>
            </div>

            {{-- Family option selector --}}
            <div class="flex flex-wrap gap-3">

                {{-- None --}}
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition"
                       :class="familyOption === 'none'
                           ? 'border-indigo-400 bg-indigo-50 text-indigo-700'
                           : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                    <input type="radio" name="family_option" value="none"
                           x-model="familyOption"
                           class="accent-indigo-600">
                    <div>
                        <p class="text-sm font-medium">None</p>
                        <p class="text-xs text-gray-400">Individual only</p>
                    </div>
                </label>

                {{-- Add-on --}}
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition"
                       :class="familyOption === 'addon'
                           ? 'border-indigo-400 bg-indigo-50 text-indigo-700'
                           : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                    <input type="radio" name="family_option" value="addon"
                           x-model="familyOption"
                           class="accent-indigo-600">
                    <div>
                        <p class="text-sm font-medium">Add-on model</p>
                        <p class="text-xs text-gray-400">Head pays full price, each member pays add-on price</p>
                    </div>
                </label>

                {{-- Bundle --}}
                <label class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition"
                       :class="familyOption === 'bundle'
                           ? 'border-indigo-400 bg-indigo-50 text-indigo-700'
                           : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                    <input type="radio" name="family_option" value="bundle"
                           x-model="familyOption"
                           class="accent-indigo-600">
                    <div>
                        <p class="text-sm font-medium">Bundle model</p>
                        <p class="text-xs text-gray-400">One flat price covers the whole family</p>
                    </div>
                </label>

            </div>

            {{-- Conditional fields — shown when family option is on --}}
            <div x-show="isFamilyOn" x-cloak class="mt-4 grid grid-cols-2 gap-4">

                {{-- Add-on price — only for addon model --}}
                <div x-show="isAddon">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Add-on price (Rs. ) per member
                        <span class="text-red-500" x-show="isAddon">*</span>
                    </label>
                    <input type="number" name="addon_price" step="0.01" min="0"
                           value="{{ $addonPrice }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none"
                           placeholder="e.g. 1999">
                    <p class="text-xs text-gray-400 mt-1">
                        Charged per add-on member. Head member pays the main plan price.
                    </p>
                    @error('addon_price') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Bundle price note — bundle uses main plan price --}}
                <div x-show="isBundle"
                     class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700 flex items-start gap-2">
                    <span class="mt-0.5"></span>
                    <span>Bundle uses the <strong>Plan Price (Rs. )</strong> above as the single flat price for the whole family. No separate add-on price needed.</span>
                </div>

                {{-- Max family members --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Max family members
                        <span class="text-xs text-gray-400 ml-1">
                            (<span x-text="isBundle ? 'total incl. head' : 'add-ons only, excl. head'"></span>)
                        </span>
                    </label>
                    <input type="number" name="max_family_members" min="1" max="20"
                           value="{{ $maxFamilyMembers }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-400 focus:outline-none">
                    @error('max_family_members') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

            </div>

        </div>
        {{-- ── /Family Options ─────────────────────────────────────────────── --}}

        {{-- Active toggle --}}
        <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       class="rounded border-gray-300 text-indigo-600"
                       {{ old('is_active', $plan?->is_active ?? true) ? 'checked' : '' }}>
                <span class="text-sm font-medium text-gray-700">Active (visible for enrollment)</span>
            </label>
        </div>

        {{-- Actions --}}
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('finance.membership.index') }}"
               class="px-4 py-2 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </a>
            <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                {{ $plan ? 'Update Tier' : 'Create Tier' }}
            </button>
        </div>

    </form>
</div>
@endsection
