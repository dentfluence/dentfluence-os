{{-- resources/views/billing/from-plan.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Bill from Treatment Plan')

@section('content')
<div class="p-4 md:p-6 max-w-3xl mx-auto space-y-5">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('patients.show', $plan->patient_id) }}#treatment-plan"
           class="inline-flex items-center px-3.5 py-1.5 text-xs font-semibold text-gray-500 bg-white border border-gray-300 rounded-md no-underline hover:border-[#6a0f70] hover:text-[#6a0f70] transition">← Back</a>
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Bill from Plan</h2>
            <p class="text-xs text-gray-500">{{ $plan->patient->name ?? '' }} · {{ $plan->plan_name }}</p>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 rounded-lg">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if($billableItems->isEmpty())
        <div class="bg-white border border-gray-200 rounded-xl p-8 text-center">
            <p class="text-gray-500 text-sm">All teeth on this plan have already been invoiced. Nothing left to bill.</p>
            <a href="{{ route('patients.show', $plan->patient_id) }}#treatment-plan"
               class="inline-block mt-3 text-sm text-[#6a0f70] hover:underline">Back to treatment plan</a>
        </div>
    @else
    <form method="POST" action="{{ route('billing.storeFromPlan', $plan) }}" class="space-y-4">
        @csrf

        <p class="text-sm text-gray-500">Tick the teeth completed this visit. Only ticked teeth are invoiced now — the rest stay pending on the plan.</p>

        @foreach($billableItems as $item)
        @php
            $pendingTeeth  = $item->teeth->where('status', 'pending');
            $invoicedTeeth = $item->teeth->where('status', 'invoiced');
        @endphp
        <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-3">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">{{ $item->treatment_name }}</h3>
                    <p class="text-xs text-gray-400">Rs. {{ number_format($item->unit_price, 2) }} / unit
                        @if($item->gst_pct > 0) · GST {{ rtrim(rtrim(number_format($item->gst_pct, 2), '0'), '.') }}% @endif
                    </p>
                </div>
                @if($item->billing_progress === 'partially_completed')
                    <span class="text-[10px] font-semibold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">Partially billed</span>
                @endif
            </div>

            {{-- Pending teeth — selectable --}}
            <div class="flex flex-wrap gap-2">
                @foreach($pendingTeeth as $tooth)
                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 has-[:checked]:bg-[#6a0f70]/5 has-[:checked]:border-[#6a0f70]">
                    <input type="checkbox" name="tooth_ids[]" value="{{ $tooth->id }}" checked
                           data-price="{{ (float) $item->unit_price }}"
                           onchange="recalcPlanTotal()"
                           class="accent-[#6a0f70]">
                    <span class="font-medium text-gray-700">{{ $tooth->tooth_number ?? 'Unit' }}</span>
                </label>
                @endforeach
            </div>

            {{-- Already invoiced teeth — informational --}}
            @if($invoicedTeeth->isNotEmpty())
            <div class="flex flex-wrap gap-1.5 pt-1">
                @foreach($invoicedTeeth as $tooth)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] bg-green-50 text-green-600 border border-green-100 rounded">
                    {{ $tooth->tooth_number ?? 'Unit' }} · billed
                </span>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach

        {{-- Footer: live subtotal + submit --}}
        <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center justify-between sticky bottom-2">
            <div class="text-sm">
                <span class="text-gray-500">Selected subtotal (pre-tax):</span>
                <span class="font-bold text-gray-800 ml-1" id="planBillTotal">Rs. 0.00</span>
            </div>
            <button type="submit" class="px-5 py-2.5 bg-[#6a0f70] text-white font-medium text-sm rounded-lg hover:bg-[#380740]">
                Create Invoice
            </button>
        </div>
    </form>
    @endif
</div>

<script>
function recalcPlanTotal() {
    let total = 0;
    document.querySelectorAll('input[name="tooth_ids[]"]:checked').forEach(cb => {
        total += parseFloat(cb.dataset.price) || 0;
    });
    document.getElementById('planBillTotal').textContent = 'Rs. ' + total.toFixed(2);
}
document.addEventListener('DOMContentLoaded', recalcPlanTotal);
</script>
@endsection
