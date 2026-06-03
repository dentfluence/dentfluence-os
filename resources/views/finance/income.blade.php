@extends('layouts.app')
@section('page-title', 'Income — Finance')


@section('content')
<div class="p-6 space-y-5" x-data="incomeManager()" x-init="init()">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp; Income
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Income Management
            </h1>
        </div>
        <button @click="showRecordPayment = true"
            class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Record Payment
        </button>
    </div>

    {{-- ── KPI STRIP ── --}}
    <div class="grid grid-cols-5 gap-3">
        @foreach([
            ['label'=>'Today',        'val'=>'₹18,500',   'sub'=>'3 payments',  'color'=>'text-green-600'],
            ['label'=>'This Week',    'val'=>'₹1,12,000', 'sub'=>'18 payments', 'color'=>'text-blue-600'],
            ['label'=>'This Month',   'val'=>'₹4,12,000', 'sub'=>'68 payments', 'color'=>'text-[#6a0f70]'],
            ['label'=>'Outstanding',  'val'=>'₹1,34,000', 'sub'=>'14 patients', 'color'=>'text-amber-600'],
            ['label'=>'Advances Held','val'=>'₹28,500',   'sub'=>'7 patients',  'color'=>'text-purple-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $c['label'] }}</p>
            <p class="text-xl font-semibold {{ $c['color'] }}">{{ $c['val'] }}</p>
            <p class="text-xs text-gray-400 mt-0.5">{{ $c['sub'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── TAB BAR ── --}}
    <div class="flex items-center gap-1 border-b border-[#e8d5f0]">
        <button @click="activeTab = 'table'"
            :class="activeTab === 'table' ? 'border-b-2 border-[#6a0f70] text-[#6a0f70] font-semibold' : 'text-gray-400 hover:text-gray-600'"
            class="px-4 py-2.5 text-sm transition-colors -mb-px">
            Table View
        </button>
        <button @click="activeTab = 'summary'"
            :class="activeTab === 'summary' ? 'border-b-2 border-[#6a0f70] text-[#6a0f70] font-semibold' : 'text-gray-400 hover:text-gray-600'"
            class="px-4 py-2.5 text-sm transition-colors -mb-px">
            Summary View
        </button>
        <div class="ml-auto flex items-center gap-2 pb-2">
            {{-- Export --}}
            <button @click="showExport = true"
                class="inline-flex items-center gap-2 border border-gray-300 text-gray-600 text-sm px-4 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export
            </button>
        </div>
    </div>

    {{-- ── FILTERS (shown in both tabs) ── --}}
    <div class="flex gap-2 flex-wrap items-center">
        <input type="date" x-model="filters.from" class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
        <input type="date" x-model="filters.to"   class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
        <select x-model="filters.category" class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            <option value="">All Categories</option>
            <option>Implant</option><option>RCT</option><option>Consultation</option>
            <option>Aligners</option><option>Crown</option><option>Membership</option>
        </select>
        <select x-model="filters.mode" class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            <option value="">All Modes</option>
            <option>Cash</option><option>UPI</option><option>Credit Card</option><option>Debit Card</option><option>Bank Transfer</option><option>Cheque</option><option>EMI</option>
        </select>
        <input type="text" x-model="filters.search" placeholder="Search patient…"
            class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70] min-w-44">
    </div>

    {{-- ════════════════════════════════════════════
         TAB: SUMMARY VIEW
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'summary'" x-transition class="space-y-4">

        <div class="grid grid-cols-3 gap-4">

            {{-- By Category --}}
            <div class="bg-white border border-[#e8d5f0]">
                <div class="px-5 py-3 border-b border-[#e8d5f0] bg-[#faf5fc]">
                    <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">By Treatment Category</p>
                </div>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[#e8d5f0]">
                            <th class="text-left px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Category</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Cases</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in summaryByCategory" :key="row.category">
                            <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc]">
                                <td class="px-4 py-2.5"><span class="text-xs bg-[#f3e8f4] text-[#6a0f70] px-2 py-0.5" x-text="row.category"></span></td>
                                <td class="px-4 py-2.5 text-right text-sm text-gray-500" x-text="row.cases"></td>
                                <td class="px-4 py-2.5 text-right text-sm font-semibold text-green-600" x-text="'₹' + row.collected.toLocaleString('en-IN')"></td>
                            </tr>
                        </template>
                        <tr class="bg-[#faf5fc]">
                            <td class="px-4 py-2.5 text-xs font-semibold uppercase text-gray-600">Total</td>
                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-gray-700" x-text="summaryByCategory.reduce((s,r)=>s+r.cases,0)"></td>
                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-green-700" x-text="'₹' + summaryByCategory.reduce((s,r)=>s+r.collected,0).toLocaleString('en-IN')"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- By Doctor --}}
            <div class="bg-white border border-[#e8d5f0]">
                <div class="px-5 py-3 border-b border-[#e8d5f0] bg-[#faf5fc]">
                    <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">By Doctor</p>
                </div>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[#e8d5f0]">
                            <th class="text-left px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Doctor</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Cases</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in summaryByDoctor" :key="row.doctor">
                            <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc]">
                                <td class="px-4 py-2.5 text-sm font-medium text-gray-700" x-text="row.doctor"></td>
                                <td class="px-4 py-2.5 text-right text-sm text-gray-500" x-text="row.cases"></td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="w-14 h-1.5 bg-[#f3e8f4] rounded-full">
                                            <div class="h-1.5 bg-[#6a0f70] rounded-full" :style="'width:'+row.share+'%'"></div>
                                        </div>
                                        <span class="text-xs text-[#6a0f70]" x-text="row.share+'%'"></span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- By Mode --}}
            <div class="bg-white border border-[#e8d5f0]">
                <div class="px-5 py-3 border-b border-[#e8d5f0] bg-[#faf5fc]">
                    <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">By Payment Mode</p>
                </div>
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-[#e8d5f0]">
                            <th class="text-left px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Mode</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Txns</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in summaryByMode" :key="row.mode">
                            <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc]">
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full inline-block"
                                            :style="'background:' + modeColor(row.mode)"></span>
                                        <span class="text-sm text-gray-700" x-text="row.mode"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right text-sm text-gray-500" x-text="row.count"></td>
                                <td class="px-4 py-2.5 text-right text-sm font-semibold text-green-600" x-text="'₹' + row.amount.toLocaleString('en-IN')"></td>
                            </tr>
                        </template>
                        <tr class="bg-[#faf5fc]">
                            <td class="px-4 py-2.5 text-xs font-semibold uppercase text-gray-600">Total</td>
                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-gray-700" x-text="summaryByMode.reduce((s,r)=>s+r.count,0)"></td>
                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-green-700" x-text="'₹' + summaryByMode.reduce((s,r)=>s+r.amount,0).toLocaleString('en-IN')"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB: TABLE VIEW
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'table'" x-transition class="bg-white border border-[#e8d5f0]">
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 w-24">
                        <button @click="setSort('id')" class="flex items-center gap-1 hover:text-[#6a0f70]"># <span x-html="sortIcon('id')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('patient')" class="flex items-center gap-1 hover:text-[#6a0f70]">Patient <span x-html="sortIcon('patient')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('cat')" class="flex items-center gap-1 hover:text-[#6a0f70]">Category <span x-html="sortIcon('cat')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('doc')" class="flex items-center gap-1 hover:text-[#6a0f70]">Doctor <span x-html="sortIcon('doc')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('mode')" class="flex items-center gap-1 hover:text-[#6a0f70]">Mode <span x-html="sortIcon('mode')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('amount')" class="flex items-center gap-1 hover:text-[#6a0f70]">Amount <span x-html="sortIcon('amount')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('outstanding')" class="flex items-center gap-1 hover:text-[#6a0f70]">Outstanding <span x-html="sortIcon('outstanding')"></span></button>
                    </th>
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">
                        <button @click="setSort('date')" class="flex items-center gap-1 hover:text-[#6a0f70]">Date <span x-html="sortIcon('date')"></span></button>
                    </th>
                    <th class="px-4 py-3 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in filteredRows" :key="row.id">
                    <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors"
                        :class="row.deleted ? 'opacity-60 bg-red-50' : ''">
                        <td class="px-4 py-3 text-xs text-gray-400 font-mono" :class="row.deleted ? 'line-through' : ''" x-text="row.id"></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-800" :class="row.deleted ? 'line-through text-gray-400' : ''" x-text="row.patient"></span>
                                <span x-show="row.deleted" class="text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium">Deleted</span>
                            </div>
                            <p x-show="row.deleted && row.deleteReason" class="text-xs text-red-400 mt-0.5" x-text="'Reason: ' + row.deleteReason"></p>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs bg-[#f3e8f4] text-[#6a0f70] px-2 py-0.5" :class="row.deleted ? 'opacity-50' : ''" x-text="row.cat"></span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" :class="row.deleted ? 'line-through' : ''" x-text="row.doc"></td>
                        <td class="px-4 py-3 text-sm text-gray-500" :class="row.deleted ? 'line-through' : ''" x-text="row.mode"></td>
                        <td class="px-4 py-3 text-sm font-semibold" :class="row.deleted ? 'line-through text-gray-400' : 'text-green-600'" x-text="'+₹' + row.amount.toLocaleString('en-IN')"></td>
                        <td class="px-4 py-3 text-sm" :class="row.deleted ? 'line-through text-gray-400' : (row.outstanding > 0 ? 'text-amber-600 font-medium' : 'text-green-600')"
                            x-text="row.outstanding > 0 ? '₹' + row.outstanding.toLocaleString('en-IN') : 'Cleared'"></td>
                        <td class="px-4 py-3 text-xs text-gray-400" :class="row.deleted ? 'line-through' : ''" x-text="row.date"></td>
                        <td class="px-4 py-3 relative">
                            <button @click.stop="toggleMenu(row.id)" :disabled="row.deleted"
                                class="text-gray-400 hover:text-[#6a0f70] disabled:opacity-30 disabled:cursor-not-allowed">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                            </button>
                            <div x-show="openMenu === row.id" @click.outside="openMenu = null"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute right-8 top-2 z-30 bg-white border border-[#e8d5f0] shadow-lg w-48 py-1">
                                <button @click="viewInvoice(row); openMenu = null"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-[#faf5fc] hover:text-[#6a0f70]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    View Invoice
                                </button>
                                <button @click="printReceipt(row); openMenu = null"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-[#faf5fc] hover:text-[#6a0f70]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    Print Receipt
                                </button>
                                <div class="border-t border-[#e8d5f0] my-1"></div>
                                <button @click="shareInvoice(row, 'email'); openMenu = null"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-[#faf5fc] hover:text-[#6a0f70]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    Share via Email
                                </button>
                                <button @click="shareInvoice(row, 'whatsapp'); openMenu = null"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-[#faf5fc] hover:text-[#6a0f70]">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                                    Share via WhatsApp
                                </button>
                                <div class="border-t border-[#e8d5f0] my-1"></div>
                                <button @click="openDeleteModal(row); openMenu = null"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                                    Delete Invoice
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
                <tr x-show="filteredRows.length === 0">
                    <td colspan="9" class="px-5 py-10 text-center text-sm text-gray-400">No records found.</td>
                </tr>
            </tbody>
        </table>
        <div class="px-5 py-3 flex justify-between items-center border-t border-[#e8d5f0]">
            <span class="text-xs text-gray-400" x-text="'Showing ' + filteredRows.length + ' of ' + rows.length + ' entries'"></span>
            <div class="flex gap-2">
                <button class="text-xs border border-gray-300 text-gray-500 px-3 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70]">← Prev</button>
                <button class="text-xs border border-[#6a0f70] bg-[#6a0f70] text-white px-3 py-1.5">Next →</button>
            </div>
        </div>
    </div>


    {{-- Print area — populated by JS, hidden off-screen --}}
    <div id="print-invoice-area" style="position:absolute;left:-9999px;visibility:hidden;">
        <div style="font-family:'Helvetica Neue',Arial,sans-serif; max-width:520px; margin:0 auto; padding:24px; border:1px solid #e8d5f0;">

            {{-- Header --}}
            <div style="display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:16px; border-bottom:2px solid #6a0f70; margin-bottom:16px;">
                <div>
                    <p style="font-size:20px; font-weight:700; color:#6a0f70; margin:0;">Dentfluence Dental Clinic</p>
                    <p style="font-size:11px; color:#888; margin:4px 0 0;">123 Clinic Street, Mumbai · +91 98765 43210</p>
                    <p style="font-size:11px; color:#888; margin:2px 0 0;">GST: 27XXXXX1234Z1ZX</p>
                </div>
                <div style="text-align:right;">
                    <p style="font-size:10px; color:#888; text-transform:uppercase; letter-spacing:1px; margin:0;">INVOICE</p>
                    <p id="pr-id" style="font-size:16px; font-weight:700; color:#6a0f70; margin:4px 0 0;"></p>
                    <p id="pr-date" style="font-size:11px; color:#888; margin:4px 0 0;"></p>
                </div>
            </div>

            {{-- Patient / Doctor --}}
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                <div>
                    <p style="font-size:10px; color:#888; text-transform:uppercase; letter-spacing:1px; margin:0 0 4px;">Patient</p>
                    <p id="pr-patient" style="font-size:13px; font-weight:600; color:#222; margin:0;"></p>
                </div>
                <div>
                    <p style="font-size:10px; color:#888; text-transform:uppercase; letter-spacing:1px; margin:0 0 4px;">Doctor</p>
                    <p id="pr-doc" style="font-size:13px; font-weight:600; color:#222; margin:0;"></p>
                </div>
            </div>

            {{-- Line item --}}
            <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
                <thead>
                    <tr style="background:#faf5fc;">
                        <th style="text-align:left; padding:8px 12px; font-size:10px; color:#888; text-transform:uppercase; letter-spacing:1px; border:1px solid #e8d5f0;">Treatment</th>
                        <th style="text-align:right; padding:8px 12px; font-size:10px; color:#888; text-transform:uppercase; letter-spacing:1px; border:1px solid #e8d5f0;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td id="pr-treatment" style="padding:10px 12px; font-size:13px; color:#333; border:1px solid #e8d5f0;"></td>
                        <td id="pr-amount-cell" style="padding:10px 12px; font-size:13px; font-weight:600; color:#333; text-align:right; border:1px solid #e8d5f0;"></td>
                    </tr>
                </tbody>
            </table>

            {{-- Payment summary --}}
            <div style="border:1px solid #e8d5f0; padding:12px 16px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-size:12px; color:#666;">Payment Mode</span>
                    <span id="pr-mode" style="font-size:12px; font-weight:600; color:#333;"></span>
                </div>
                <div id="pr-ref-row" style="display:none; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-size:12px; color:#666;" id="pr-ref-label">Reference</span>
                    <span id="pr-ref-val" style="font-size:12px; color:#333;"></span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span style="font-size:12px; color:#666;">Amount Paid</span>
                    <span id="pr-paid" style="font-size:13px; font-weight:700; color:#16a34a;"></span>
                </div>
                <div style="border-top:1px solid #e8d5f0; padding-top:8px; display:flex; justify-content:space-between;">
                    <span style="font-size:12px; color:#666;">Balance Outstanding</span>
                    <span id="pr-outstanding" style="font-size:13px; font-weight:700;"></span>
                </div>
            </div>

            {{-- Footer --}}
            <p style="font-size:10px; color:#aaa; text-align:center; margin:0;">
                Thank you for choosing Dentfluence · This is a computer-generated receipt
            </p>
        </div>
    </div>


    {{-- ════════════════════════════════════════════
         MODAL: RECORD PAYMENT
    ════════════════════════════════════════════ --}}
    <div x-show="showRecordPayment" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div @click.outside="showRecordPayment = false"
            class="bg-white w-full max-w-lg shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto">

            <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0] bg-[#faf5fc] sticky top-0">
                <div>
                    <h2 class="text-base font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Record Payment</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Link to existing patient · All fields required</p>
                </div>
                <button @click="showRecordPayment = false" class="text-gray-400 hover:text-gray-600">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="px-6 py-5 space-y-4">

                {{-- Patient Search --}}
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Patient</label>
                    <div class="relative">
                        <input type="text" x-model="payment.patientSearch" @input="searchPatients()" placeholder="Search patient name or ID…"
                            class="w-full text-sm border border-gray-300 px-3 py-2 pr-8 focus:outline-none focus:border-[#6a0f70]">
                        <svg class="absolute right-3 top-2.5 text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <div x-show="patientResults.length > 0" class="absolute z-10 w-full bg-white border border-[#e8d5f0] shadow-md mt-1">
                            <template x-for="p in patientResults" :key="p.id">
                                <button @click="selectPatient(p)" class="w-full flex items-center justify-between px-3 py-2 hover:bg-[#faf5fc] text-left">
                                    <span class="text-sm text-gray-700" x-text="p.name"></span>
                                    <span class="text-xs text-gray-400" x-text="p.phone"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <p x-show="payment.patient" class="text-xs text-[#6a0f70] mt-1">✓ <span x-text="payment.patient + ' selected'"></span></p>
                </div>

                {{-- Amount + Mode --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Amount Paid (₹)</label>
                        <input type="number" x-model="payment.amount" @input="calcConvenience()" placeholder="0"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Payment Mode</label>
                        <select x-model="payment.mode" @change="calcConvenience()" class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            <option value="">Select mode</option>
                            <option>Cash</option>
                            <option>UPI</option>
                            <option>Credit Card</option>
                            <option>Debit Card</option>
                            <option>Bank Transfer</option>
                            <option>Cheque</option>
                            <option>EMI</option>
                        </select>
                    </div>
                </div>

                {{-- ── MODE-SPECIFIC FIELDS ── --}}

                {{-- UPI: reference number only --}}
                <div x-show="payment.mode === 'UPI'" class="bg-blue-50 border border-blue-200 p-3 space-y-2">
                    <p class="text-xs font-semibold text-blue-700 uppercase tracking-widest">UPI Details</p>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">UPI Transaction Ref No.</label>
                        <input type="text" x-model="payment.upiRef" placeholder="e.g. 426831908234"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-blue-400">
                    </div>
                </div>

                {{-- Credit Card: with 2.5% convenience charge above ₹10K --}}
                <div x-show="payment.mode === 'Credit Card'" class="bg-purple-50 border border-purple-200 p-3 space-y-3">
                    <p class="text-xs font-semibold text-purple-700 uppercase tracking-widest">Credit Card Details</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Card Last 4 Digits</label>
                            <input type="text" x-model="payment.cardLast4" maxlength="4" placeholder="XXXX"
                                class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-purple-400">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Network</label>
                            <select x-model="payment.cardType" class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-purple-400">
                                <option value="">Select</option>
                                <option>Visa</option><option>Mastercard</option><option>Amex</option><option>RuPay</option>
                            </select>
                        </div>
                    </div>
                    <div x-show="payment.amount >= 10000" class="flex items-center justify-between px-3 py-2 bg-purple-100 border border-purple-300">
                        <div>
                            <p class="text-xs font-semibold text-purple-800">2.5% Convenience Charge Applied</p>
                            <p class="text-xs text-purple-600 mt-0.5">On credit card payments above ₹10,000</p>
                        </div>
                        <span class="text-sm font-bold text-purple-800" x-text="'+ ₹' + convenienceCharge.toLocaleString('en-IN')"></span>
                    </div>
                </div>

                {{-- Debit Card: no charges --}}
                <div x-show="payment.mode === 'Debit Card'" class="bg-blue-50 border border-blue-200 p-3 space-y-3">
                    <p class="text-xs font-semibold text-blue-700 uppercase tracking-widest">Debit Card Details</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Card Last 4 Digits</label>
                            <input type="text" x-model="payment.cardLast4" maxlength="4" placeholder="XXXX"
                                class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-blue-400">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Network</label>
                            <select x-model="payment.cardType" class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-blue-400">
                                <option value="">Select</option>
                                <option>Visa</option><option>Mastercard</option><option>RuPay</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs text-blue-600">✓ No convenience charges on debit card payments.</p>
                </div>

                {{-- Bank Transfer --}}
                <div x-show="payment.mode === 'Bank Transfer'" class="bg-green-50 border border-green-200 p-3 space-y-3">
                    <p class="text-xs font-semibold text-green-700 uppercase tracking-widest">Bank Transfer Details</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Bank Name</label>
                            <input type="text" x-model="payment.bankName" placeholder="e.g. HDFC Bank"
                                class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-green-400">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Transaction Ref No.</label>
                            <input type="text" x-model="payment.bankRef" placeholder="e.g. NEFT/RTGS ref"
                                class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-green-400">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Transfer Date</label>
                        <input type="date" x-model="payment.bankDate"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-green-400">
                    </div>
                </div>

                {{-- Cheque --}}
                <div x-show="payment.mode === 'Cheque'" class="bg-amber-50 border border-amber-200 p-3 space-y-3">
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-widest">Cheque Details</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Bank Name</label>
                            <input type="text" x-model="payment.chequeBankName" placeholder="e.g. SBI"
                                class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-amber-400">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Cheque Number</label>
                            <input type="text" x-model="payment.chequeNo" placeholder="e.g. 000123"
                                class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-amber-400">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1 block">Cheque Date</label>
                        <input type="date" x-model="payment.chequeDate"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-amber-400">
                    </div>
                    <p class="text-xs text-amber-600">⚠ Payment will be confirmed only after cheque clearance.</p>
                </div>

                {{-- Category + Doctor --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Treatment Category</label>
                        <select x-model="payment.category" class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            <option value="">Select category</option>
                            <option>Implant</option><option>RCT</option><option>Consultation</option>
                            <option>Aligners</option><option>Crown</option><option>Membership</option>
                            <option>Extraction</option><option>Braces</option><option>Scaling</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Doctor</label>
                        <select x-model="payment.doctor" class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            <option value="">Select doctor</option>
                            <option>Dr. Sumit Firke</option><option>Dr. Priya Mehta</option><option>Dr. Arjun Sharma</option>
                        </select>
                    </div>
                </div>

                {{-- Total Bill + Date --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Total Bill Amount (₹)</label>
                        <input type="number" x-model="payment.totalBill" placeholder="0"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Date</label>
                        <input type="date" x-model="payment.date"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>

                {{-- Total with convenience --}}
                <div x-show="payment.amount" class="flex items-center justify-between px-4 py-3 bg-[#faf5fc] border border-[#e8d5f0]">
                    <div>
                        <span class="text-xs text-gray-500">Total Charged</span>
                        <span x-show="payment.mode === 'Credit Card' && payment.amount >= 10000"
                            class="text-xs text-purple-600 ml-2">(incl. 2.5% card charge)</span>
                    </div>
                    <span class="text-sm font-semibold text-[#6a0f70]" x-text="'₹' + totalCharged.toLocaleString('en-IN')"></span>
                </div>

                {{-- Outstanding --}}
                <div x-show="payment.totalBill && payment.amount"
                    class="flex items-center justify-between px-4 py-3 bg-[#faf5fc] border border-[#e8d5f0]">
                    <span class="text-xs text-gray-500">Outstanding after this payment</span>
                    <span class="text-sm font-semibold"
                        :class="(payment.totalBill - payment.amount) > 0 ? 'text-amber-600' : 'text-green-600'"
                        x-text="(payment.totalBill - payment.amount) > 0 ? '₹' + (payment.totalBill - payment.amount).toLocaleString('en-IN') : 'Cleared ✓'">
                    </span>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Notes (optional)</label>
                    <textarea x-model="payment.notes" rows="2" placeholder="EMI installment 2/6, advance for implant…"
                        class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t border-[#e8d5f0] bg-[#faf5fc] sticky bottom-0">
                <button @click="showRecordPayment = false"
                    class="text-sm border border-gray-300 text-gray-600 px-5 py-2 hover:border-gray-400 transition-colors">Cancel</button>
                <button @click="savePayment()"
                    class="text-sm bg-[#6a0f70] text-white px-5 py-2 hover:bg-[#380740] transition-colors">Save Payment</button>
            </div>
        </div>
    </div>


    {{-- ════════════════════════════════════════════
         MODAL: VIEW INVOICE
    ════════════════════════════════════════════ --}}
    <div x-show="showViewInvoice" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div @click.outside="showViewInvoice = false" class="bg-white w-full max-w-lg shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0]">
                <h2 class="text-base font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;" x-text="'Invoice · ' + viewTarget?.id"></h2>
                <div class="flex items-center gap-2">
                    <button @click="printReceipt(viewTarget)"
                        class="inline-flex items-center gap-1.5 text-xs border border-gray-300 text-gray-600 px-3 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70]">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print
                    </button>
                    <button @click="showViewInvoice = false" class="text-gray-400 hover:text-gray-600">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="flex items-start justify-between pb-4 border-b border-[#e8d5f0]">
                    <div>
                        <p class="text-lg font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Dentfluence Dental Clinic</p>
                        <p class="text-xs text-gray-400 mt-0.5">123 Clinic Street, Mumbai · +91 98765 43210</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400 uppercase tracking-widest">Invoice No.</p>
                        <p class="text-sm font-mono font-semibold text-[#6a0f70]" x-text="viewTarget?.id"></p>
                        <p class="text-xs text-gray-400 mt-1" x-text="viewTarget?.date"></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Patient</p><p class="text-sm font-medium text-gray-800" x-text="viewTarget?.patient"></p></div>
                    <div><p class="text-xs text-gray-400 uppercase tracking-widest mb-1">Doctor</p><p class="text-sm font-medium text-gray-800" x-text="viewTarget?.doc"></p></div>
                </div>
                <table class="w-full border border-[#e8d5f0]">
                    <thead class="bg-[#faf5fc]">
                        <tr>
                            <th class="text-left px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Treatment</th>
                            <th class="text-right px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-400">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-[#e8d5f0]">
                            <td class="px-4 py-3 text-sm text-gray-700" x-text="viewTarget?.cat"></td>
                            <td class="px-4 py-3 text-sm font-semibold text-gray-800 text-right" x-text="viewTarget ? '₹' + viewTarget.amount.toLocaleString('en-IN') : ''"></td>
                        </tr>
                    </tbody>
                </table>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Payment Mode</span><span class="font-medium text-gray-700" x-text="viewTarget?.mode"></span></div>
                    <div class="flex justify-between text-sm"><span class="text-gray-500">Amount Paid</span><span class="font-semibold text-green-600" x-text="viewTarget ? '+₹' + viewTarget.amount.toLocaleString('en-IN') : ''"></span></div>
                    <div class="flex justify-between text-sm border-t border-[#e8d5f0] pt-2">
                        <span class="text-gray-500">Outstanding</span>
                        <span :class="viewTarget?.outstanding > 0 ? 'text-amber-600 font-medium' : 'text-green-600'"
                            x-text="viewTarget?.outstanding > 0 ? '₹' + viewTarget.outstanding.toLocaleString('en-IN') : 'Cleared ✓'"></span>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between px-6 py-4 border-t border-[#e8d5f0] bg-[#faf5fc]">
                <p class="text-xs text-gray-400">Share with patient</p>
                <div class="flex gap-2">
                    <button @click="shareInvoice(viewTarget, 'email')"
                        class="inline-flex items-center gap-1.5 text-xs border border-gray-300 text-gray-600 px-3 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70]">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Email
                    </button>
                    <button @click="shareInvoice(viewTarget, 'whatsapp')"
                        class="inline-flex items-center gap-1.5 text-xs border border-[#25D366] text-[#25D366] px-3 py-1.5 hover:bg-[#25D366] hover:text-white">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
                        WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>


    {{-- ════════════════════════════════════════════
         MODAL: EXPORT WITH OTP
    ════════════════════════════════════════════ --}}
    <div x-show="showExport" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div @click.outside="showExport = false" class="bg-white w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0] bg-[#faf5fc]">
                <div>
                    <h2 class="text-base font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">Export Income Data</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Requires admin OTP authorisation</p>
                </div>
                <button @click="showExport = false; exportStep = 1; exportOtp = ''" class="text-gray-400 hover:text-gray-600">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div x-show="exportStep === 1">
                    <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Select Export Format</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="exportFormat = 'pdf'" :class="exportFormat === 'pdf' ? 'border-[#6a0f70] bg-[#faf5fc] text-[#6a0f70]' : 'border-gray-200 text-gray-600 hover:border-[#6a0f70]'"
                            class="border-2 p-4 flex flex-col items-center gap-2 transition-colors">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="text-sm font-medium">PDF</span><span class="text-xs text-gray-400">Printable report</span>
                        </button>
                        <button @click="exportFormat = 'excel'" :class="exportFormat === 'excel' ? 'border-[#6a0f70] bg-[#faf5fc] text-[#6a0f70]' : 'border-gray-200 text-gray-600 hover:border-[#6a0f70]'"
                            class="border-2 p-4 flex flex-col items-center gap-2 transition-colors">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            <span class="text-sm font-medium">Excel</span><span class="text-xs text-gray-400">.xlsx spreadsheet</span>
                        </button>
                    </div>
                    <div class="mt-4 px-4 py-3 bg-amber-50 border border-amber-200">
                        <p class="text-xs text-amber-700"><span class="font-semibold">Admin OTP required.</span> An OTP will be sent to the admin email.</p>
                    </div>
                </div>
                <div x-show="exportStep === 2">
                    <div class="text-center pb-2">
                        <div class="w-12 h-12 rounded-full bg-[#f3e8f4] flex items-center justify-center mx-auto mb-3">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">OTP sent to admin email</p>
                        <p class="text-xs text-gray-400 mt-1">Expires in 5 minutes</p>
                    </div>
                    <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block mt-4">Enter 6-digit OTP</label>
                    <input type="text" x-model="exportOtp" maxlength="6" placeholder="— — — — — —"
                        class="w-full text-center text-2xl font-mono tracking-[0.5em] border border-gray-300 px-3 py-3 focus:outline-none focus:border-[#6a0f70]">
                    <button @click="exportStep = 1" class="mt-3 text-xs text-[#6a0f70] hover:underline w-full text-center">← Back / Resend OTP</button>
                </div>
                <div x-show="exportStep === 3" class="text-center py-4">
                    <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-700">Export authorised!</p>
                    <p class="text-xs text-gray-400 mt-1">Your <span x-text="exportFormat.toUpperCase()"></span> is being prepared…</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-[#e8d5f0] bg-[#faf5fc]" x-show="exportStep < 3">
                <button @click="showExport = false; exportStep = 1; exportOtp = ''"
                    class="text-sm border border-gray-300 text-gray-600 px-5 py-2">Cancel</button>
                <button x-show="exportStep === 1" @click="sendExportOtp()" :disabled="!exportFormat"
                    :class="!exportFormat ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[#380740]'"
                    class="text-sm bg-[#6a0f70] text-white px-5 py-2 transition-colors">Send OTP</button>
                <button x-show="exportStep === 2" @click="verifyExportOtp()" :disabled="exportOtp.length < 6"
                    :class="exportOtp.length < 6 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-[#380740]'"
                    class="text-sm bg-[#6a0f70] text-white px-5 py-2 transition-colors">Verify & Export</button>
            </div>
        </div>
    </div>


    {{-- ════════════════════════════════════════════
         MODAL: DELETE INVOICE
    ════════════════════════════════════════════ --}}
    <div x-show="showDelete" x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
        <div @click.outside="closeDeleteModal()" class="bg-white w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-red-100 bg-red-50">
                <div>
                    <h2 class="text-base font-semibold text-red-700" style="font-family:'Cormorant Garamond',serif;">Delete Invoice</h2>
                    <p class="text-xs text-red-400 mt-0.5">Requires admin OTP authorisation</p>
                </div>
                <button @click="closeDeleteModal()" class="text-red-300 hover:text-red-500">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="px-6 py-5 space-y-4">
                <div class="px-4 py-3 bg-gray-50 border border-gray-200 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-700" x-text="deleteTarget?.patient"></p>
                        <p class="text-xs text-gray-400" x-text="deleteTarget?.id + ' · ' + deleteTarget?.cat + ' · +₹' + deleteTarget?.amount?.toLocaleString('en-IN')"></p>
                    </div>
                    <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5">Will be voided</span>
                </div>
                <div x-show="deleteStep === 1" class="space-y-3">
                    <div>
                        <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Reason <span class="text-red-400">*</span></label>
                        <select x-model="deleteReason" class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-red-400 mb-2">
                            <option value="">Select reason</option>
                            <option>Duplicate entry</option><option>Wrong patient</option><option>Wrong amount</option>
                            <option>Patient requested cancellation</option><option>Treatment not done</option><option>Other</option>
                        </select>
                        <textarea x-model="deleteNote" rows="2" placeholder="Additional notes (optional)…"
                            class="w-full text-sm border border-gray-300 px-3 py-2 focus:outline-none focus:border-red-400 resize-none"></textarea>
                    </div>
                </div>
                <div x-show="deleteStep === 2" class="space-y-3">
                    <div class="text-center">
                        <p class="text-sm font-medium text-gray-700">OTP sent to admin email · Expires in 5 min</p>
                    </div>
                    <label class="text-xs text-gray-500 uppercase tracking-widest mb-1.5 block">Enter 6-digit OTP</label>
                    <input type="text" x-model="deleteOtp" maxlength="6" placeholder="— — — — — —"
                        class="w-full text-center text-2xl font-mono tracking-[0.5em] border border-gray-300 px-3 py-3 focus:outline-none focus:border-red-400">
                    <button @click="deleteStep = 1" class="text-xs text-gray-400 hover:underline w-full text-center">← Back</button>
                </div>
            </div>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-red-100 bg-red-50">
                <button @click="closeDeleteModal()" class="text-sm border border-gray-300 text-gray-600 px-5 py-2">Cancel</button>
                <button x-show="deleteStep === 1" @click="sendDeleteOtp()" :disabled="!deleteReason"
                    :class="!deleteReason ? 'opacity-40 cursor-not-allowed' : 'hover:bg-red-700'"
                    class="text-sm bg-red-600 text-white px-5 py-2 transition-colors">Send OTP to Confirm</button>
                <button x-show="deleteStep === 2" @click="confirmDelete()" :disabled="deleteOtp.length < 6"
                    :class="deleteOtp.length < 6 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-red-700'"
                    class="text-sm bg-red-600 text-white px-5 py-2 transition-colors">Confirm Delete</button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function incomeManager() {
    return {
        activeTab: 'table',
        openMenu: null,
        showRecordPayment: false,
        showExport: false,
        showDelete: false,
        showViewInvoice: false,
        sortKey: 'date',
        sortDir: 'desc',
        filters: { from: '', to: '', category: '', mode: '', search: '' },
        exportFormat: '',
        exportStep: 1,
        exportOtp: '',
        deleteTarget: null,
        deleteStep: 1,
        deleteReason: '',
        deleteNote: '',
        deleteOtp: '',
        viewTarget: null,
        convenienceCharge: 0,

        payment: {
            patientSearch: '', patient: '', patientId: null,
            amount: '', mode: '',
            // UPI
            upiRef: '',
            // Card
            cardLast4: '', cardType: '',
            // Bank Transfer
            bankName: '', bankRef: '', bankDate: '',
            // Cheque
            chequeBankName: '', chequeNo: '', chequeDate: '',
            // Common
            category: '', doctor: '', totalBill: '', date: '', notes: ''
        },
        patientResults: [],

        allPatients: [
            { id:1, name:'Priya Sharma',   phone:'98765 43210' },
            { id:2, name:'Rahul Mehta',    phone:'91234 56789' },
            { id:3, name:'Anita Desai',    phone:'87654 32109' },
            { id:4, name:'Suresh Kumar',   phone:'76543 21098' },
            { id:5, name:'Meera Patel',    phone:'65432 10987' },
            { id:6, name:'Vijay Singh',    phone:'99887 76655' },
            { id:7, name:'Kavita Rao',     phone:'98712 34560' },
            { id:8, name:'Arun Joshi',     phone:'97654 32108' },
            { id:9, name:'Sneha Kulkarni', phone:'88776 65544' },
            { id:10, name:'Deepak Nair',   phone:'96543 21087' },
        ],

        rows: [
            { id:'INC-001', patient:'Priya Sharma',  cat:'Implant',     doc:'Dr. Sumit Firke',  mode:'UPI',          amount:45000, outstanding:0,     date:'29 May', dateSort:20260529, deleted:false, deleteReason:'' },
            { id:'INC-002', patient:'Rahul Mehta',   cat:'RCT',         doc:'Dr. Priya Mehta',  mode:'Cash',         amount:7500,  outstanding:4500,  date:'29 May', dateSort:20260529, deleted:false, deleteReason:'' },
            { id:'INC-003', patient:'Anita Desai',   cat:'Aligners',    doc:'Dr. Sumit Firke',  mode:'Card',         amount:28000, outstanding:57000, date:'28 May', dateSort:20260528, deleted:false, deleteReason:'' },
            { id:'INC-004', patient:'Suresh Kumar',  cat:'Crown',       doc:'Dr. Arjun Sharma', mode:'Bank Transfer',amount:18000, outstanding:0,     date:'28 May', dateSort:20260528, deleted:false, deleteReason:'' },
            { id:'INC-005', patient:'Meera Patel',   cat:'Consultation',doc:'Dr. Priya Mehta',  mode:'UPI',          amount:800,   outstanding:0,     date:'27 May', dateSort:20260527, deleted:false, deleteReason:'' },
            { id:'INC-006', patient:'Vijay Singh',   cat:'Membership',  doc:'—',                mode:'UPI',          amount:12000, outstanding:0,     date:'27 May', dateSort:20260527, deleted:false, deleteReason:'' },
            { id:'INC-007', patient:'Kavita Rao',    cat:'Implant',     doc:'Dr. Sumit Firke',  mode:'EMI',          amount:15000, outstanding:40000, date:'26 May', dateSort:20260526, deleted:false, deleteReason:'' },
            { id:'INC-008', patient:'Arun Joshi',    cat:'Scaling',     doc:'Dr. Sumit Firke',  mode:'Cash',         amount:2500,  outstanding:6000,  date:'26 May', dateSort:20260526, deleted:false, deleteReason:'' },
            { id:'INC-009', patient:'Sneha Kulkarni',cat:'Scaling',     doc:'Dr. Arjun Sharma', mode:'UPI',          amount:3000,  outstanding:0,     date:'30 May', dateSort:20260530, deleted:false, deleteReason:'' },
            { id:'INC-010', patient:'Deepak Nair',   cat:'Consultation',doc:'Dr. Sumit Firke',  mode:'Cash',         amount:800,   outstanding:0,     date:'31 May', dateSort:20260531, deleted:false, deleteReason:'' },
        ],

        init() {
            const now = new Date();
            const y = now.getFullYear(), m = String(now.getMonth()+1).padStart(2,'0');
            this.filters.from = `${y}-${m}-01`;
            this.filters.to   = `${y}-${m}-${String(now.getDate()).padStart(2,'0')}`;
            this.payment.date = `${y}-${m}-${String(now.getDate()).padStart(2,'0')}`;
            this.payment.bankDate   = this.payment.date;
            this.payment.chequeDate = this.payment.date;
        },

        // ── Convenience charge ──
        calcConvenience() {
            const amt = parseFloat(this.payment.amount) || 0;
            this.convenienceCharge = (this.payment.mode === 'Credit Card' && amt >= 10000)
                ? Math.round(amt * 0.025) : 0;
        },
        get totalCharged() {
            return (parseFloat(this.payment.amount) || 0) + this.convenienceCharge;
        },

        // ── Sorting ──
        setSort(key) {
            if (this.sortKey === key) this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
            else { this.sortKey = key; this.sortDir = 'asc'; }
        },
        sortIcon(key) {
            if (this.sortKey !== key) return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="opacity-30"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
            return this.sortDir === 'asc'
                ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>'
                : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>';
        },

        get filteredRows() {
            let data = [...this.rows];
            if (this.filters.category) data = data.filter(r => r.cat === this.filters.category);
            if (this.filters.mode)     data = data.filter(r => r.mode === this.filters.mode);
            if (this.filters.search)   data = data.filter(r => r.patient.toLowerCase().includes(this.filters.search.toLowerCase()));
            const key = this.sortKey, dir = this.sortDir;
            data.sort((a, b) => {
                let av = key === 'date' ? a.dateSort : (typeof a[key] === 'string' ? a[key].toLowerCase() : a[key]);
                let bv = key === 'date' ? b.dateSort : (typeof b[key] === 'string' ? b[key].toLowerCase() : b[key]);
                return av < bv ? (dir === 'asc' ? -1 : 1) : av > bv ? (dir === 'asc' ? 1 : -1) : 0;
            });
            return data;
        },

        // ── Summary: by category ──
        get summaryByCategory() {
            const map = {};
            this.rows.filter(r => !r.deleted).forEach(r => {
                if (!map[r.cat]) map[r.cat] = { category: r.cat, cases: 0, collected: 0, outstanding: 0 };
                map[r.cat].cases++;
                map[r.cat].collected += r.amount;
                map[r.cat].outstanding += r.outstanding;
            });
            return Object.values(map).sort((a,b) => b.collected - a.collected);
        },

        // ── Summary: by doctor ──
        get summaryByDoctor() {
            const map = {};
            const total = this.rows.filter(r => !r.deleted).reduce((s,r) => s + r.amount, 0);
            this.rows.filter(r => !r.deleted).forEach(r => {
                if (!map[r.doc]) map[r.doc] = { doctor: r.doc, cases: 0, collected: 0, share: 0 };
                map[r.doc].cases++;
                map[r.doc].collected += r.amount;
            });
            Object.values(map).forEach(d => d.share = total > 0 ? Math.round(d.collected / total * 100) : 0);
            return Object.values(map).sort((a,b) => b.collected - a.collected);
        },

        // ── Summary: by mode ──
        get summaryByMode() {
            const map = {};
            this.rows.filter(r => !r.deleted).forEach(r => {
                if (!map[r.mode]) map[r.mode] = { mode: r.mode, count: 0, amount: 0 };
                map[r.mode].count++;
                map[r.mode].amount += r.amount;
            });
            return Object.values(map).sort((a,b) => b.amount - a.amount);
        },

        modeColor(mode) {
            const colors = { 'UPI':'#8b5cf6','Cash':'#16a34a','Credit Card':'#3b82f6','Debit Card':'#06b6d4','Bank Transfer':'#f59e0b','Cheque':'#f97316','EMI':'#6a0f70' };
            return colors[mode] || '#888';
        },

        // ── Three-dot ──
        toggleMenu(id) { this.openMenu = this.openMenu === id ? null : id; },

        // ── View ──
        viewInvoice(row) { this.viewTarget = row; this.showViewInvoice = true; },

        // ── Amount in words (Indian system) ──
        amountInWords(n) {
            const a = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
            const b = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
            const inWords = (num) => {
                if (num === 0) return '';
                if (num < 20) return a[num] + ' ';
                if (num < 100) return b[Math.floor(num/10)] + ' ' + a[num%10] + ' ';
                if (num < 1000) return a[Math.floor(num/100)] + ' Hundred ' + inWords(num%100);
                if (num < 100000) return inWords(Math.floor(num/1000)) + 'Thousand ' + inWords(num%1000);
                if (num < 10000000) return inWords(Math.floor(num/100000)) + 'Lakh ' + inWords(num%100000);
                return inWords(Math.floor(num/10000000)) + 'Crore ' + inWords(num%10000000);
            };
            return inWords(n).trim() + ' Only';
        },

        // ── Print receipt — letterhead style, new window ──
        printReceipt(row) {
            if (!row) return;
            const now = new Date();
            const dateStr = now.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' })
                          + ' ' + now.toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit' });
            const amtWords = this.amountInWords(row.amount);
            const balance = row.outstanding > 0
                ? 'INR ' + row.outstanding.toLocaleString('en-IN') + '.00'
                : 'NIL';

            const html = `<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Receipt ${row.id}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: Arial, sans-serif; font-size:13px; color:#111; background:#fff; padding:60px 60px 40px; }
  /* Top: patient left, doctor right */
  .top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px; }
  .patient-block { font-size:13px; line-height:1.8; }
  .patient-name { font-size:14px; font-weight:bold; }
  .doctor-block { text-align:right; font-size:13px; line-height:1.8; }
  .doctor-name { font-size:14px; font-weight:bold; }
  hr { border:none; border-top:1px solid #333; margin:14px 0; }
  .receipt-title { text-align:center; font-size:15px; font-weight:bold; letter-spacing:2px; margin:20px 0 24px; text-transform:uppercase; }
  .meta { display:flex; justify-content:space-between; margin-bottom:28px; font-size:13px; }
  .body-text { font-size:13.5px; line-height:2; margin-bottom:28px; }
  .body-text strong { font-weight:bold; }
  .payment-method { font-size:13px; margin-bottom:28px; }
  .recorded { font-size:12px; color:#555; font-style:italic; margin-bottom:40px; }
  .balance { font-size:13.5px; margin-bottom:40px; }
  .sig-block { display:flex; justify-content:flex-end; }
  .sig-inner { text-align:center; min-width:180px; }
  .sig-line { border-top:1px solid #333; margin-top:50px; padding-top:6px; font-size:12px; }
  @media print { body { padding:40px 50px 30px; } }
</style></head>
<body>

  {{-- Top row: patient info left, doctor right --}}
  <div class="top">
    <div class="patient-block">
      <div class="patient-name">${row.patient}</div>
      <div>${row.doc || '—'}</div>
    </div>
    <div class="doctor-block">
      <div class="doctor-name">Dr. Sumit Firke</div>
      <div>B.D.S., M.D.S.</div>
      <div>Reg. No.: MH-12345</div>
    </div>
  </div>

  <hr>

  <div class="receipt-title">Receipt</div>

  <div class="meta">
    <div><strong>Date:</strong> ${dateStr}</div>
    <div><strong>Receipt No:</strong> ${row.id}</div>
  </div>

  <div class="body-text">
    Payment received from &nbsp;<strong>${row.patient}</strong>&nbsp; of &nbsp;
    <strong>INR ${row.amount.toLocaleString('en-IN')}/- (${amtWords})</strong>
    &nbsp; for &nbsp;<strong>${row.cat}</strong>.
  </div>

  <div class="payment-method">
    <strong>Payment method:</strong> ${row.mode}
  </div>

  <div class="recorded">
    Recorded on ${dateStr} by ${row.doc || 'Dr. Sumit Firke'}
  </div>

  <div class="balance">
    Your current balance: &nbsp;<strong>INR ${balance}</strong>
  </div>

  <hr>

  <div class="sig-block">
    <div class="sig-inner">
      <div class="sig-line">Authorised Signatory &nbsp;·&nbsp; For Dentfluence</div>
    </div>
  </div>

  <p style="font-size:11px;color:#888;margin-top:24px;">E&amp;OE. Payments made via bank/cheque are subject to clearing.</p>

<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body></html>`;
            const w = window.open('', '_blank', 'width=700,height=800');
            w.document.write(html);
            w.document.close();
        },

        // ── Share ──
        shareInvoice(row, channel) {
            if (!row) return;
            const msg = `Invoice ${row.id} | ${row.patient} | ${row.cat} | ₹${row.amount.toLocaleString('en-IN')} | ${row.date} | Dentfluence Dental Clinic`;
            if (channel === 'whatsapp') window.open(`https://wa.me/?text=${encodeURIComponent(msg)}`, '_blank');
            else window.location.href = `mailto:?subject=Invoice ${row.id} — Dentfluence&body=${encodeURIComponent(msg)}`;
        },

        // ── Delete ──
        openDeleteModal(row) { this.deleteTarget = row; this.deleteStep = 1; this.deleteReason = ''; this.deleteNote = ''; this.deleteOtp = ''; this.showDelete = true; },
        closeDeleteModal()   { this.showDelete = false; this.deleteTarget = null; this.deleteStep = 1; },
        sendDeleteOtp()      { this.deleteStep = 2; },
        confirmDelete() {
            const row = this.rows.find(r => r.id === this.deleteTarget.id);
            if (row) { row.deleted = true; row.deleteReason = this.deleteReason + (this.deleteNote ? ' — ' + this.deleteNote : ''); }
            this.closeDeleteModal();
        },

        // ── Export ──
        sendExportOtp()   { this.exportStep = 2; },
        verifyExportOtp() { this.exportStep = 3; setTimeout(() => { this.showExport = false; this.exportStep = 1; this.exportOtp = ''; }, 2000); },

        // ── Record Payment ──
        searchPatients() {
            if (this.payment.patientSearch.length < 2) { this.patientResults = []; return; }
            this.patientResults = this.allPatients.filter(p => p.name.toLowerCase().includes(this.payment.patientSearch.toLowerCase()));
        },
        selectPatient(p) { this.payment.patient = p.name; this.payment.patientId = p.id; this.payment.patientSearch = p.name; this.patientResults = []; },
        savePayment() {
            alert('Payment saved (wire to controller next)');
            this.showRecordPayment = false;
        },
    }
}
</script>
@endpush

@endsection
