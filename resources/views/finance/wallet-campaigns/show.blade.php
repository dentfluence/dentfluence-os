@extends('layouts.app')
@section('page-title', $walletCampaign->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <a href="{{ route('finance.wallet-campaigns.index') }}" class="text-sm text-gray-500 hover:text-[#6a0f70]">← Campaigns</a>
            <h1 class="text-xl font-bold text-gray-800 mt-1">{{ $walletCampaign->name }}</h1>
            @if($walletCampaign->description)
                <p class="text-sm text-gray-500 mt-0.5">{{ $walletCampaign->description }}</p>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if($walletCampaign->isDraft())
                {{-- Apply --}}
                <form method="POST" action="{{ route('finance.wallet-campaigns.apply', $walletCampaign) }}"
                      onsubmit="return confirm('Apply this campaign? Rs. {{ number_format($walletCampaign->amount,0) }} will be credited to {{ number_format($matchCount) }} patients immediately.')">
                    @csrf
                    <button type="submit"
                            class="bg-green-600 text-white text-sm px-5 py-2 hover:bg-green-700 transition-colors font-semibold">
                        ✓ Apply to {{ number_format($matchCount) }} Patients
                    </button>
                </form>
                {{-- Cancel --}}
                <form method="POST" action="{{ route('finance.wallet-campaigns.cancel', $walletCampaign) }}"
                      onsubmit="return confirm('Cancel this campaign?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="border border-gray-300 text-gray-600 text-sm px-3 py-2 hover:bg-gray-50">
                        Cancel
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 mb-4">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-3 gap-5">

        {{-- ── Campaign details ── --}}
        <div class="col-span-2 space-y-5">

            {{-- Status banner --}}
            @if($walletCampaign->isApplied())
                <div class="bg-green-50 border border-green-200 rounded-lg px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-green-800 text-sm">Campaign Applied</p>
                            <p class="text-xs text-green-600 mt-0.5">
                                Rs. {{ number_format($walletCampaign->amount, 0) }} credited to
                                {{ number_format($walletCampaign->patients_credited) }} patients
                                on {{ $walletCampaign->applied_at->format('d M Y, h:i A') }}.
                                Total issued: <strong>Rs. {{ number_format($walletCampaign->total_amount_issued, 0) }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            @elseif($walletCampaign->status === 'cancelled')
                <div class="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm text-gray-500">
                    This campaign was cancelled.
                </div>
            @else
                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                    <p class="text-sm font-semibold text-blue-800">Draft — Ready to Apply</p>
                    <p class="text-xs text-blue-600 mt-0.5">
                        Review the patient list below. Click "Apply" to credit Rs. {{ number_format($walletCampaign->amount, 0) }}
                        to {{ number_format($matchCount) }} matching patients.
                    </p>
                </div>
            @endif

            {{-- Campaign settings summary --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-4">Campaign Settings</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-xs text-gray-400 mb-0.5">Amount per Patient</div>
                        <div class="font-bold text-2xl text-amber-700">Rs. {{ number_format($walletCampaign->amount, 0) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-0.5">Valid Until</div>
                        <div class="font-semibold text-gray-800">{{ $walletCampaign->expiry_date->format('d M Y') }}</div>
                        @if($walletCampaign->expiry_date->isPast())
                            <span class="text-xs text-red-500">Expired</span>
                        @elseif($walletCampaign->expiry_date->diffInDays(today()) <= 30)
                            <span class="text-xs text-amber-500">Expiring soon</span>
                        @endif
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-0.5">Treatment Restriction</div>
                        <div class="text-gray-700">
                            @if($walletCampaign->applicable_treatments === null)
                                <span class="text-green-600 font-medium">All treatments</span>
                            @else
                                <span class="text-amber-700 font-medium">Restricted</span>
                                <div class="text-xs text-gray-500 mt-0.5">{{ implode(', ', $treatments) ?: 'None specified' }}</div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-400 mb-0.5">Patient Filters</div>
                        <div class="text-gray-700 text-xs">{{ $walletCampaign->filterSummary() }}</div>
                    </div>
                </div>
                @if($walletCampaign->notes)
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="text-xs text-gray-400 mb-0.5">Notes</div>
                        <div class="text-sm text-gray-600">{{ $walletCampaign->notes }}</div>
                    </div>
                @endif
            </div>

            {{-- Matching patients --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700">
                        {{ $walletCampaign->isApplied() ? 'Patients Credited' : 'Matching Patients' }}
                        <span class="ml-2 text-xs font-normal text-gray-400">
                            {{ number_format($matchCount) }} total
                            @if($matchCount > 50)(showing first 50)@endif
                        </span>
                    </h2>
                </div>

                @if($previewList->isEmpty())
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">
                        No patients match the current filters.
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-100">
                            <tr>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Patient</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Phone</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Gender</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Area</th>
                                <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Membership</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($previewList as $p)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('patients.show', $p) }}"
                                           class="font-medium text-gray-800 hover:text-[#6a0f70]">
                                            {{ $p->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $p->phone }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 text-xs capitalize">{{ $p->gender ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $p->area ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-xs">
                                        @if($p->membership_status === 'active')
                                            <span class="text-green-600 font-medium">Active</span>
                                        @elseif($p->membership_status === 'expired')
                                            <span class="text-red-400">Expired</span>
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>

        {{-- ── Right sidebar ── --}}
        <div class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide">Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Matching patients</span>
                        <span class="font-bold text-[#6a0f70]">{{ number_format($matchCount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Per patient</span>
                        <span class="font-semibold text-amber-700">Rs. {{ number_format($walletCampaign->amount, 0) }}</span>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 mt-2">
                        <span class="text-gray-700 font-semibold">Total to Issue</span>
                        <span class="font-bold text-gray-800">Rs. {{ number_format($matchCount * $walletCampaign->amount, 0) }}</span>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3">Status</h3>
                @if($walletCampaign->status === 'draft')
                    <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-medium">Draft</span>
                    <p class="text-xs text-gray-400 mt-2">Created {{ $walletCampaign->created_at->format('d M Y') }}</p>
                @elseif($walletCampaign->isApplied())
                    <span class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-full font-medium">Applied</span>
                    <p class="text-xs text-gray-400 mt-2">Applied {{ $walletCampaign->applied_at->format('d M Y') }}</p>
                    <p class="text-xs text-gray-400">{{ number_format($walletCampaign->patients_credited) }} patients credited</p>
                @else
                    <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full font-medium">Cancelled</span>
                @endif
            </div>

            @if($walletCampaign->isDraft())
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-xs text-amber-800 space-y-1.5">
                    <p class="font-semibold">Before applying:</p>
                    <ul class="list-disc list-inside space-y-1 text-amber-700">
                        <li>Credits are issued immediately to all matching patients.</li>
                        <li>Cannot be reversed in bulk — only individually from each patient's wallet.</li>
                        <li>Patients added after this date won't receive it automatically.</li>
                    </ul>
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
