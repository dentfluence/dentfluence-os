@extends('layouts.app')
@section('page-title', 'Expenses — Finance')

@section('content')
<div class="p-6 space-y-5" x-data="expenseManager()" x-init="init()">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Expenses
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Expense Management</h1>
        </div>
        <div class="flex items-center gap-2">
            <button @click="showExport = true"
                class="inline-flex items-center gap-2 border border-gray-300 text-gray-600 text-sm px-4 py-2 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export
            </button>
            <button @click="openAddModal()"
                class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Expense
            </button>
        </div>
    </div>

    {{-- ── KPI STRIP ── --}}
    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['label'=>'Today',       'val'=>'₹3,200',  'color'=>'text-red-500'],
            ['label'=>'This Week',   'val'=>'₹22,400', 'color'=>'text-red-500'],
            ['label'=>'This Month',  'val'=>'₹98,000', 'color'=>'text-red-600'],
            ['label'=>'Avg Monthly', 'val'=>'₹88,400', 'color'=>'text-amber-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $c['label'] }}</p>
            <p class="text-xl font-semibold {{ $c['color'] }}">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid gap-4" style="grid-template-columns:1fr 280px;">

        {{-- ── MAIN TABLE ── --}}
        <div class="space-y-3">

            {{-- Filters --}}
            <div class="flex gap-2 flex-wrap items-center">
                <input type="date" x-model="filters.from"
                    class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <input type="date" x-model="filters.to"
                    class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <select x-model="filters.category"
                    class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Categories</option>
                    <template x-for="cat in allCategories" :key="cat.slug">
                        <option :value="cat.slug" x-text="cat.name"></option>
                    </template>
                </select>
                <select x-model="filters.vendor"
                    class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Vendors / Staff</option>
                    <template x-for="v in allVendors" :key="v.id">
                        <option :value="v.id" x-text="v.name"></option>
                    </template>
                </select>
                <input type="text" x-model="filters.search" placeholder="Search expense…"
                    class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70] flex-1">
            </div>

            {{-- Table --}}
            <div class="bg-white border border-[#e8d5f0]" id="expenseTableWrap">
                <table class="w-full" id="expenseTable">
                    <thead>
                        <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                            <th @click="sortBy('date')"
                                class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 cursor-pointer select-none hover:text-[#6a0f70]">
                                Date
                                <span x-show="sort.col==='date'" x-text="sort.dir==='asc'?'↑':'↓'" class="ml-1 text-[#6a0f70]"></span>
                            </th>
                            <th @click="sortBy('title')"
                                class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 cursor-pointer select-none hover:text-[#6a0f70]">
                                Description
                                <span x-show="sort.col==='title'" x-text="sort.dir==='asc'?'↑':'↓'" class="ml-1 text-[#6a0f70]"></span>
                            </th>
                            <th @click="sortBy('category')"
                                class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 cursor-pointer select-none hover:text-[#6a0f70]">
                                Category
                                <span x-show="sort.col==='category'" x-text="sort.dir==='asc'?'↑':'↓'" class="ml-1 text-[#6a0f70]"></span>
                            </th>
                            <th @click="sortBy('vendor')"
                                class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 cursor-pointer select-none hover:text-[#6a0f70]">
                                Vendor / Staff
                                <span x-show="sort.col==='vendor'" x-text="sort.dir==='asc'?'↑':'↓'" class="ml-1 text-[#6a0f70]"></span>
                            </th>
                            <th @click="sortBy('mode')"
                                class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 cursor-pointer select-none hover:text-[#6a0f70]">
                                Mode
                                <span x-show="sort.col==='mode'" x-text="sort.dir==='asc'?'↑':'↓'" class="ml-1 text-[#6a0f70]"></span>
                            </th>
                            <th @click="sortBy('amount')"
                                class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400 cursor-pointer select-none hover:text-[#6a0f70]">
                                Amount
                                <span x-show="sort.col==='amount'" x-text="sort.dir==='asc'?'↑':'↓'" class="ml-1 text-[#6a0f70]"></span>
                            </th>
                            <th class="px-4 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in filteredExpenses" :key="row.id">
                            <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors">
                                <td class="px-4 py-3 text-xs text-gray-400" x-text="row.dateLabel"></td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-800" x-text="row.title"></div>
                                    <div class="flex gap-1 mt-0.5 flex-wrap">
                                        <span x-show="row.recurring"
                                              class="text-xs bg-amber-50 text-amber-600 border border-amber-200 px-1.5 py-0.5">Recurring</span>
                                        <span x-show="row.mode==='emi'"
                                              class="text-xs bg-blue-50 text-blue-600 border border-blue-200 px-1.5 py-0.5"
                                              x-text="row.emi_tenure ? 'EMI ×'+row.emi_tenure : 'EMI'"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-xs bg-red-50 text-red-600 px-2 py-0.5" x-text="row.category"></span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500" x-text="row.vendor"></td>
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium text-gray-500 uppercase" x-text="row.modeLabel"></span>
                                    <div x-show="row.reference" class="text-xs text-gray-400 mt-0.5 font-mono" x-text="row.reference"></div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-semibold text-red-500"
                                          x-text="'−₹' + row.amount.toLocaleString('en-IN')"></span>
                                    <span x-show="row.gst" class="text-xs text-amber-500 ml-1">+GST</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="relative" x-data="{open:false}">
                                        <button @click="open=!open"
                                            class="text-gray-400 hover:text-[#6a0f70] p-1">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
                                            </svg>
                                        </button>
                                        <div x-show="open" @click.outside="open=false" x-cloak
                                             class="absolute right-0 top-7 bg-white border border-[#e8d5f0] shadow-lg z-20 min-w-[130px]">
                                            <button @click="editExpense(row); open=false"
                                                class="w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-[#faf5fc] hover:text-[#6a0f70] flex items-center gap-2">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
                                                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                </svg>
                                                Edit
                                            </button>
                                            <button @click="printVoucher(row); open=false"
                                                class="w-full text-left px-4 py-2.5 text-sm text-gray-600 hover:bg-[#faf5fc] hover:text-[#6a0f70] flex items-center gap-2">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="6 9 6 2 18 2 18 9"/>
                                                    <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/>
                                                    <rect x="6" y="14" width="12" height="8"/>
                                                </svg>
                                                Print Voucher
                                            </button>
                                            <button @click="deleteExpense(row); open=false"
                                                class="w-full text-left px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 flex items-center gap-2">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"/>
                                                    <path d="M19 6l-1 14H6L5 6"/>
                                                    <path d="M10 11v6M14 11v6"/>
                                                    <path d="M9 6V4h6v2"/>
                                                </svg>
                                                Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filteredExpenses.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-400">No expenses match your filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── SIDEBAR ── --}}
        <div class="space-y-4">
            <div class="bg-white border border-[#e8d5f0] p-5">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Category Breakdown</p>
                @foreach([
                    ['cat'=>'Staff Salary',    'pct'=>42,'amt'=>'₹42,000'],
                    ['cat'=>'Lab Expenses',    'pct'=>18,'amt'=>'₹17,500'],
                    ['cat'=>'Rent',            'pct'=>15,'amt'=>'₹14,500'],
                    ['cat'=>'Dental Materials','pct'=>10,'amt'=>'₹9,800'],
                    ['cat'=>'Maintenance',     'pct'=>6, 'amt'=>'₹5,900'],
                    ['cat'=>'Utility Bills',   'pct'=>5, 'amt'=>'₹4,800'],
                    ['cat'=>'Marketing',       'pct'=>4, 'amt'=>'₹3,500'],
                ] as $c)
                <div class="mb-3">
                    <div class="flex justify-between mb-1">
                        <span class="text-xs text-gray-600">{{ $c['cat'] }}</span>
                        <span class="text-xs font-medium text-gray-700">{{ $c['amt'] }}</span>
                    </div>
                    <div class="h-1.5 bg-[#f3e8f4] rounded-full">
                        <div class="h-1.5 bg-[#6a0f70] rounded-full" style="width:{{ $c['pct'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="bg-white border border-[#e8d5f0] p-5">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Upcoming Recurring</p>
                @foreach([
                    ['title'=>'Clinic Rent',     'due'=>'01 Jun','amt'=>'₹35,000'],
                    ['title'=>'Meta Ads',         'due'=>'01 Jun','amt'=>'₹4,500'],
                    ['title'=>'Internet / WiFi',  'due'=>'05 Jun','amt'=>'₹1,200'],
                    ['title'=>'AC Annual Service','due'=>'10 Jun','amt'=>'₹3,500'],
                    ['title'=>'Software License', 'due'=>'10 Jun','amt'=>'₹2,500'],
                ] as $r)
                <div class="flex justify-between items-center py-2.5 border-b border-[#e8d5f0] last:border-0">
                    <div>
                        <div class="text-sm text-gray-700">{{ $r['title'] }}</div>
                        <div class="text-xs text-gray-400">Due {{ $r['due'] }}</div>
                    </div>
                    <span class="text-sm font-semibold text-red-500">{{ $r['amt'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         ADD / EDIT EXPENSE MODAL
    ══════════════════════════════════════════ --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false"
             class="bg-white border border-[#e8d5f0] w-[600px] max-h-[92vh] overflow-y-auto p-7">

            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;"
                    x-text="editMode ? 'Edit Expense' : 'Add Expense'"></h2>
                <button @click="showModal=false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
            </div>

            <div class="grid grid-cols-2 gap-4">

                {{-- Description --}}
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Description *</label>
                    <input type="text" x-model="form.title" placeholder="e.g. Lab work — Crown for Priya"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>

                {{-- Category --}}
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Category *</label>
                    <select x-model="form.category_slug" @change="onCategoryChange()"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="">Select category</option>
                        <template x-for="cat in allCategories" :key="cat.slug">
                            <option :value="cat.slug" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Date --}}
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Date *</label>
                    <input type="date" x-model="form.expense_date"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>

                {{-- Smart Vendor / Staff / Doctor --}}
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1"
                        x-text="vendorLabel"></label>
                    <select x-model="form.vendor_id"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="">— Select —</option>
                        <template x-for="opt in vendorOptions" :key="opt.id">
                            <option :value="opt.id" x-text="opt.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Amount --}}
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Amount (₹) *</label>
                    <input type="number" x-model="form.amount" @input="recalcEmi()" placeholder="0.00" step="0.01"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>

                {{-- ── PAYMENT INSTRUMENT — from Settings bank accounts ── --}}
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Pay Via *</label>
                    <select x-model="form.instrument_key" @change="onInstrumentChange()"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="">— Select —</option>
                        <option value="cash">💵 Cash</option>
                        <template x-for="inst in payInstruments" :key="inst.key">
                            <option :value="inst.key" x-text="inst.label"></option>
                        </template>
                        <option value="emi">📅 EMI / Instalment</option>
                    </select>
                    {{-- Show which account + mode is resolved --}}
                    <p x-show="selectedInstrument && form.instrument_key !== 'cash' && form.instrument_key !== 'emi'"
                       class="text-xs text-[#6a0f70] mt-1"
                       x-text="selectedInstrument ? selectedInstrument.accountLabel : ''"></p>
                </div>

                {{-- ── PAYMENT REFERENCE — changes based on resolved mode ── --}}

                {{-- UPI --}}
                <div class="col-span-2" x-show="resolvedMode === 'upi'">
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">UPI Transaction ID / UTR</label>
                    <input type="text" x-model="form.payment_reference" placeholder="e.g. 123456789012"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <p class="text-xs text-gray-400 mt-1" x-text="selectedInstrument?.upiHint || 'Find in your UPI app → Transaction Details'"></p>
                </div>

                {{-- Card --}}
                <div class="col-span-2" x-show="resolvedMode === 'card'">
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Approval Code / Last 4 Digits</label>
                    <input type="text" x-model="form.payment_reference" placeholder="e.g. AUTH123456 or *9900"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <p class="text-xs text-gray-400 mt-1">From payment receipt or bank SMS</p>
                </div>

                {{-- NEFT / Bank Transfer --}}
                <div class="col-span-2" x-show="resolvedMode === 'bank_transfer'">
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">UTR / Reference Number</label>
                    <input type="text" x-model="form.payment_reference" placeholder="e.g. HDFC0000XXXXXXX"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <p class="text-xs text-gray-400 mt-1">Unique Transaction Reference from NEFT / IMPS / RTGS receipt</p>
                </div>

                {{-- Cheque --}}
                <div class="col-span-2 grid grid-cols-2 gap-4" x-show="resolvedMode === 'cheque'">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Cheque Number</label>
                        <input type="text" x-model="form.payment_reference" placeholder="e.g. 001234"
                            class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Bank (auto-filled)</label>
                        <input type="text" :value="selectedInstrument?.bankName || ''" readonly
                            class="w-full border border-gray-200 bg-gray-50 text-gray-500 text-sm px-3 py-2">
                    </div>
                </div>

                {{-- ── EMI FIELDS ── --}}
                <div class="col-span-2 space-y-3" x-show="form.instrument_key === 'emi'">
                    <div class="text-xs text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2">
                        💡 Enter the <strong>total purchase value</strong> above. EMI details record your repayment schedule and trigger huddle reminders.
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Tenure (months) *</label>
                            <input type="number" x-model="form.emi_tenure" @input="recalcEmi()" min="1" max="84" placeholder="e.g. 12"
                                class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">EMI Start Date *</label>
                            <input type="date" x-model="form.emi_start_date"
                                class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Interest Rate % p.a. (0 = no-cost)</label>
                            <input type="number" x-model="form.emi_interest_rate" @input="recalcEmi()" placeholder="0" step="0.01" min="0"
                                class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Monthly EMI (auto-calculated)</label>
                            <div class="flex items-center gap-2">
                                <input type="number" x-model="form.emi_amount" step="0.01" placeholder="—"
                                    class="w-full border border-[#6a0f70] bg-[#faf5fc] text-[#380740] font-semibold text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <span class="text-xs text-gray-400 whitespace-nowrap">₹/mo</span>
                            </div>
                        </div>
                    </div>

                    {{-- Summary + auto-debit account --}}
                    <div x-show="form.emi_tenure && form.emi_amount" class="bg-[#faf5fc] border border-[#e8d5f0] px-4 py-3 space-y-2">
                        <div class="flex gap-6 flex-wrap text-xs text-gray-600">
                            <span>EMI: <strong class="text-[#6a0f70]">₹<span x-text="parseFloat(form.emi_amount||0).toLocaleString('en-IN',{maximumFractionDigits:2})"></span>/mo</strong></span>
                            <span>Tenure: <strong x-text="form.emi_tenure + ' months'"></strong></span>
                            <span>Total repayment: <strong>₹<span x-text="(parseFloat(form.emi_tenure||0)*parseFloat(form.emi_amount||0)).toLocaleString('en-IN',{maximumFractionDigits:0})"></span></strong></span>
                            <span x-show="parseFloat(form.emi_interest_rate)>0">Interest cost: <strong class="text-amber-600">₹<span x-text="Math.max(0,(parseFloat(form.emi_tenure||0)*parseFloat(form.emi_amount||0))-parseFloat(form.amount||0)).toLocaleString('en-IN',{maximumFractionDigits:0})"></span></strong></span>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Auto-debit from Account *</label>
                                <select x-model="form.emi_debit_account_id"
                                    class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white">
                                    <option value="">— Select account —</option>
                                    <template x-for="acc in bankAccounts" :key="acc.id">
                                        <option :value="acc.id" x-text="acc.shortLabel"></option>
                                    </template>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Used for daily huddle EMI reminders</p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Loan / Agreement Ref</label>
                                <input type="text" x-model="form.payment_reference" placeholder="e.g. LOAN-2024-001"
                                    class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- GST --}}
                <div class="col-span-2 flex items-center gap-3">
                    <input type="checkbox" id="gstCheck" x-model="form.gst_applicable" class="accent-[#6a0f70] w-4 h-4">
                    <label for="gstCheck" class="text-sm text-gray-600">GST Applicable</label>
                    <select x-show="form.gst_applicable" x-model="form.gst_rate"
                        class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="5">5%</option>
                        <option value="12">12%</option>
                        <option value="18">18%</option>
                        <option value="28">28%</option>
                    </select>
                </div>

                {{-- Recurring --}}
                <div class="col-span-2 flex items-center gap-3">
                    <input type="checkbox" id="recurringCheck" x-model="form.is_recurring" class="accent-[#6a0f70] w-4 h-4">
                    <label for="recurringCheck" class="text-sm text-gray-600">Recurring</label>
                    <select x-show="form.is_recurring" x-model="form.recurring_period"
                        class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>

                {{-- Notes --}}
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Notes</label>
                    <textarea x-model="form.notes" rows="2"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                </div>

                <div class="col-span-2 flex gap-3 justify-end mt-2">
                    <button @click="showModal=false"
                        class="text-sm border border-gray-300 text-gray-600 px-5 py-2 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                        Cancel
                    </button>
                    <button @click="saveExpense()"
                        class="text-sm bg-[#6a0f70] text-white px-5 py-2 hover:bg-[#380740] transition-colors"
                        x-text="editMode ? 'Update Expense' : 'Save Expense'"></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         EXPORT MODAL
    ══════════════════════════════════════════ --}}
    <div x-show="showExport" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div @click.outside="showExport=false" class="bg-white border border-[#e8d5f0] w-[440px] p-7">
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-lg font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">Export Expenses</h2>
                <button @click="showExport=false" class="text-gray-400 hover:text-gray-600 text-xl">×</button>
            </div>
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">From</label>
                    <input type="date" x-model="exportForm.from"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">To</label>
                    <input type="date" x-model="exportForm.to"
                        class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-xs text-gray-500 uppercase tracking-widest mb-2">Format</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                        <input type="radio" x-model="exportForm.format" value="pdf" class="accent-[#6a0f70]"> PDF
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-600">
                        <input type="radio" x-model="exportForm.format" value="excel" class="accent-[#6a0f70]"> Excel
                    </label>
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-xs text-gray-500 uppercase tracking-widest mb-1">Admin PIN *</label>
                <input type="password" x-model="exportForm.pin" placeholder="Enter admin PIN to authorise export"
                    class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <p class="text-xs text-gray-400 mt-1">Required to download financial records</p>
                <p x-show="exportForm.error" class="text-xs text-red-500 mt-1" x-text="exportForm.error"></p>
            </div>
            <div class="flex gap-3 justify-end">
                <button @click="showExport=false"
                    class="text-sm border border-gray-300 text-gray-600 px-5 py-2 hover:border-[#6a0f70] transition-colors">
                    Cancel
                </button>
                <button @click="doExport()"
                    class="text-sm bg-[#6a0f70] text-white px-5 py-2 hover:bg-[#380740] transition-colors">
                    Export
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════
         DELETE CONFIRM
    ══════════════════════════════════════════ --}}
    <div x-show="showDeleteConfirm" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
        <div @click.outside="showDeleteConfirm=false" class="bg-white border border-[#e8d5f0] w-[380px] p-7">
            <h2 class="text-lg font-semibold text-red-600 mb-3">Delete Expense?</h2>
            <p class="text-sm text-gray-600 mb-5">
                Permanently remove <strong x-text="deleteTarget?.title"></strong>. This cannot be undone.
            </p>
            <div class="flex gap-3 justify-end">
                <button @click="showDeleteConfirm=false"
                    class="text-sm border border-gray-300 text-gray-600 px-5 py-2 hover:border-[#6a0f70] transition-colors">
                    Cancel
                </button>
                <button @click="confirmDelete()"
                    class="text-sm bg-red-500 text-white px-5 py-2 hover:bg-red-600 transition-colors">
                    Delete
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function expenseManager() {
    return {
        showModal:         false,
        showExport:        false,
        showDeleteConfirm: false,
        editMode:          false,
        deleteTarget:      null,

        sort: { col: 'date', dir: 'desc' },

        filters: { from: '', to: '', category: '', vendor: '', search: '' },

        exportForm: { from: '', to: '', format: 'pdf', pin: '', error: '' },

        form: {
            id: null, title: '', category_slug: '', expense_date: '',
            vendor_id: '', amount: '',
            // Payment instrument — encodes account + mode together
            instrument_key:      'cash',   // e.g. 'cash', '1_neft', '1_upi', '2_cheque', 'emi'
            payment_reference:   '',
            // EMI specific
            emi_tenure:          '',
            emi_start_date:      '',
            emi_amount:          '',       // auto-calculated
            emi_interest_rate:   '0',
            emi_debit_account_id:'',
            gst_applicable: false, gst_rate: 18,
            is_recurring: false, recurring_period: 'monthly', notes: ''
        },

        // ── Bank accounts (loaded from finance_bank_accounts via settings) ──
        // Each account declares which payment modes it supports.
        bankAccounts: [
            {
                id: 1, name: 'HDFC Current Account', bank: 'HDFC Bank',
                accNo: '****4521', type: 'current', upiId: 'clinic@hdfcbank',
                shortLabel: 'HDFC Current ****4521',
                // modes this account supports
                modes: ['bank_transfer','upi','cheque','card'],
            },
            {
                id: 2, name: 'SBI Savings Account', bank: 'State Bank of India',
                accNo: '****8832', type: 'savings', upiId: 'clinic@sbi',
                shortLabel: 'SBI Savings ****8832',
                modes: ['bank_transfer','upi','cheque'],
            },
            {
                id: 3, name: 'ICICI OD Account', bank: 'ICICI Bank',
                accNo: '****1190', type: 'od', upiId: null,
                shortLabel: 'ICICI OD ****1190',
                modes: ['bank_transfer','cheque'],
            },
            {
                id: 4, name: 'HDFC Credit Card', bank: 'HDFC Bank',
                accNo: '****9900', type: 'cc', upiId: null,
                shortLabel: 'HDFC Credit Card ****9900',
                modes: ['card'],
            },
        ],

        // ── Computed: flat list of selectable instruments from all accounts ──
        get payInstruments() {
            const modeLabels = {
                bank_transfer: 'NEFT / Bank Transfer',
                upi:           'UPI',
                cheque:        'Cheque',
                card:          'Debit Card',
            };
            const result = [];
            this.bankAccounts.forEach(acc => {
                acc.modes.forEach(mode => {
                    let label = `${acc.bank} ${acc.accNo}`;
                    if (mode === 'upi')  label += ` — UPI (${acc.upiId})`;
                    else if (mode === 'card' && acc.type === 'cc') label = `${acc.bank} ${acc.accNo} — Credit Card`;
                    else label += ` — ${modeLabels[mode] || mode}`;
                    result.push({
                        key:         `${acc.id}_${mode}`,
                        label:       label,
                        accountId:   acc.id,
                        mode:        mode,
                        bankName:    acc.bank,
                        accountLabel:`${acc.name} (${acc.accNo})`,
                        upiHint:     acc.upiId ? `UPI ID: ${acc.upiId} — check your UPI app for UTR` : 'Find UTR in your UPI app',
                    });
                });
            });
            return result;
        },

        // ── Resolved mode from current instrument_key ──
        get resolvedMode() {
            if (!this.form.instrument_key || this.form.instrument_key === 'cash') return 'cash';
            if (this.form.instrument_key === 'emi') return 'emi';
            const inst = this.payInstruments.find(i => i.key === this.form.instrument_key);
            return inst ? inst.mode : 'cash';
        },

        // ── Selected instrument detail object ──
        get selectedInstrument() {
            return this.payInstruments.find(i => i.key === this.form.instrument_key) || null;
        },

        allCategories: [
            { slug:'rent',             name:'Rent',               type:'vendor' },
            { slug:'maintenance',      name:'Maintenance',        type:'vendor' },
            { slug:'electricity',      name:'Electricity',        type:'vendor' },
            { slug:'internet',         name:'Internet / WiFi',    type:'vendor' },
            { slug:'mobile_bills',     name:'Mobile Bills',       type:'vendor' },
            { slug:'water',            name:'Water',              type:'vendor' },
            { slug:'staff_salary',     name:'Staff Salary',       type:'staff'  },
            { slug:'consultant_fees',  name:'Consultant Fees',    type:'doctor' },
            { slug:'petty_cash',       name:'Petty Cash',         type:''       },
            { slug:'refreshments',     name:'Refreshments',       type:''       },
            { slug:'marketing',        name:'Marketing',          type:'vendor' },
            { slug:'dental_materials', name:'Dental Materials',   type:'vendor' },
            { slug:'lab_expenses',     name:'Lab Expenses',       type:'lab'    },
            { slug:'equipment',        name:'Equipment',          type:'vendor' },
            { slug:'professional_fees',name:'Professional Fees',  type:'vendor' },
            { slug:'insurance',        name:'Insurance',          type:'vendor' },
            { slug:'cde_education',    name:'CDE / Education',    type:'vendor' },
            { slug:'office_supplies',  name:'Office Supplies',    type:'vendor' },
            { slug:'travel',           name:'Travel',             type:''       },
            { slug:'miscellaneous',    name:'Miscellaneous',      type:''       },
        ],

        allVendors: [
            { id:'v1', name:'Sharma Property',        type:'vendor' },
            { id:'v2', name:'CoolAir Services',        type:'vendor' },
            { id:'v3', name:'Prime Dental Supply',     type:'vendor' },
            { id:'v4', name:'Osstem India',            type:'vendor' },
            { id:'v5', name:'CA Ramesh Joshi',         type:'vendor' },
            { id:'l1', name:'City Dental Lab',         type:'lab'    },
            { id:'l2', name:'Digital Dentistry Works', type:'lab'    },
            { id:'s1', name:'Samiksha',                type:'staff'  },
            { id:'s2', name:'Runali',                  type:'staff'  },
            { id:'s3', name:'Ankita',                  type:'staff'  },
            { id:'s4', name:'Ashwini',                 type:'staff'  },
            { id:'s5', name:'Dr. Nirmita',             type:'staff'  },
            { id:'s6', name:'Dr. Sumit',               type:'staff'  },
            { id:'s7', name:'Dr. Sayli',               type:'staff'  },
            { id:'d1', name:'Dr. Devendra',            type:'doctor' },
            { id:'d2', name:'Dr. Niraj',               type:'doctor' },
        ],

        expenses: [
            // Each row carries instrument_key, gst_rate, notes, emi fields so edit can fully restore.
            { id:1,  date:'2026-05-29', dateLabel:'29 May', title:'Lab Work — Crown for Patient Priya',  category:'Lab Expenses',    category_slug:'lab_expenses',     vendor:'City Dental Lab',         vendor_id:'l1', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:8500,   gst:false, gst_rate:18, recurring:false, recurring_period:'monthly', reference:'UTR2024051234',  emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'Crown work for patient Priya' },
            { id:2,  date:'2026-05-28', dateLabel:'28 May', title:'Meta Ads — May Campaign',             category:'Marketing',        category_slug:'marketing',         vendor:'—',                       vendor_id:'',   bank_account_id:4, mode:'card',          modeLabel:'Credit Card', amount:4500,   gst:true,  gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'AUTH9876',        emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'4_card',           notes:'' },
            { id:3,  date:'2026-05-28', dateLabel:'28 May', title:'Dental Materials — May Stock',        category:'Dental Materials', category_slug:'dental_materials',  vendor:'Prime Dental Supply',     vendor_id:'v3', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:6800,   gst:true,  gst_rate:18, recurring:false, recurring_period:'monthly', reference:'UTR2024051100',  emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'' },
            { id:4,  date:'2026-05-27', dateLabel:'27 May', title:'Electricity Bill — May',              category:'Electricity',      category_slug:'electricity',       vendor:'MSEB',                    vendor_id:'',   bank_account_id:1, mode:'upi',           modeLabel:'HDFC UPI',    amount:3200,   gst:false, gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'TXN20240527001', emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_upi',           notes:'May electricity bill' },
            { id:5,  date:'2026-05-27', dateLabel:'27 May', title:'Internet / WiFi — May',               category:'Internet / WiFi',  category_slug:'internet',          vendor:'Jio Fiber',               vendor_id:'',   bank_account_id:2, mode:'upi',           modeLabel:'SBI UPI',     amount:1200,   gst:true,  gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'TXN20240527002', emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'2_upi',           notes:'' },
            { id:6,  date:'2026-05-26', dateLabel:'26 May', title:'AC Service — Annual AMC',             category:'Maintenance',      category_slug:'maintenance',       vendor:'CoolAir Services',        vendor_id:'v2', bank_account_id:1, mode:'cheque',        modeLabel:'HDFC Cheque', amount:3500,   gst:false, gst_rate:18, recurring:true,  recurring_period:'yearly',  reference:'CHQ-004521',     emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_cheque',        notes:'Annual AMC for 3 AC units' },
            { id:7,  date:'2026-05-25', dateLabel:'25 May', title:'CA Fees — Q1 Filing',                 category:'Professional Fees',category_slug:'professional_fees', vendor:'CA Ramesh Joshi',         vendor_id:'v5', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:8000,   gst:false, gst_rate:18, recurring:false, recurring_period:'quarterly',reference:'UTR2024052500',  emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'Q1 GST + ITR filing' },
            { id:8,  date:'2026-05-24', dateLabel:'24 May', title:'Salary — Samiksha',                   category:'Staff Salary',     category_slug:'staff_salary',      vendor:'Samiksha',                vendor_id:'s1', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:18000,  gst:false, gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'SAL-MAY-S1',     emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'May 2026 salary' },
            { id:9,  date:'2026-05-24', dateLabel:'24 May', title:'Salary — Runali',                     category:'Staff Salary',     category_slug:'staff_salary',      vendor:'Runali',                  vendor_id:'s2', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:16000,  gst:false, gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'SAL-MAY-S2',     emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'May 2026 salary' },
            { id:10, date:'2026-05-24', dateLabel:'24 May', title:'Salary — Ankita',                     category:'Staff Salary',     category_slug:'staff_salary',      vendor:'Ankita',                  vendor_id:'s3', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:15000,  gst:false, gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'SAL-MAY-S3',     emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'May 2026 salary' },
            { id:11, date:'2026-05-24', dateLabel:'24 May', title:'Consultant Fee — Dr. Devendra',       category:'Consultant Fees',  category_slug:'consultant_fees',   vendor:'Dr. Devendra',            vendor_id:'d1', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:12000,  gst:false, gst_rate:18, recurring:false, recurring_period:'monthly', reference:'CONS-MAY-D1',    emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'Visiting consultant — 8 sessions' },
            { id:12, date:'2026-05-20', dateLabel:'20 May', title:'Implant Kit — Osstem (EMI 1/12)',     category:'Equipment',        category_slug:'equipment',         vendor:'Osstem India',            vendor_id:'v4', bank_account_id:1, mode:'emi',           modeLabel:'EMI',         amount:120000, gst:true,  gst_rate:18, recurring:false, recurring_period:'monthly', reference:'LOAN-OST-2024',  emi_tenure:12, emi_amount:'10000.00', emi_interest_rate:'0', emi_start_date:'2026-06-01', emi_debit_account_id:1, instrument_key:'emi', notes:'No-cost EMI via HDFC — 12 months' },
            { id:13, date:'2026-05-15', dateLabel:'15 May', title:'Digital Lab Work — Zirconia Bridge',  category:'Lab Expenses',     category_slug:'lab_expenses',      vendor:'Digital Dentistry Works', vendor_id:'l2', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:9200,   gst:false, gst_rate:18, recurring:false, recurring_period:'monthly', reference:'UTR2024051500',  emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'Patient Mehta — full-arch zirconia' },
            { id:14, date:'2026-05-10', dateLabel:'10 May', title:'Consultant Fee — Dr. Niraj',          category:'Consultant Fees',  category_slug:'consultant_fees',   vendor:'Dr. Niraj',               vendor_id:'d2', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:10000,  gst:false, gst_rate:18, recurring:false, recurring_period:'monthly', reference:'CONS-MAY-D2',    emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'Oral surgery — 4 cases' },
            { id:15, date:'2026-05-05', dateLabel:'05 May', title:'Clinic Insurance — Annual Premium',   category:'Insurance',        category_slug:'insurance',         vendor:'LIC / Star Health',       vendor_id:'',   bank_account_id:1, mode:'cheque',        modeLabel:'HDFC Cheque', amount:24000,  gst:false, gst_rate:18, recurring:true,  recurring_period:'yearly',  reference:'CHQ-004400',     emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_cheque',        notes:'Annual clinic + equipment insurance' },
            { id:16, date:'2026-05-01', dateLabel:'01 May', title:'Clinic Rent — May',                   category:'Rent',             category_slug:'rent',              vendor:'Sharma Property',         vendor_id:'v1', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:35000,  gst:false, gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'UTR2024050100',  emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'May 2026 clinic rent' },
            { id:17, date:'2026-05-01', dateLabel:'01 May', title:'Petty Cash — Refreshments / Misc',    category:'Petty Cash',       category_slug:'petty_cash',        vendor:'—',                       vendor_id:'',   bank_account_id:null, mode:'cash',        modeLabel:'Cash',        amount:2000,   gst:false, gst_rate:18, recurring:false, recurring_period:'monthly', reference:'',               emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'cash',            notes:'Tea, snacks, misc office supplies' },
            { id:18, date:'2026-05-01', dateLabel:'01 May', title:'Salary — Ashwini',                    category:'Staff Salary',     category_slug:'staff_salary',      vendor:'Ashwini',                 vendor_id:'s4', bank_account_id:1, mode:'bank_transfer', modeLabel:'HDFC NEFT',   amount:14000,  gst:false, gst_rate:18, recurring:true,  recurring_period:'monthly', reference:'SAL-MAY-S4',     emi_tenure:0, emi_amount:'', emi_interest_rate:'0', emi_start_date:'', emi_debit_account_id:'', instrument_key:'1_bank_transfer', notes:'May 2026 salary' },
        ],

        get vendorLabel() {
            const cat = this.allCategories.find(c => c.slug === this.form.category_slug);
            if (!cat) return 'Vendor / Staff / Doctor (Optional)';
            if (cat.type === 'staff')  return 'Select Staff Member *';
            if (cat.type === 'doctor') return 'Select Consultant / Doctor *';
            if (cat.type === 'lab')    return 'Select Lab *';
            if (cat.type === 'vendor') return 'Select Vendor *';
            return 'Vendor / Person (Optional)';
        },

        get vendorOptions() {
            const cat = this.allCategories.find(c => c.slug === this.form.category_slug);
            if (!cat || !cat.type) return this.allVendors;
            return this.allVendors.filter(v => v.type === cat.type);
        },

        get filteredExpenses() {
            let rows = [...this.expenses];

            if (this.filters.from)
                rows = rows.filter(r => r.date >= this.filters.from);
            if (this.filters.to)
                rows = rows.filter(r => r.date <= this.filters.to);
            if (this.filters.category)
                rows = rows.filter(r => r.category_slug === this.filters.category);
            if (this.filters.vendor)
                rows = rows.filter(r => r.vendor_id === this.filters.vendor);
            if (this.filters.search) {
                const q = this.filters.search.toLowerCase();
                rows = rows.filter(r =>
                    r.title.toLowerCase().includes(q) ||
                    r.vendor.toLowerCase().includes(q) ||
                    r.category.toLowerCase().includes(q)
                );
            }

            const { col, dir } = this.sort;
            rows.sort((a, b) => {
                let av = a[col] ?? '', bv = b[col] ?? '';
                if (col === 'date')     { av = a.date;     bv = b.date;     }
                if (col === 'title')    { av = a.title;    bv = b.title;    }
                if (col === 'category') { av = a.category; bv = b.category; }
                if (col === 'vendor')   { av = a.vendor;   bv = b.vendor;   }
                if (col === 'mode')     { av = a.mode;     bv = b.mode;     }
                if (col === 'amount')   { av = a.amount;   bv = b.amount;   }
                if (av < bv) return dir === 'asc' ? -1 : 1;
                if (av > bv) return dir === 'asc' ?  1 : -1;
                return 0;
            });
            return rows;
        },

        init() {
            const today = new Date();
            const y = today.getFullYear();
            const m = String(today.getMonth() + 1).padStart(2, '0');
            this.exportForm.from = `${y}-${m}-01`;
            this.exportForm.to   = today.toISOString().slice(0, 10);
            // Default form date
            this.form.expense_date = today.toISOString().slice(0, 10);
        },

        sortBy(col) {
            if (this.sort.col === col) {
                this.sort.dir = this.sort.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.sort.col = col;
                this.sort.dir = col === 'amount' ? 'desc' : 'asc';
            }
        },

        openAddModal() {
            this.editMode = false;
            const today = new Date().toISOString().slice(0, 10);
            this.form = {
                id: null, title: '', category_slug: '', expense_date: today,
                vendor_id: '', amount: '', instrument_key: 'cash',
                payment_reference: '',
                emi_tenure: '', emi_start_date: '', emi_amount: '', emi_interest_rate: '0',
                emi_debit_account_id: '',
                gst_applicable: false, gst_rate: 18,
                is_recurring: false, recurring_period: 'monthly', notes: ''
            };
            this.showModal = true;
        },

        editExpense(row) {
            this.editMode = true;
            this.form = {
                id:                   row.id,
                title:                row.title,
                category_slug:        row.category_slug,
                expense_date:         row.date,
                vendor_id:            row.vendor_id,
                amount:               row.amount,
                // instrument_key is now stored directly on each row
                instrument_key:       row.instrument_key || 'cash',
                payment_reference:    row.reference     || '',
                emi_tenure:           row.emi_tenure     || '',
                emi_start_date:       row.emi_start_date || '',
                emi_amount:           row.emi_amount     || '',
                emi_interest_rate:    row.emi_interest_rate ?? '0',
                emi_debit_account_id: row.emi_debit_account_id || '',
                gst_applicable:       row.gst            ?? false,
                gst_rate:             row.gst_rate       || 18,
                is_recurring:         row.recurring      ?? false,
                recurring_period:     row.recurring_period || 'monthly',
                notes:                row.notes          || '',
            };
            this.showModal = true;
        },

        onCategoryChange() {
            this.form.vendor_id = '';
        },

        onInstrumentChange() {
            this.form.payment_reference = '';
            // Auto-fill first account for EMI debit suggestion
            if (this.form.instrument_key === 'emi' && !this.form.emi_debit_account_id && this.bankAccounts.length) {
                this.form.emi_debit_account_id = this.bankAccounts[0].id;
            }
        },

        // ── EMI auto-calculation: standard reducing-balance formula ──
        // EMI = P × r × (1+r)^n / ((1+r)^n − 1)   [r = monthly rate]
        // Zero-interest: EMI = P / n
        recalcEmi() {
            const P = parseFloat(this.form.amount)         || 0;
            const n = parseInt(this.form.emi_tenure)       || 0;
            const r = parseFloat(this.form.emi_interest_rate) || 0;
            if (P <= 0 || n <= 0) { this.form.emi_amount = ''; return; }
            if (r === 0) {
                this.form.emi_amount = (P / n).toFixed(2);
            } else {
                const monthlyRate = r / 12 / 100;
                const factor = Math.pow(1 + monthlyRate, n);
                const emi = (P * monthlyRate * factor) / (factor - 1);
                this.form.emi_amount = emi.toFixed(2);
            }
        },

        saveExpense() {
            if (!this.form.title || !this.form.amount) {
                alert('Description and Amount are required.');
                return;
            }
            const catObj = this.allCategories.find(c => c.slug === this.form.category_slug);
            const venObj = this.allVendors.find(v => v.id === this.form.vendor_id);

            // Resolve mode + account from instrument_key
            const mode = this.resolvedMode;
            const inst = this.selectedInstrument;
            const modeDisplayMap = {
                cash:'Cash', upi:'UPI', card:'Card',
                bank_transfer:'NEFT', cheque:'Cheque', emi:'EMI',
            };
            let modeLabel = modeDisplayMap[mode] || mode;
            if (inst) {
                // Prefix with bank short name for display
                if (mode === 'bank_transfer') modeLabel = `${inst.bankName} NEFT`;
                else if (mode === 'upi')      modeLabel = `${inst.bankName} UPI`;
                else if (mode === 'cheque')   modeLabel = `${inst.bankName} Cheque`;
                else if (mode === 'card' && inst.accountLabel?.includes('Credit')) modeLabel = 'Credit Card';
                else if (mode === 'card')     modeLabel = `${inst.bankName} Card`;
            }

            const payload = {
                title:                this.form.title,
                category_slug:        this.form.category_slug,
                category:             catObj ? catObj.name : this.form.category_slug,
                date:                 this.form.expense_date,
                dateLabel:            this.formatDateLabel(this.form.expense_date),
                vendor_id:            this.form.vendor_id,
                vendor:               venObj ? venObj.name : '—',
                bank_account_id:      inst ? inst.accountId : null,
                instrument_key:       this.form.instrument_key,
                mode:                 mode,
                modeLabel:            modeLabel,
                amount:               parseFloat(this.form.amount),
                gst:                  this.form.gst_applicable,
                gst_rate:             this.form.gst_rate,
                recurring:            this.form.is_recurring,
                recurring_period:     this.form.recurring_period,
                reference:            this.form.payment_reference,
                notes:                this.form.notes,
                emi_tenure:           parseInt(this.form.emi_tenure)       || 0,
                emi_amount:           this.form.emi_amount                 || '',
                emi_interest_rate:    this.form.emi_interest_rate          || '0',
                emi_start_date:       this.form.emi_start_date             || '',
                emi_debit_account_id: this.form.emi_debit_account_id      || '',
            };
            if (this.editMode) {
                const idx = this.expenses.findIndex(e => e.id === this.form.id);
                if (idx > -1) Object.assign(this.expenses[idx], payload);
            } else {
                const newId = Math.max(0, ...this.expenses.map(e => e.id)) + 1;
                this.expenses.unshift({ id: newId, ...payload });
            }
            this.showModal = false;
        },

        deleteExpense(row) {
            this.deleteTarget      = row;
            this.showDeleteConfirm = true;
        },

        confirmDelete() {
            this.expenses          = this.expenses.filter(e => e.id !== this.deleteTarget.id);
            this.showDeleteConfirm = false;
            this.deleteTarget      = null;
        },

        doExport() {
            // Demo PIN = 1234. Replace with real server-side auth when connecting to backend.
            if (this.exportForm.pin !== '1234') {
                this.exportForm.error = 'Incorrect PIN. Please try again.';
                return;
            }
            this.exportForm.error = '';
            // TODO: wire to /finance/expenses/export?from=...&to=...&format=...
            alert(`Exporting expenses (${this.exportForm.from} → ${this.exportForm.to}) as ${this.exportForm.format.toUpperCase()}.\n\nConnect to: /finance/expenses/export`);
            this.showExport = false;
        },

        // ── Print a single expense as a payment voucher ──
        printVoucher(row) {
            const acc = row.bank_account_id
                ? this.bankAccounts.find(a => a.id === row.bank_account_id)
                : null;

            const emiBlock = row.mode === 'emi' ? `
                <tr><td class="lbl">Tenure</td><td>${row.emi_tenure} months</td></tr>
                <tr><td class="lbl">Monthly EMI</td><td>₹${parseFloat(row.emi_amount||0).toLocaleString('en-IN',{minimumFractionDigits:2})}</td></tr>
                <tr><td class="lbl">EMI Start</td><td>${row.emi_start_date || '—'}</td></tr>
                <tr><td class="lbl">Interest Rate</td><td>${row.emi_interest_rate || '0'}% p.a.</td></tr>
                <tr><td class="lbl">Total Repayment</td><td>₹${(parseFloat(row.emi_tenure||0)*parseFloat(row.emi_amount||0)).toLocaleString('en-IN',{maximumFractionDigits:0})}</td></tr>
            ` : '';

            const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Expense Voucher #${row.id}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size:13px; color:#222; padding:32px; }
  .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; border-bottom:2px solid #6a0f70; padding-bottom:16px; }
  .clinic-name { font-size:22px; font-weight:700; color:#380740; font-family:Georgia,serif; }
  .clinic-sub  { font-size:11px; color:#888; margin-top:2px; }
  .voucher-label { text-align:right; }
  .voucher-label h2 { font-size:18px; color:#6a0f70; font-weight:700; letter-spacing:1px; }
  .voucher-label p  { font-size:11px; color:#666; margin-top:3px; }
  table { width:100%; border-collapse:collapse; margin-bottom:20px; }
  td { padding:8px 12px; vertical-align:top; }
  td.lbl { width:42%; font-weight:600; color:#555; font-size:11px; text-transform:uppercase; letter-spacing:.5px; background:#f9f3fc; border-bottom:1px solid #ede; }
  td:not(.lbl) { border-bottom:1px solid #ede; color:#222; }
  .amount-row td { font-size:17px; font-weight:700; color:#c0392b; background:#fff8f8; border-top:2px solid #6a0f70; }
  .amount-row td.lbl { font-size:13px; color:#6a0f70; background:#f3e8f4; }
  .section-title { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#6a0f70; padding:6px 12px; background:#f3e8f4; border-left:3px solid #6a0f70; margin-top:12px; margin-bottom:0; }
  .footer { margin-top:36px; display:flex; justify-content:space-between; font-size:11px; color:#888; }
  .sign-box { border-top:1px solid #bbb; padding-top:6px; min-width:160px; text-align:center; }
  .gst-badge { display:inline-block; background:#fff3cd; color:#856404; font-size:10px; padding:2px 7px; border:1px solid #ffc107; border-radius:3px; margin-left:6px; }
</style>
</head>
<body>
<div class="header">
  <div>
    <div class="clinic-name">Dentfluence Dental Clinic</div>
    <div class="clinic-sub">Expense Payment Voucher</div>
  </div>
  <div class="voucher-label">
    <h2>VOUCHER #EXP-${String(row.id).padStart(4,'0')}</h2>
    <p>Date: ${row.dateLabel || row.date}</p>
    <p>Printed: ${new Date().toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'})}</p>
  </div>
</div>

<div class="section-title">Expense Details</div>
<table>
  <tr><td class="lbl">Description</td><td><strong>${row.title}</strong></td></tr>
  <tr><td class="lbl">Category</td><td>${row.category || '—'}</td></tr>
  <tr><td class="lbl">Paid To</td><td>${row.vendor || '—'}</td></tr>
  <tr><td class="lbl">Expense Date</td><td>${row.date}</td></tr>
  ${row.notes ? `<tr><td class="lbl">Notes</td><td>${row.notes}</td></tr>` : ''}
</table>

<div class="section-title">Payment Details</div>
<table>
  <tr><td class="lbl">Payment Mode</td><td>${row.modeLabel || row.mode}${acc ? ' — ' + acc.name : ''}</td></tr>
  ${acc && acc.accNo ? `<tr><td class="lbl">Account</td><td>${acc.bank} ${acc.accNo}</td></tr>` : ''}
  ${row.reference   ? `<tr><td class="lbl">Reference / UTR</td><td><strong>${row.reference}</strong></td></tr>` : ''}
  ${emiBlock}
  <tr class="amount-row">
    <td class="lbl">Total Amount${row.gst ? ' <span class="gst-badge">+GST</span>' : ''}</td>
    <td>₹${parseFloat(row.amount).toLocaleString('en-IN',{minimumFractionDigits:2})}</td>
  </tr>
</table>

<div class="footer">
  <div class="sign-box">Prepared By</div>
  <div class="sign-box">Approved By</div>
  <div class="sign-box">Received By</div>
</div>
<p style="margin-top:18px;font-size:10px;color:#bbb;text-align:center;">This is a computer-generated expense voucher — Dentfluence v1.0</p>
</body>
</html>`;

            const win = window.open('', '_blank', 'width=750,height=900');
            win.document.write(html);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 400);
        },

        formatDateLabel(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr + 'T00:00:00');
            return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' });
        },
    };
}
</script>


@endsection
