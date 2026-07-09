@extends('layouts.app')
@section('page-title', 'Expenses — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- PAGE HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp; Expenses
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Expense Management
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.expenses.export', array_merge(request()->only(['from','to','category_id']), ['format'=>'pdf'])) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-600 text-sm px-3 py-2 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                PDF
            </a>
            <a href="{{ route('finance.expenses.export', array_merge(request()->only(['from','to','category_id']), ['format'=>'excel'])) }}"
               class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-600 text-sm px-3 py-2 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                Excel
            </a>
            <a href="{{ route('finance.expenses.create') }}"
               class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                + Record Expense
            </a>
        </div>
    </div>

    @session('success')
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ $value }}</div>
    @endsession

    {{-- SUMMARY STRIP --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Records</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $summary->cnt ?? 0 }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Subtotal</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">&#8377;{{ number_format($summary->subtotal ?? 0, 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">GST Paid</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">&#8377;{{ number_format($summary->gst ?? 0, 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Outflow</p>
            <p class="text-2xl font-bold text-red-600 mt-1">&#8377;{{ number_format($summary->total ?? 0, 0) }}</p>
        </div>
        <a href="{{ route('finance.expenses', ['tab' => 'unpaid']) }}"
           class="bg-orange-50 border border-orange-200 p-4 hover:bg-orange-100 transition-colors">
            <p class="text-xs text-orange-500 uppercase tracking-widest">Pending Bills</p>
            <p class="text-2xl font-bold text-orange-600 mt-1">&#8377;{{ number_format($unpaidAmount, 0) }}</p>
            <p class="text-xs text-orange-400 mt-0.5">
                {{ $unpaidCount }} bill{{ $unpaidCount != 1 ? 's' : '' }}
                @if($overdueCount > 0)
                    &middot; <span class="text-red-500">{{ $overdueCount }} overdue</span>
                @endif
            </p>
        </a>
    </div>

    {{-- TABS --}}
    <div class="flex border-b border-gray-200">
        @php
            $tabDefs = [
                'all'       => ['label' => 'All Expenses',     'count' => null],
                'unpaid'    => ['label' => 'Unpaid / Pending', 'count' => $unpaidCount],
                'recurring' => ['label' => 'Recurring',        'count' => $recurringCount],
                'vouchers'  => ['label' => 'Voucher Register', 'count' => null],
            ];
        @endphp
        @foreach($tabDefs as $key => $info)
        <a href="{{ route('finance.expenses', array_merge(request()->except('tab','page'), ['tab' => $key])) }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                  {{ $tab === $key ? 'border-[#6a0f70] text-[#6a0f70]' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
            {{ $info['label'] }}
            @if($info['count'] !== null && $info['count'] > 0)
                <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full {{ $tab === $key ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 text-gray-600' }}">
                    {{ $info['count'] }}
                </span>
            @endif
        </a>
        @endforeach
    </div>

    @if($tab === 'vouchers')
    {{-- ================================================================ --}}
    {{-- VOUCHER REGISTER TAB                                             --}}
    {{-- ================================================================ --}}

    <form method="GET" action="{{ route('finance.expenses') }}" class="bg-white border border-[#e8d5f0] p-4">
        <input type="hidden" name="tab" value="vouchers">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Vendor</label>
                <select name="vendor_id" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $vnd)
                        <option value="{{ $vnd->id }}" {{ request('vendor_id') == $vnd->id ? 'selected' : '' }}>{{ $vnd->vendor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Mode</label>
                <select name="vmode" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Modes</option>
                    <option value="cash" {{ request('vmode')==='cash' ? 'selected' : '' }}>Cash</option>
                    <option value="upi" {{ request('vmode')==='upi' ? 'selected' : '' }}>UPI</option>
                    <option value="card" {{ request('vmode')==='card' ? 'selected' : '' }}>Card</option>
                    <option value="bank_transfer" {{ request('vmode')==='bank_transfer' ? 'selected' : '' }}>Bank Transfer</option>
                    <option value="cheque" {{ request('vmode')==='cheque' ? 'selected' : '' }}>Cheque</option>
                    <option value="other" {{ request('vmode')==='other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div class="flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Voucher no, vendor, purpose..." class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <label class="flex items-center gap-2 text-xs text-gray-500 pb-1.5">
                <input type="checkbox" name="show_voided" value="1" {{ $showVoided ? 'checked' : '' }}
                       onchange="this.form.submit()">
                Show voided
            </label>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">Filter</button>
            <a href="{{ route('finance.expenses', ['tab' => 'vouchers']) }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
        </div>
    </form>

    <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">
            {{ $vouchers?->total() ?? 0 }} voucher(s) &nbsp;&middot;&nbsp;
            Total: <span class="font-semibold text-[#6a0f70]">&#8377;{{ number_format($voucherTotal, 2) }}</span>
        </p>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.vouchers.export', array_merge(request()->only(['from','to','vendor_id','search']), ['payment_mode' => request('vmode')])) }}"
               class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-600 text-sm px-3 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                Export Excel
            </a>
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 border border-gray-300 text-gray-600 text-sm px-3 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                Print
            </button>
        </div>
    </div>

    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        @if($vouchers && $vouchers->isEmpty())
        <div class="py-10 text-center text-gray-400 text-sm">No vouchers found for the selected filters.</div>
        @elseif($vouchers)
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Voucher No</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor / Payee</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Purpose</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Mode</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Account</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($vouchers as $vchr)
                <tr class="hover:bg-[#fdf8ff] {{ $vchr->isVoided() ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3 font-mono text-xs text-[#6a0f70] font-semibold whitespace-nowrap {{ $vchr->isVoided() ? 'line-through' : '' }}">
                        {{ $vchr->voucher_number }}
                        @if($vchr->isVoided())
                        <span class="ml-1 text-xs px-1.5 py-0.5 bg-red-50 text-red-600 border border-red-200 font-normal">VOIDED</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $vchr->voucher_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $vchr->vendor_name ?? ($vchr->vendor?->vendor_name ?? '&mdash;') }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ Str::limit($vchr->purpose ?? '', 45) }}</td>
                    <td class="px-4 py-3 text-gray-600 capitalize">{{ str_replace('_', ' ', $vchr->payment_mode ?? '') }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $vchr->clinic_account_name ?? '&mdash;' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">&#8377;{{ number_format($vchr->amount, 2) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('finance.vouchers.show', $vchr) }}" class="text-xs text-[#6a0f70] hover:underline mr-2">View</a>
                        <a href="{{ route('finance.vouchers.print', $vchr) }}" target="_blank" class="text-xs border border-gray-300 text-gray-600 px-2 py-0.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors mr-1">Print</a>
                        <a href="{{ route('finance.vouchers.print', $vchr) }}?pdf=1" target="_blank" class="text-xs border border-gray-300 text-gray-600 px-2 py-0.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">PDF</a>
                        @if(!$vchr->isVoided() && auth()->user()?->isAdmin())
                        <form method="POST" action="{{ route('finance.vouchers.destroy', $vchr) }}" class="inline" onsubmit="return promptVoidReason(this)">
                            @csrf @method('DELETE')
                            <input type="hidden" name="void_reason">
                            <button type="submit" class="text-xs text-red-500 hover:underline ml-1">Void</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 border-t border-gray-200">
                <tr>
                    <td colspan="6" class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase">Total ({{ $vouchers->total() }} vouchers)</td>
                    <td class="px-4 py-2.5 text-right font-bold text-gray-900">&#8377;{{ number_format($voucherTotal, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @if($vouchers->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $vouchers->links() }}</div>
        @endif
        @endif
    </div>

    @else
    {{-- ================================================================ --}}
    {{-- EXPENSE TABS (all / unpaid / recurring)                         --}}
    {{-- ================================================================ --}}

    <form method="GET" action="{{ route('finance.expenses') }}" class="bg-white border border-[#e8d5f0] p-4">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="flex flex-wrap gap-3 items-end">
            @if($tab !== 'unpaid')
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Category</label>
                <select name="category_id" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Expense title..." class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">Filter</button>
            <a href="{{ route('finance.expenses', ['tab' => $tab]) }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
        </div>
    </form>

    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        @if($expenses->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">
            @if($tab === 'unpaid')
                No pending bills. All clear!
            @elseif($tab === 'recurring')
                No recurring expenses set up yet.
                <a href="{{ route('finance.expenses.create') }}" class="text-[#6a0f70] hover:underline ml-1">Add one &rarr;</a>
            @else
                No expenses recorded for the selected period.
                <a href="{{ route('finance.expenses.create') }}" class="text-[#6a0f70] hover:underline ml-1">Record one &rarr;</a>
            @endif
        </div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Title</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">GST</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($expenses as $expense)
                @php $badge = $expense->getPaymentStatusBadge(); @endphp
                <tr class="hover:bg-[#fdf8ff] {{ $expense->isOverdue() ? 'bg-red-50' : '' }}">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                        {{ $expense->expense_date?->format('d M Y') }}
                        @if($expense->payment_status === 'unpaid' && $expense->due_date)
                            <div class="text-xs {{ $expense->isOverdue() ? 'text-red-500 font-semibold' : 'text-gray-400' }}">
                                Due: {{ $expense->due_date->format('d M') }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-800">{{ $expense->title }}</div>
                        @if($expense->description)
                            <div class="text-xs text-gray-400">{{ Str::limit($expense->description, 55) }}</div>
                        @endif
                        @if($expense->is_recurring)
                            <span class="text-xs text-purple-600">Recurring &mdash; {{ ucfirst($expense->recurring_period) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-sm">{{ $expense->category?->name ?? '&mdash;' }}</td>
                    <td class="px-4 py-3 text-gray-600 text-sm">{{ $expense->vendor?->vendor_name ?? '&mdash;' }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        @if($expense->payment_status === 'paid' && $expense->payment_mode)
                            <div class="text-xs text-gray-400 mt-0.5 capitalize">
                                {{ str_replace('_', ' ', $expense->payment_mode) }}
                                @if($expense->paid_at) &middot; {{ $expense->paid_at->format('d M') }} @endif
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right text-gray-700">&#8377;{{ number_format($expense->amount, 2) }}</td>
                    <td class="px-4 py-3 text-right text-amber-600 text-sm">
                        @if($expense->gst_applicable && $expense->gst_amount > 0)
                            &#8377;{{ number_format($expense->gst_amount, 2) }} <span class="text-xs text-gray-400">({{ $expense->gst_rate }}%)</span>
                        @else
                            &mdash;
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">&#8377;{{ number_format($expense->total_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if($expense->payment_status === 'unpaid')
                            <button type="button"
                                    onclick="openMarkPaid({{ $expense->id }}, '{{ addslashes($expense->title) }}', {{ $expense->total_amount }})"
                                    class="text-xs bg-green-600 text-white px-2 py-1 hover:bg-green-700 transition-colors mr-1">
                                Mark Paid
                            </button>
                        @endif
                        @if($expense->payment_status === 'paid' && $expense->voucher)
                            <a href="{{ route('finance.vouchers.show', $expense->voucher) }}"
                               class="text-xs border border-emerald-300 text-emerald-700 px-2 py-0.5 hover:bg-emerald-50 transition-colors mr-1"
                               title="View voucher {{ $expense->voucher->voucher_number }}">View</a>
                            <a href="{{ route('finance.vouchers.print', $expense->voucher) }}" target="_blank"
                               class="text-xs border border-gray-300 text-gray-600 px-2 py-0.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors mr-1">Print</a>
                            <a href="{{ route('finance.vouchers.print', $expense->voucher) }}?pdf=1" target="_blank"
                               class="text-xs border border-gray-300 text-gray-600 px-2 py-0.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors mr-1">PDF</a>
                        @endif
                        <a href="{{ route('finance.expenses.edit', $expense) }}" class="text-xs text-[#6a0f70] hover:underline mr-2">Edit</a>
                        <form method="POST" action="{{ route('finance.expenses.destroy', $expense) }}" class="inline"
                              onsubmit="return confirm('Delete this expense?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Del</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($expenses->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $expenses->links() }}</div>
        @endif
        @endif
    </div>

    @endif {{-- end vouchers/expense tab --}}

</div>

{{-- MARK PAID MODAL --}}
<div id="markPaidModal"
     class="fixed inset-0 bg-black bg-opacity-40 z-50 hidden items-center justify-center"
     onclick="if(event.target===this) closeMarkPaid()">
    <div class="bg-white border border-[#e8d5f0] w-full max-w-lg mx-4 p-6 shadow-xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between mb-1">
            <h3 class="text-lg font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Mark as Paid</h3>
            <button onclick="closeMarkPaid()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-sm text-gray-500 mb-4">Recording payment for: <strong id="mpTitle" class="text-gray-800"></strong></p>

        <form id="markPaidForm" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Payment Date <span class="text-red-500">*</span></label>
                    <input type="date" name="paid_at" id="mpDate" value="{{ today()->toDateString() }}"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Amount Paid (&#8377;) <span class="text-red-500">*</span></label>
                    <input type="number" name="paid_amount" id="mpAmount" step="0.01" min="0.01"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Payment Mode <span class="text-red-500">*</span></label>
                    <select name="paid_mode" id="mpMode"
                            class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                            onchange="onMpModeChange()" required>
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Clinic Account <span class="text-red-500">*</span></label>
                    <select name="paid_clinic_account" id="mpAccount"
                            class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                        <option value="">-- Select Account --</option>
                        @foreach($bankAccounts ?? [] as $acct)
                            <option value="{{ $acct->id }}">{{ $acct->account_name }}@if($acct->bank_name) &middot; {{ $acct->bank_name }}@endif</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div id="mpUtrRow">
                <label id="mpUtrLabel" class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">UTR / Transaction Reference <span class="text-red-500">*</span></label>
                <input type="text" name="paid_reference" id="mpReference" placeholder="UTR / Transaction ID"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div id="mpChequeRow" class="hidden">
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Cheque Number <span class="text-red-500">*</span></label>
                <input type="text" name="paid_cheque_number" id="mpChequeNo" placeholder="6-digit cheque number"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Notes</label>
                <textarea name="notes" rows="2" placeholder="Optional remarks"
                          class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"></textarea>
            </div>
            <div class="flex gap-3 pt-2 border-t border-gray-100">
                <button type="submit" class="flex-1 bg-green-600 text-white text-sm py-2.5 hover:bg-green-700 transition-colors font-medium">
                    Confirm Payment &amp; Generate Voucher
                </button>
                <button type="button" onclick="closeMarkPaid()" class="flex-1 border border-gray-300 text-sm py-2.5 text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openMarkPaid(id, title, amount) {
    document.getElementById('mpTitle').textContent = title;
    document.getElementById('mpAmount').value = amount;
    document.getElementById('markPaidForm').action = '/finance/expenses/' + id + '/mark-paid';
    document.getElementById('mpMode').value = 'cash';
    document.getElementById('mpReference').value = '';
    document.getElementById('mpChequeNo').value = '';
    onMpModeChange();
    document.getElementById('markPaidModal').classList.remove('hidden');
    document.getElementById('markPaidModal').classList.add('flex');
}
function closeMarkPaid() {
    document.getElementById('markPaidModal').classList.add('hidden');
    document.getElementById('markPaidModal').classList.remove('flex');
}
function onMpModeChange() {
    const mode = document.getElementById('mpMode').value;
    const utrRow = document.getElementById('mpUtrRow');
    const utrLabel = document.getElementById('mpUtrLabel');
    const utrInput = document.getElementById('mpReference');
    const chequeRow = document.getElementById('mpChequeRow');
    const chequeNo = document.getElementById('mpChequeNo');
    if (mode === 'cash') {
        utrRow.classList.add('hidden'); utrInput.required = false;
        chequeRow.classList.add('hidden'); chequeNo.required = false;
    } else if (mode === 'cheque') {
        utrRow.classList.remove('hidden'); utrLabel.textContent = 'UTR / Transaction Reference';
        utrInput.required = false;
        chequeRow.classList.remove('hidden'); chequeNo.required = true;
    } else {
        utrRow.classList.remove('hidden');
        utrLabel.innerHTML = 'UTR / Transaction Reference <span style="color:red">*</span>';
        utrInput.required = true;
        chequeRow.classList.add('hidden'); chequeNo.required = false;
    }
}
document.addEventListener('DOMContentLoaded', onMpModeChange);
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeMarkPaid(); });

function promptVoidReason(form) {
    const reason = prompt('Why is this voucher being voided? (required — this stays on the record)');
    if (!reason || !reason.trim()) return false;
    form.querySelector('input[name="void_reason"]').value = reason.trim();
    return true;
}
</script>
@endpush
@endsection
