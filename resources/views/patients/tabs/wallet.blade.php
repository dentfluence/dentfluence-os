
{{-- ══════════════════════════════════════════════════════════
     WALLET TAB — read-only (no Add Credit / no redirects)
     Credit management lives in Finance → Wallet Management
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'wallet'" style="display:none" class="w-full px-6 py-5">
@php
    $walletTxns = $wallet ? $wallet->transactions()->with('invoice')->orderByDesc('created_at')->limit(20)->get() : collect();
@endphp
    <div class="max-w-4xl mx-auto">

        {{-- Balance cards --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Promotional</div>
                <div class="text-2xl font-bold text-amber-600">
                    Rs. {{ number_format($wallet->balance_promotional ?? 0, 0) }}
                </div>
                <div class="text-xs text-gray-400 mt-1">Expires first · treatment-restricted</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Credit Balance</div>
                <div class="text-2xl font-bold text-purple-700">
                    Rs. {{ number_format($wallet->balance_permanent ?? 0, 0) }}
                </div>
                <div class="text-xs text-gray-400 mt-1">All treatments · optional expiry</div>
            </div>
            <div class="bg-[#6a0f70] rounded-lg p-4 text-center text-white">
                <div class="text-xs uppercase tracking-wide mb-1 opacity-80">Total Balance</div>
                <div class="text-2xl font-bold">
                    Rs. {{ number_format($wallet->balance_total ?? 0, 0) }}
                </div>
                <div class="text-xs mt-1 opacity-70">Available for invoices</div>
            </div>
        </div>

        {{-- Recent activity (read-only) --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700">Recent Wallet Activity</h3>
            </div>

            @if($walletTxns->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-gray-400">
                    No wallet transactions yet.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Date</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Type</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Notes</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Expiry</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-green-600">Credit</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-red-500">Debit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($walletTxns as $tx)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $tx->created_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($tx->credit_type === 'promotional')
                                    <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Promotional</span>
                                    @if($tx->campaign_name)
                                        <div class="text-xs text-amber-600 mt-0.5">{{ $tx->campaign_name }}</div>
                                    @endif
                                @elseif($tx->source === 'refund')
                                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded">Refund</span>
                                @elseif($tx->direction === 'credit')
                                    <span class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded">Credit</span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 bg-red-50 text-red-600 rounded">Used</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $tx->notes ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                @if($tx->expiry_date)
                                    {{ $tx->expiry_date->format('d M Y') }}
                                    @if($tx->expiry_date->isPast())
                                        <span class="text-red-400 block text-[10px]">Expired</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                @if($tx->direction === 'credit')
                                    <span class="font-semibold text-green-600">+Rs. {{ number_format($tx->amount, 0) }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                @if($tx->direction === 'debit')
                                    <span class="font-semibold text-red-500">−Rs. {{ number_format($tx->amount, 0) }}</span>
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

        <p class="text-xs text-gray-400 mt-3 text-center">
            To add credit or view the full ledger, go to <strong>Finance → Wallet Management</strong>.
        </p>
    </div>
</div>{{-- /x-show wallet --}}


{{-- ════════════════════════════════════
     WALLET TAB
════════════════════════════════════ --}}
<div x-show="activeTab === 'wallet'" style="display:none" class="w-full px-6 py-5">
    <div class="max-w-3xl mx-auto">

        {{-- Balance summary cards --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Promotional</div>
                <div class="text-2xl font-bold text-amber-600">Rs. {{ number_format($wallet->balance_promotional ?? 0, 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">Expires first · treatment-restricted</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Credit Balance</div>
                <div class="text-2xl font-bold text-purple-700">Rs. {{ number_format($wallet->balance_permanent ?? 0, 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">All treatments · optional expiry</div>
            </div>
            <div class="bg-[#6a0f70] rounded-lg p-4 text-center text-white">
                <div class="text-xs uppercase tracking-wide mb-1 opacity-80">Total Balance</div>
                <div class="text-2xl font-bold">Rs. {{ number_format($wallet->balance_total ?? 0, 0) }}</div>
                <div class="text-xs mt-1 opacity-70">Available for invoices</div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-3 mb-6">
            <a href="{{ route('finance.wallets.credit-form', $patient) }}"
               class="bg-[#6a0f70] text-white text-sm px-4 py-2 rounded hover:bg-[#380740] transition-colors font-medium">
                + Add Credit
            </a>
            <a href="{{ route('finance.wallets.show', $patient) }}"
               class="bg-white border border-gray-200 text-gray-700 text-sm px-4 py-2 rounded hover:bg-gray-50 transition-colors">
                View Full Ledger →
            </a>
        </div>

        {{-- Recent transactions --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Recent Wallet Activity</h3>
            </div>
            @php
                $recentWalletTx = isset($wallet) ? $wallet->transactions()->with('invoice')->limit(10)->get() : collect();
            @endphp
            @if($recentWalletTx->isEmpty())
                <div class="px-4 py-8 text-center text-gray-400 text-sm">No wallet transactions yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Date</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Type</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Details</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Expiry</th>
                            <th class="text-right px-4 py-2 text-xs font-medium text-gray-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentWalletTx as $tx)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $tx->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-2.5">
                                    @if($tx->credit_type === 'promotional')
                                        <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Promo</span>
                                        @if($tx->campaign_name)
                                            <div class="text-xs text-amber-600 mt-0.5">{{ $tx->campaign_name }}</div>
                                        @endif
                                    @elseif($tx->direction === 'debit')
                                        <span class="text-xs px-1.5 py-0.5 bg-red-50 text-red-600 rounded">Used</span>
                                    @elseif($tx->source === 'advance')
                                        <span class="text-xs px-1.5 py-0.5 bg-green-50 text-green-700 rounded">Advance Paid</span>
                                    @else
                                        <span class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded">Credit</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500">
                                    @if($tx->credit_type === 'promotional' && $tx->applicable_treatments !== null)
                                        {{ $tx->applicableTreatmentsLabel() }}
                                    @elseif($tx->notes)
                                        {{ $tx->notes }}
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500">
                                    @if($tx->expiry_date)
                                        {{ $tx->expiry_date->format('d M Y') }}
                                        @if($tx->isExpired())<span class="text-red-400 ml-1">Expired</span>@endif
                                    @else
                                        <span class="text-gray-300">No expiry</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="font-semibold text-sm {{ $tx->direction === 'credit' ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $tx->direction === 'credit' ? '+' : '−' }}Rs. {{ number_format($tx->amount, 0) }}
                                    </span>
                                    @if($tx->direction === 'credit' && $tx->credit_type === 'permanent')
                                        <a href="{{ route('finance.wallets.credit-note', [$patient, $tx]) }}"
                                           target="_blank"
                                           class="block text-xs text-[#6a0f70] hover:underline mt-0.5">{{ $tx->source === 'advance' ? 'Receipt' : 'Credit Note' }} ↗</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($recentWalletTx->count() >= 10)
                    <div class="px-4 py-2 border-t border-gray-100 text-center">
                        <a href="{{ route('finance.wallets.show', $patient) }}"
                           class="text-xs text-[#6a0f70] hover:underline">View all transactions →</a>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>{{-- /wallet tab --}}
