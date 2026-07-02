@extends('layouts.app')
@section('page-title', 'Promotional Campaigns')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('finance.wallet.index') }}" class="text-sm text-gray-500 hover:text-[#6a0f70]">← Wallets</a>
            <h1 class="text-xl font-bold text-gray-800 mt-1">Promotional Campaigns</h1>
            <p class="text-sm text-gray-500 mt-0.5">Bulk promotional credits with patient filter criteria.</p>
        </div>
        <a href="{{ route('finance.wallet-campaigns.create') }}"
           class="bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors font-medium">
            + New Campaign
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 mb-4">{{ session('success') }}</div>
    @endif

    {{-- Campaign list --}}
    @if($campaigns->isEmpty())
        <div class="bg-white border border-gray-200 rounded-lg px-6 py-16 text-center">
            <div class="w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </div>
            <p class="text-gray-500 font-medium">No campaigns yet</p>
            <p class="text-sm text-gray-400 mt-1 mb-4">Create a campaign to bulk-credit promotional money to filtered patients.</p>
            <a href="{{ route('finance.wallet-campaigns.create') }}"
               class="inline-block bg-[#6a0f70] text-white text-sm px-5 py-2 hover:bg-[#380740]">
                + New Campaign
            </a>
        </div>
    @else
        <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Campaign</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Filters</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Amount</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Expiry</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
                        <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Credited</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($campaigns as $campaign)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $campaign->name }}</div>
                                @if($campaign->description)
                                    <div class="text-xs text-gray-400 mt-0.5">{{ Str::limit($campaign->description, 60) }}</div>
                                @endif
                                <div class="text-xs text-gray-400 mt-0.5">Created {{ $campaign->created_at->format('d M Y') }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 max-w-xs">
                                {{ $campaign->filterSummary() }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="font-bold text-amber-700">Rs. {{ number_format($campaign->amount, 0) }}</span>
                                <div class="text-xs text-gray-400">per patient</div>
                            </td>
                            <td class="px-4 py-3 text-center text-xs text-gray-600">
                                {{ $campaign->expiry_date->format('d M Y') }}
                                @if($campaign->expiry_date->isPast())
                                    <div class="text-red-400">Expired</div>
                                @elseif($campaign->expiry_date->diffInDays(today()) <= 30)
                                    <div class="text-amber-500">Expiring soon</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($campaign->status === 'draft')
                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">Draft</span>
                                @elseif($campaign->status === 'applied')
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Applied</span>
                                @else
                                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full font-medium">Cancelled</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center text-xs">
                                @if($campaign->isApplied())
                                    <span class="font-semibold text-gray-700">{{ number_format($campaign->patients_credited) }}</span>
                                    <div class="text-gray-400">patients</div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right flex items-center justify-end gap-3">
                                <a href="{{ route('finance.wallet-campaigns.show', $campaign) }}"
                                   class="text-xs text-[#6a0f70] hover:underline font-medium">
                                    {{ $campaign->isDraft() ? 'Review & Apply →' : 'Details →' }}
                                </a>
                                @if($campaign->isDraft())
                                    <form method="POST"
                                          action="{{ route('finance.wallet-campaigns.destroy', $campaign) }}"
                                          onsubmit="return confirm('Delete campaign \'{{ addslashes($campaign->name) }}\'? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="text-xs text-red-400 hover:text-red-600 font-medium">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    @endif

</div>
@endsection
