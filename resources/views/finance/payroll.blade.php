@extends('layouts.app')
@section('page-title', 'Payroll — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- ── HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Payroll
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Payroll</h1>
        </div>
    </div>

    @session('success')
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ $value }}</div>
    @endsession

    {{-- ── MONTH PICKER ── --}}
    <form method="GET" action="{{ route('finance.payroll') }}" class="bg-white border border-[#e8d5f0] p-4">
        <div class="flex gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Month</label>
                <input type="month" name="month" value="{{ $month }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">View</button>
        </div>
    </form>

    {{-- ── SUMMARY ── --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Staff Count</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $summary['total_count'] }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Payout</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($summary['total_salary'], 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Paid</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $summary['paid'] }} / {{ $summary['total_count'] }}</p>
        </div>
    </div>

    {{-- ── EXISTING RECORDS ── --}}
    @if($records->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Staff</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Basic</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Incentives</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Deductions</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Net Salary</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Mode</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Paid On</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($records as $rec)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $rec->staff?->name ?? 'Unknown' }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">Rs. {{ number_format($rec->fixed_salary, 0) }}</td>
                    <td class="px-4 py-3 text-right text-green-600">{{ $rec->incentives > 0 ? '+Rs. ' . number_format($rec->incentives, 0) : '—' }}</td>
                    <td class="px-4 py-3 text-right text-red-500">{{ $rec->deductions > 0 ? '-Rs. ' . number_format($rec->deductions, 0) : '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($rec->net_salary, 0) }}</td>
                    <td class="px-4 py-3 text-gray-600 capitalize">{{ str_replace('_',' ', $rec->payment_mode) }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $rec->payment_date?->format('d M') }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('finance.payroll.destroy', $rec) }}" class="inline"
                              onsubmit="return confirm('Delete this payroll entry?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">Del</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ── ADD PAYROLL ENTRY ── --}}
    <div class="bg-white border border-[#e8d5f0] p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Add Payroll Entry</h2>
        <form method="POST" action="{{ route('finance.payroll.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Staff <span class="text-red-500">*</span></label>
                    <select name="user_id" required class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        <option value="">— Select —</option>
                        @foreach($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Month <span class="text-red-500">*</span></label>
                    <select name="month" required class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (int)$mon === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m)->format('F') }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Year <span class="text-red-500">*</span></label>
                    <input type="number" name="year" value="{{ $year }}" min="2020" max="2099"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                </div>
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Basic Salary <span class="text-red-500">*</span></label>
                    <input type="number" name="fixed_salary" step="0.01" min="0"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Incentives</label>
                    <input type="number" name="incentives" step="0.01" min="0" value="0"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Deductions</label>
                    <input type="number" name="deductions" step="0.01" min="0" value="0"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Payment Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" value="{{ today()->toDateString() }}"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Payment Mode <span class="text-red-500">*</span></label>
                    <select name="payment_mode" required class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        @foreach(['cash','upi','bank_transfer','cheque','other'] as $m)
                            <option value="{{ $m }}">{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Reference No.</label>
                    <input type="text" name="reference_number"
                           class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
            </div>
            <button type="submit" dusk="payroll-save" class="bg-[#6a0f70] text-white text-sm px-6 py-2 hover:bg-[#380740] transition-colors">
                Save Payroll Entry
            </button>
        </form>
    </div>

</div>
@endsection
