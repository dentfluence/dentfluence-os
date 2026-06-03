@extends('layouts.app')
@section('page-title', 'GST — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; GST
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">GST Management</h1>
    </div>

    {{-- GST Toggle Notice --}}
    <div class="bg-amber-50 border border-amber-200 p-5">
        <div class="flex items-start justify-between">
            <div class="flex items-start gap-4">
                <span class="text-2xl mt-0.5">🔒</span>
                <div>
                    <p class="font-semibold text-amber-800 mb-1">GST is currently disabled</p>
                    <p class="text-sm text-amber-700">
                        Your clinic's GST module is OFF. The entire architecture is GST-ready from Day 1.<br>
                        When you register for GST, enable it below — <strong>no redesign needed, just activate.</strong>
                    </p>
                    <div class="flex items-center gap-3 mt-4">
                        <input type="checkbox" id="gstToggle" class="accent-[#6a0f70] w-4 h-4">
                        <label for="gstToggle" class="text-sm text-amber-700">Enable GST for this clinic</label>
                    </div>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">GSTIN</p>
                <p class="text-sm text-gray-500 font-mono">Not Registered</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-5">

        {{-- Readiness Checklist --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">What's Ready When You Enable GST</p>
            <div class="space-y-2">
                @foreach([
                    'GSTIN field on all invoices',
                    'HSN/SAC code on every treatment/product',
                    'CGST + SGST + IGST auto-calculation',
                    'GST-ready invoice template',
                    'Input Tax Credit (ITC) tracking',
                    'Output Tax tracking on income',
                    'GSTR-1 monthly export',
                    'GSTR-3B summary',
                    'GST vendor tracking',
                    'Tally-compatible GST export',
                    'CA GST report in one click',
                ] as $item)
                <div class="flex items-center gap-3 py-1.5 border-b border-[#e8d5f0] last:border-0">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <span class="text-sm text-gray-600">{{ $item }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- GST Settings (disabled) --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">GST Settings (Inactive)</p>
            @foreach([
                ['label'=>'GSTIN',           'placeholder'=>'27AABCT1234Z1Z5'],
                ['label'=>'State Code',      'placeholder'=>'27 (Maharashtra)'],
                ['label'=>'Business Type',   'placeholder'=>'Proprietorship'],
                ['label'=>'HSN/SAC Default', 'placeholder'=>'998311 (Dental Services)'],
            ] as $field)
            <div class="mb-4">
                <label class="block text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $field['label'] }}</label>
                <input disabled type="text" placeholder="{{ $field['placeholder'] }}"
                    class="w-full border border-gray-200 bg-gray-50 text-gray-400 text-sm px-3 py-2 cursor-not-allowed">
            </div>
            @endforeach
            <div class="bg-amber-50 border border-amber-200 p-3 text-xs text-amber-700 mt-2">
                💡 These fields activate when GST is enabled. All historical transactions will be retroactively tagged.
            </div>
        </div>
    </div>

    {{-- GST Rate Reference --}}
    <div class="bg-white border border-[#e8d5f0]">
        <div class="px-5 py-4 border-b border-[#e8d5f0]">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">Dental Services GST Reference</h2>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                    @foreach(['Service','HSN/SAC','GST Rate','CGST','SGST','Notes'] as $h)
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach([
                    ['service'=>'Clinical Services (Consultation, RCT, Surgery)','hsn'=>'998311','rate'=>'Exempt','cgst'=>'—','sgst'=>'—','note'=>'Healthcare exempt from GST'],
                    ['service'=>'Cosmetic Dental (Whitening, Veneers)',           'hsn'=>'998311','rate'=>'18%',   'cgst'=>'9%','sgst'=>'9%','note'=>'Cosmetic = taxable'],
                    ['service'=>'Dental Products / Sales',                        'hsn'=>'3006',  'rate'=>'12%',   'cgst'=>'6%','sgst'=>'6%','note'=>'Toothpaste, kits etc.'],
                    ['service'=>'Lab Charges (to patient)',                        'hsn'=>'998314','rate'=>'12%',   'cgst'=>'6%','sgst'=>'6%','note'=>'Dental prosthetics'],
                    ['service'=>'Equipment Purchase',                              'hsn'=>'9018',  'rate'=>'12%',   'cgst'=>'6%','sgst'=>'6%','note'=>'Dental chair, instruments'],
                ] as $row)
                <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['service'] }}</td>
                    <td class="px-4 py-3 text-sm font-mono text-gray-500">{{ $row['hsn'] }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs font-semibold {{ $row['rate']==='Exempt' ? 'text-green-600' : 'text-amber-600' }}">{{ $row['rate'] }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $row['cgst'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ $row['sgst'] }}</td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $row['note'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection
