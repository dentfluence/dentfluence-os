@extends('layouts.app')
@section('page-title', 'Vendors — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Vendors
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Vendor Management</h1>
        </div>
        <button class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Vendor
        </button>
    </div>

    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['label'=>'Total Vendors',    'val'=>'14',        'color'=>'text-blue-600'],
            ['label'=>'Total Outstanding','val'=>'₹84,500',   'color'=>'text-amber-600'],
            ['label'=>'This Month Paid',  'val'=>'₹38,200',   'color'=>'text-green-600'],
            ['label'=>'Overdue',          'val'=>'3 vendors',  'color'=>'text-red-500'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $c['label'] }}</p>
            <p class="text-xl font-semibold {{ $c['color'] }}">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach([
            ['name'=>'City Dental Lab',     'company'=>'City Dental Lab Pvt Ltd', 'type'=>'Lab',             'phone'=>'+91 98765 12345','outstanding'=>18500,'total'=>142000,'credit'=>30],
            ['name'=>'Osstem Implants',     'company'=>'Osstem India Pvt Ltd',    'type'=>'Implant Company', 'phone'=>'+91 80001 56789','outstanding'=>0,    'total'=>380000,'credit'=>45],
            ['name'=>'Prime Dental Supply', 'company'=>'Prime Dental Supplies',   'type'=>'Dental Supplier', 'phone'=>'+91 77777 23456','outstanding'=>6800, 'total'=>95000, 'credit'=>15],
            ['name'=>'Digital Dentistry',   'company'=>'Digital Dentistry Works', 'type'=>'Lab',             'phone'=>'+91 91234 78901','outstanding'=>8200, 'total'=>64000, 'credit'=>21],
            ['name'=>'Meta / Facebook',     'company'=>'Meta Platforms Inc.',     'type'=>'Marketing',       'phone'=>'—',              'outstanding'=>0,    'total'=>42000, 'credit'=>0],
            ['name'=>'CA Ramesh Joshi',     'company'=>'Ramesh Joshi & Assoc.',   'type'=>'CA',              'phone'=>'+91 98001 34567','outstanding'=>8000, 'total'=>48000, 'credit'=>0],
        ] as $v)
        <div class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] transition-colors">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <div class="font-semibold text-gray-800">{{ $v['name'] }}</div>
                    <div class="text-xs text-gray-400 mt-0.5">{{ $v['company'] }}</div>
                </div>
                <span class="text-xs bg-[#f3e8f4] text-[#6a0f70] px-2 py-0.5">{{ $v['type'] }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Outstanding</p>
                    <p class="text-lg font-semibold {{ $v['outstanding']>0 ? 'text-amber-600' : 'text-green-600' }}">
                        {{ $v['outstanding'] > 0 ? '₹'.number_format($v['outstanding']) : 'Cleared' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Total Purchases</p>
                    <p class="text-lg font-semibold text-gray-700">₹{{ number_format($v['total']) }}</p>
                </div>
            </div>
            <div class="flex justify-between items-center pt-3 border-t border-[#e8d5f0]">
                <div class="text-xs text-gray-400">
                    {{ $v['phone'] }}
                    @if($v['credit'] > 0) · {{ $v['credit'] }}d credit @endif
                </div>
                <div class="flex gap-2">
                    <button class="text-xs border border-[#e8d5f0] text-gray-500 px-3 py-1 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">Ledger</button>
                    <button class="text-xs bg-[#6a0f70] text-white px-3 py-1 hover:bg-[#380740] transition-colors">Pay</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
