@extends('layouts.app')
@section('page-title', 'Reconciliation — ' . $reconciliation->reconciliation_ref)

@section('content')
<div class="p-6 max-w-6xl mx-auto space-y-6" x-data="{ disputeOpen: false, approveOpen: false, submitOpen: false }">

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('lab.reconciliation.index') }}" class="hover:text-indigo-600">Reconciliation</a>
                &nbsp;/&nbsp; {{ $reconciliation->reconciliation_ref }}
            </p>
            <h1 class="text-2xl font-semibold text-indigo-700 mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                {{ $reconciliation->reconciliation_ref }}
            </h1>
        </div>

        <span class="px-3 py-1 rounded-full text-sm font-semibold {{ App\Models\LabMonthlyReconciliation::STATUS_COLORS[$reconciliation->status] ?? 'bg-gray-100 text-gray-600' }}">
            {{ App\Models\LabMonthlyReconciliation::STATUS_LABELS[$reconciliation->status] ?? $reconciliation->status }}
        </span>
    </div>

    {{-- FLASH --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ session('error') }}</div>
    @endif

    {{-- TOP ROW: Meta + Financials --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        {{-- Meta --}}
        <div class="md:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">Details</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-y-4 gap-x-6 text-sm">
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Lab Vendor</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->labVendor?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Finance Vendor</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->financeVendor?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Billing Period</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->getBillingPeriodLabel() }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Vendor Bill #</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->vendor_bill_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Vendor Bill Date</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->vendor_bill_date ? \Carbon\Carbon::parse($reconciliation->vendor_bill_date)->format('d M Y') : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Created By</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->createdBy?->name ?? '—' }}</p>
                </div>
                @if($reconciliation->approvedBy)
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Approved By</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->approvedBy->name }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Approved At</p>
                    <p class="font-medium text-gray-800">{{ $reconciliation->approved_at?->format('d M Y H:i') ?? '—' }}</p>
                </div>
                @endif
                @if($reconciliation->notes)
                <div class="col-span-3">
                    <p class="text-xs text-gray-400 mb-0.5">Notes</p>
                    <p class="text-gray-700">{{ $reconciliation->notes }}</p>
                </div>
                @endif
                @if($reconciliation->dispute_reason)
                <div class="col-span-3">
                    <p class="text-xs text-gray-400 mb-0.5">Dispute Reason</p>
                    <p class="text-red-700 font-medium">{{ $reconciliation->dispute_reason }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Financial summary --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-3">
            <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2">Financials</h2>
            @foreach([
                ['Our Total',    'Rs. '.number_format($reconciliation->our_total, 2),    'gray'],
                ['Vendor Total', 'Rs. '.number_format($reconciliation->vendor_total, 2), 'gray'],
                ['Difference',   'Rs. '.number_format($reconciliation->difference, 2),   abs($reconciliation->difference) < 1 ? 'green' : 'red'],
                ['Agreed Amount','Rs. '.number_format($reconciliation->agreed_amount, 2),'indigo'],
            ] as [$label, $val, $color])
            <div class="flex justify-between items-center py-2 border-b border-gray-50 last:border-0">
                <span class="text-sm text-gray-500">{{ $label }}</span>
                <span class="font-semibold text-{{ $color }}-700">{{ $val }}</span>
            </div>
            @endforeach

            {{-- Linked Finance Expense --}}
            @if($reconciliation->financeExpense)
            <div class="pt-2 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-1">Finance Expense</p>
                <a href="{{ route('finance.expenses') }}" class="text-sm text-indigo-600 hover:underline font-medium">
                    {{ $reconciliation->financeExpense->title }}
                </a>
                <span class="ml-2 text-xs px-2 py-0.5 rounded-full
                    {{ $reconciliation->financeExpense->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ ucfirst($reconciliation->financeExpense->payment_status) }}
                </span>
            </div>
            @endif

            {{-- Linked Voucher --}}
            @if($reconciliation->voucher)
            <div class="pt-2 border-t border-gray-100">
                <p class="text-xs text-gray-400 mb-1">Voucher</p>
                <a href="{{ route('finance.vouchers.show', $reconciliation->voucher) }}" class="text-sm text-indigo-600 hover:underline font-medium">
                    {{ $reconciliation->voucher->voucher_number }}
                </a>
            </div>
            @endif
        </div>
    </div>

    {{-- LINE ITEMS --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">Case Line Items ({{ $reconciliation->items->count() }})</h2>
            @if($reconciliation->hasConflicts())
            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-medium">
                {{ $reconciliation->items->where('match_status','conflict')->count() }} Conflicts
            </span>
            @endif
        </div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-widest">
                <tr>
                    <th class="px-4 py-3 text-left">Case #</th>
                    <th class="px-4 py-3 text-left">Patient</th>
                    <th class="px-4 py-3 text-left">Work</th>
                    <th class="px-4 py-3 text-right">Our (Rs. )</th>
                    <th class="px-4 py-3 text-right">Vendor (Rs. )</th>
                    <th class="px-4 py-3 text-right">Diff (Rs. )</th>
                    <th class="px-4 py-3 text-center">Match</th>
                    <th class="px-4 py-3 text-left">Remarks</th>
                    @if(in_array($reconciliation->status, ['draft','pending_review']))
                    <th class="px-4 py-3 text-center">Edit</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($reconciliation->items as $item)
                <tr class="hover:bg-gray-50 {{ $item->match_status === 'conflict' ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">
                        {{ $item->labCase?->case_number ?? $item->lab_case_id }}
                    </td>
                    <td class="px-4 py-3 text-gray-700 text-xs">{{ $item->labCase?->patient?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        {{ $item->labCase?->work_category ?? '—' }}
                        @if($item->labCase?->work_subtype) <span class="text-gray-400">/ {{ $item->labCase->work_subtype }}</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right font-mono">{{ number_format($item->our_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono">{{ number_format($item->vendor_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right font-mono {{ $item->difference != 0 ? 'text-red-600 font-semibold' : 'text-green-600' }}">
                        {{ number_format($item->difference, 2) }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @php
                        $mc = ['matched'=>'green','conflict'=>'red','disputed'=>'amber','accepted'=>'blue'];
                        @endphp
                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                            bg-{{ $mc[$item->match_status] ?? 'gray' }}-100
                            text-{{ $mc[$item->match_status] ?? 'gray' }}-700">
                            {{ ucfirst($item->match_status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $item->remarks ?? '—' }}</td>
                    @if(in_array($reconciliation->status, ['draft','pending_review']))
                    <td class="px-4 py-3 text-center">
                        <button type="button"
                                @click="$dispatch('open-item-edit', {{ json_encode(['id'=>$item->id,'our'=>$item->our_amount,'vendor'=>$item->vendor_amount,'match'=>$item->match_status,'remarks'=>$item->remarks]) }})"
                                class="text-xs text-indigo-600 hover:underline">Edit</button>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ACTIONS BAR --}}
    <div class="flex flex-wrap gap-3">
        @if($reconciliation->status === 'draft')
        <button @click="submitOpen = true"
                class="px-4 py-2 text-sm bg-indigo-600 text-white font-medium rounded-lg shadow hover:bg-indigo-700">
            Submit for Review
        </button>
        @endif

        @if($reconciliation->status === 'pending_review')
        <button @click="approveOpen = true"
                class="px-4 py-2 text-sm bg-green-600 text-white font-medium rounded-lg shadow hover:bg-green-700">
            Approve &amp; Create AP Entry
        </button>
        @endif

        @if(in_array($reconciliation->status, ['draft','pending_review']))
        <button @click="disputeOpen = true"
                class="px-4 py-2 text-sm bg-red-50 text-red-700 border border-red-200 font-medium rounded-lg hover:bg-red-100">
            Mark as Disputed
        </button>
        @endif

        @if(in_array($reconciliation->status, ['draft','pending_review']))
        <form method="POST" action="{{ route('lab.reconciliation.destroy', $reconciliation) }}"
              onsubmit="return confirm('Delete this reconciliation? Cases will return to unbilled.');">
            @csrf @method('DELETE')
            <button type="submit" class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50">
                Delete
            </button>
        </form>
        @endif
    </div>

    {{-- AUDIT EVENTS --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Audit History</h2>
        <div class="space-y-3">
            @forelse($reconciliation->events->sortByDesc('created_at') as $event)
            <div class="flex gap-3 text-sm">
                <div class="w-2 h-2 rounded-full bg-indigo-400 mt-1.5 shrink-0"></div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-gray-800 capitalize">{{ str_replace('_',' ',$event->event_type) }}</span>
                        @if($event->from_status && $event->to_status)
                        <span class="text-xs text-gray-400">{{ $event->from_status }} → {{ $event->to_status }}</span>
                        @endif
                        <span class="text-xs text-gray-400 ml-auto">{{ $event->created_at?->format('d M Y H:i') }}</span>
                        @if($event->createdBy)
                        <span class="text-xs text-gray-400">by {{ $event->createdBy->name }}</span>
                        @endif
                    </div>
                    @if($event->notes)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $event->notes }}</p>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400">No events recorded.</p>
            @endforelse
        </div>
    </div>
</div>

{{-- ── MODALS ─────────────────────────────────────────────────────────────── --}}

{{-- Submit Modal --}}
<div x-show="submitOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div @click.outside="submitOpen = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">Submit for Review</h3>
        <form method="POST" action="{{ route('lab.reconciliation.submit', $reconciliation) }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Agreed Amount (Rs. )</label>
                    <input type="number" step="0.01" name="agreed_amount"
                           value="{{ $reconciliation->agreed_amount }}"
                           class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" @click="submitOpen = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Submit</button>
            </div>
        </form>
    </div>
</div>

{{-- Approve Modal --}}
<div x-show="approveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div @click.outside="approveOpen = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">Approve Reconciliation</h3>
        <p class="text-sm text-gray-500">This will approve the reconciliation and automatically create an <strong>unpaid Finance Expense</strong> (AP entry) for the agreed amount.</p>
        <form method="POST" action="{{ route('lab.reconciliation.approve', $reconciliation) }}">
            @csrf
            <div class="space-y-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Final Agreed Amount (Rs. ) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" name="agreed_amount" required
                           value="{{ $reconciliation->agreed_amount }}"
                           class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" @click="approveOpen = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700">Approve</button>
            </div>
        </form>
    </div>
</div>

{{-- Dispute Modal --}}
<div x-show="disputeOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div @click.outside="disputeOpen = false" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-800">Mark as Disputed</h3>
        <p class="text-sm text-gray-500">Cases will return to <em>unbilled</em> and can be re-reconciled once the dispute is resolved.</p>
        <form method="POST" action="{{ route('lab.reconciliation.dispute', $reconciliation) }}">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Dispute Reason <span class="text-red-500">*</span></label>
                <textarea name="dispute_reason" rows="3" required
                          class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Describe the discrepancy or issue…"></textarea>
            </div>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" @click="disputeOpen = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit"
                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Mark Disputed</button>
            </div>
        </form>
    </div>
</div>

{{-- Line Item Edit Modal --}}
<div x-data="itemEditModal()" @open-item-edit.window="open($event.detail)" x-cloak>
    <div x-show="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div @click.outside="show = false" class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
            <h3 class="text-lg font-semibold text-gray-800">Edit Line Item</h3>
            <form :action="`{{ url('lab/reconciliation/'. '{{ $reconciliation->id }}' .'/items/') }}/${itemId}/update`" method="POST">
                @csrf
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Our Amount (Rs. )</label>
                        <input type="text" :value="ourAmt" disabled class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Vendor Amount (Rs. )</label>
                        <input type="number" step="0.01" name="vendor_amount" x-model="vendorAmt" required
                               class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Match Status</label>
                        <select name="match_status" x-model="matchStatus"
                                class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="matched">Matched</option>
                            <option value="conflict">Conflict</option>
                            <option value="disputed">Disputed</option>
                            <option value="accepted">Accepted</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Remarks</label>
                        <input type="text" name="remarks" x-model="remarks"
                               class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>
                <div class="flex justify-end gap-3 mt-4">
                    <button type="button" @click="show = false"
                            class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function itemEditModal() {
    return {
        show: false, itemId: null, ourAmt: 0, vendorAmt: 0, matchStatus: '', remarks: '',
        open(d) { this.itemId = d.id; this.ourAmt = d.our; this.vendorAmt = d.vendor; this.matchStatus = d.match; this.remarks = d.remarks || ''; this.show = true; }
    }
}
</script>
@endpush
@endsection
