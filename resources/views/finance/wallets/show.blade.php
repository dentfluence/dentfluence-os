@extends('layouts.app')
@section('page-title', $patient->name . ' — Wallet Ledger')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
        <span>/</span>
        <a href="{{ route('finance.wallet.index') }}" class="hover:text-[#6a0f70]">Wallets</a>
        <span>/</span>
        <span class="text-gray-700 font-medium">{{ $patient->name }}</span>
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $patient->name }} — Wallet Ledger</h1>
            <p class="text-sm text-gray-500">{{ $patient->phone }}
                @if($patient->patient_id)
                    · <span class="font-mono">{{ $patient->patient_id }}</span>
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" onclick="document.getElementById('advanceModal').classList.remove('hidden')"
                    class="bg-green-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                + Receive Advance
            </button>
            <button type="button" onclick="document.getElementById('refundModal').classList.remove('hidden')"
                    class="bg-white text-blue-700 border border-blue-200 text-sm px-4 py-2 rounded-lg hover:bg-blue-50 transition-colors">
                Refund
            </button>
            <button type="button" onclick="document.getElementById('adjustModal').classList.remove('hidden')"
                    class="bg-white text-gray-600 border border-gray-200 text-sm px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                Adjust
            </button>
            <a href="{{ route('finance.wallets.credit-form', $patient) }}"
               class="bg-[#6a0f70] text-white text-sm px-4 py-2 rounded-lg hover:bg-[#380740] transition-colors">
                + Add Credit
            </a>
        </div>
    </div>

    {{-- Validation errors (from advance/refund/adjust) --}}
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2 mb-4 rounded-lg">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 mb-4">
            {{ session('success') }}
        </div>
    @endif

    {{-- Balance summary cards --}}
    <div class="grid grid-cols-3 gap-4 mb-5">
        <div class="bg-white border border-gray-200 p-4 text-center rounded-lg">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Promotional</div>
            <div class="text-2xl font-bold text-amber-700">Rs. {{ number_format($wallet->balance_promotional, 0) }}</div>
            <div class="text-xs text-gray-400 mt-1">Expires first</div>
        </div>
        <div class="bg-white border border-gray-200 p-4 text-center rounded-lg">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Permanent Credit</div>
            <div class="text-2xl font-bold text-blue-700">Rs. {{ number_format($wallet->balance_permanent, 0) }}</div>
            <div class="text-xs text-gray-400 mt-1">No expiry</div>
        </div>
        <div class="bg-[#6a0f70] p-4 text-center text-white rounded-lg">
            <div class="text-xs uppercase tracking-wide mb-1 opacity-80">Total Balance</div>
            <div class="text-2xl font-bold">Rs. {{ number_format($wallet->balance_total, 0) }}</div>
            <div class="text-xs mt-1 opacity-70">Available for invoices</div>
        </div>
    </div>

    {{-- Ledger summary bar --}}
    <div class="grid grid-cols-4 gap-3 mb-5">
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 mb-1">Opening Balance</div>
            <div class="text-base font-semibold text-gray-700">Rs. 0.00</div>
        </div>
        <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 mb-1">Total Credits</div>
            <div class="text-base font-semibold text-green-600">Rs. {{ number_format($totalCredits, 0) }}</div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 mb-1">Total Utilized</div>
            <div class="text-base font-semibold text-red-500">Rs. {{ number_format($totalUtilized, 0) }}</div>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
            <div class="text-xs text-gray-500 mb-1">Refunds</div>
            <div class="text-base font-semibold text-blue-600">Rs. {{ number_format($totalRefunds, 0) }}</div>
        </div>
    </div>

    {{-- Transaction ledger --}}
    <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Transaction History</h2>
            <span class="text-xs text-gray-400">{{ $withBalance->count() }} transactions</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Date</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Type</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Source / Invoice</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Notes</th>
                        <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Expiry</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-green-600">Credit</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-red-500">Debit</th>
                        <th class="text-right px-4 py-2.5 text-xs font-semibold text-[#6a0f70]">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    {{-- Opening balance row --}}
                    <tr class="bg-gray-50">
                        <td class="px-4 py-2.5 text-xs text-gray-400 italic" colspan="7">Opening Balance</td>
                        <td class="px-4 py-2.5 text-right text-xs font-semibold text-gray-500">Rs. 0.00</td>
                    </tr>

                    @forelse($withBalance as $tx)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500 text-xs align-top whitespace-nowrap">
                                {{ $tx->created_at->format('d M Y') }}
                                <div class="text-gray-400">{{ $tx->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                @if($tx->credit_type === 'promotional')
                                    <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Promotional</span>
                                    @if($tx->campaign_name)
                                        <div class="text-xs text-amber-600 mt-0.5 font-medium">{{ $tx->campaign_name }}</div>
                                    @endif
                                    @if($tx->applicable_treatments !== null)
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $tx->applicableTreatmentsLabel() }}</div>
                                    @else
                                        <div class="text-xs text-gray-400 mt-0.5">All treatments</div>
                                    @endif
                                @elseif($tx->source === 'refund')
                                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded">Refund</span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded">Credit</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 text-xs align-top">
                                <span class="capitalize">{{ ucwords(str_replace('_', ' ', $tx->source ?? '')) }}</span>
                                @php
                                    $invNo = $tx->invoice_number ?? $tx->invoice?->invoice_number;
                                @endphp
                                @if($invNo)
                                    <a href="{{ route('billing.show', $tx->invoice_id) }}"
                                       class="text-[#6a0f70] hover:underline ml-1 font-medium block">
                                        {{ $invNo }}
                                    </a>
                                @elseif($tx->invoice_id)
                                    <a href="{{ route('billing.show', $tx->invoice_id) }}"
                                       class="text-[#6a0f70] hover:underline ml-1">#{{ $tx->invoice_id }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs align-top">{{ $tx->notes ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 align-top whitespace-nowrap">
                                @if($tx->expiry_date)
                                    {{ $tx->expiry_date->format('d M Y') }}
                                    @if($tx->isExpired())
                                        <span class="text-red-400 block">Expired</span>
                                    @elseif($tx->expiry_date->diffInDays(today()) <= 30)
                                        <span class="text-amber-500 block">Expiring soon</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">No expiry</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                @if($tx->direction === 'credit')
                                    <div class="font-semibold text-green-600">+Rs. {{ number_format($tx->amount, 0) }}</div>
                                    @if($tx->source === 'admin_credit' && $tx->credit_type === 'permanent')
                                        <a href="{{ route('finance.wallets.credit-note', [$patient, $tx]) }}"
                                           target="_blank"
                                           class="text-xs text-[#6a0f70] hover:underline mt-0.5 inline-block">Credit Note ↗</a>
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                @if($tx->direction === 'debit')
                                    <div class="font-semibold text-red-500">−Rs. {{ number_format($tx->amount, 0) }}</div>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right align-top font-semibold text-[#6a0f70]">
                                Rs. {{ number_format($tx->running_balance, 0) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-400 text-sm">
                                No transactions yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($withBalance->count())
                <tfoot>
                    <tr class="bg-gray-50 border-t border-gray-200">
                        <td colspan="5" class="px-4 py-2.5 text-xs font-semibold text-gray-600">Closing Balance</td>
                        <td class="px-4 py-2.5 text-right text-xs font-bold text-green-600">
                            Rs. {{ number_format($totalCredits, 0) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-xs font-bold text-red-500">
                            Rs. {{ number_format($totalDebits, 0) }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-xs font-bold text-[#6a0f70]">
                            Rs. {{ number_format($wallet->balance_total, 0) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Receive Advance Modal ─────────────────────────────────────────── --}}
    <div id="advanceModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-green-700">Receive Advance</h3>
                <button onclick="document.getElementById('advanceModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <p class="text-xs text-gray-500">Money paid now with no invoice. Added to wallet, available for future invoices.</p>
            <form method="POST" action="{{ route('finance.wallets.receive-advance', $patient) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rs.)</label>
                        <input type="number" name="amount" step="0.01" min="1" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mode</label>
                        <select name="payment_mode" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Credit Card</option>
                            <option value="debit_card">Debit Card</option>
                            <option value="netbanking">Net Banking</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <input type="text" name="notes" placeholder="Optional" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-300">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" class="flex-1 py-2.5 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700">Add to Wallet</button>
                    <button type="button" onclick="document.getElementById('advanceModal').classList.add('hidden')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Refund Modal ──────────────────────────────────────────────────── --}}
    <div id="refundModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-blue-700">Refund from Wallet</h3>
                <button onclick="document.getElementById('refundModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <p class="text-xs text-gray-500">Return money to the patient. Available: <strong>Rs. {{ number_format($wallet->balance_permanent, 2) }}</strong> (permanent credit only).</p>
            <form method="POST" action="{{ route('finance.wallets.refund', $patient) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rs.)</label>
                        <input type="number" name="amount" step="0.01" min="1" max="{{ $wallet->balance_permanent }}" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Mode</label>
                        <select name="payment_mode" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input type="date" name="refund_date" value="{{ now()->toDateString() }}" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reason <span class="text-red-500">*</span></label>
                    <input type="text" name="reason" required minlength="3" placeholder="e.g. Treatment cancelled" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" class="flex-1 py-2.5 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700">Refund</button>
                    <button type="button" onclick="document.getElementById('refundModal').classList.add('hidden')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Adjust Modal ──────────────────────────────────────────────────── --}}
    <div id="adjustModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-700">Wallet Adjustment</h3>
                <button onclick="document.getElementById('adjustModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <p class="text-xs text-gray-500">Manual correction. Logged with reason. Debit is capped at available balance.</p>
            <form method="POST" action="{{ route('finance.wallets.adjust', $patient) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Direction</label>
                        <select name="direction" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300">
                            <option value="credit">Credit (add)</option>
                            <option value="debit">Debit (deduct)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rs.)</label>
                        <input type="number" name="amount" step="0.01" min="1" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reason <span class="text-red-500">*</span></label>
                    <input type="text" name="reason" required minlength="3" placeholder="e.g. Correcting duplicate credit" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-gray-300">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" class="flex-1 py-2.5 bg-gray-800 text-white font-medium text-sm rounded-lg hover:bg-gray-900">Apply Adjustment</button>
                    <button type="button" onclick="document.getElementById('adjustModal').classList.add('hidden')" class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Cancel</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
