@extends('layouts.app')
@section('page-title', 'Payroll — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Payroll
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Staff Payroll</h1>
        </div>
        <div class="flex gap-2">
            <select class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option>May 2026</option><option>April 2026</option><option>March 2026</option>
            </select>
            <button class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                Process Payroll
            </button>
        </div>
    </div>

    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['label'=>'Total Staff',   'val'=>'6',          'color'=>'text-blue-600'],
            ['label'=>'Gross Payroll', 'val'=>'₹1,82,000',  'color'=>'text-[#6a0f70]'],
            ['label'=>'Net Payroll',   'val'=>'₹1,68,500',  'color'=>'text-green-600'],
            ['label'=>'Pending',       'val'=>'2 staff',    'color'=>'text-amber-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $c['label'] }}</p>
            <p class="text-xl font-semibold {{ $c['color'] }}">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-white border border-[#e8d5f0]">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                    @foreach(['Employee','Role','Fixed','Incentives','Bonus','Deductions','Advance Adj.','Net Salary','Mode','Status',''] as $h)
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach([
                    ['name'=>'Dr. Ananya Mehta','role'=>'Associate Dentist','fixed'=>65000,'incentive'=>8000,'bonus'=>5000,'deduct'=>0,   'advance'=>0,   'mode'=>'Bank Transfer','status'=>'paid'],
                    ['name'=>'Priya Deshpande', 'role'=>'Dental Nurse',    'fixed'=>22000,'incentive'=>2000,'bonus'=>0,   'deduct'=>500, 'advance'=>2000,'mode'=>'Bank Transfer','status'=>'paid'],
                    ['name'=>'Rahul Patil',      'role'=>'Receptionist',    'fixed'=>18000,'incentive'=>1500,'bonus'=>0,   'deduct'=>0,   'advance'=>0,   'mode'=>'Bank Transfer','status'=>'paid'],
                    ['name'=>'Sanjay Kumar',     'role'=>'Lab Technician',  'fixed'=>25000,'incentive'=>3000,'bonus'=>0,   'deduct'=>0,   'advance'=>5000,'mode'=>'Cash',         'status'=>'pending'],
                    ['name'=>'Meena Joshi',      'role'=>'Sterilization',   'fixed'=>14000,'incentive'=>0,   'bonus'=>0,   'deduct'=>0,   'advance'=>0,   'mode'=>'UPI',          'status'=>'paid'],
                    ['name'=>'Amit Sharma',      'role'=>'Helper',          'fixed'=>12000,'incentive'=>0,   'bonus'=>0,   'deduct'=>0,   'advance'=>1000,'mode'=>'Cash',         'status'=>'pending'],
                ] as $r)
                @php $net = $r['fixed'] + $r['incentive'] + $r['bonus'] - $r['deduct'] - $r['advance']; @endphp
                <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ $r['name'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $r['role'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format($r['fixed']) }}</td>
                    <td class="px-4 py-3 text-sm text-green-600">+₹{{ number_format($r['incentive']) }}</td>
                    <td class="px-4 py-3 text-sm text-green-500">+₹{{ number_format($r['bonus']) }}</td>
                    <td class="px-4 py-3 text-sm text-red-500">−₹{{ number_format($r['deduct']) }}</td>
                    <td class="px-4 py-3 text-sm text-amber-600">−₹{{ number_format($r['advance']) }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-[#380740]">₹{{ number_format($net) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $r['mode'] }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 {{ $r['status']==='paid' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                            {{ ucfirst($r['status']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($r['status']==='pending')
                        <button class="text-xs border border-[#6a0f70] text-[#6a0f70] px-3 py-1 hover:bg-[#6a0f70] hover:text-white transition-colors">Mark Paid</button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
