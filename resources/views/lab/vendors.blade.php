@extends('layouts.app')
@section('page-title', 'Lab Vendors')

@section('content')
<div x-data="labVendors()" class="p-6 space-y-5">

    {{-- ══ NAV TABS ═══════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-2 border-b border-gray-200 pb-0">
        <a href="{{ route('lab.dashboard') }}"
           class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-brand-700 border-b-2 border-transparent -mb-px transition">
            Dashboard
        </a>
        <a href="{{ route('lab.index') }}"
           class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-brand-700 border-b-2 border-transparent -mb-px transition">
            Lab Cases
        </a>
        <a href="{{ route('lab-vendors.index') }}"
           class="px-4 py-2 text-sm font-medium text-brand-700 border-b-2 border-brand-600 -mb-px">
            Lab Vendors
        </a>
    </div>

    {{-- ══ HEADER ══════════════════════════════════════════════════════════ --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-700">Lab Vendors</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage dental labs, contacts, services &amp; rates</p>
        </div>
        <button @click="openAdd()"
                dusk="labvendor-add"
                class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Lab
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- ══ VENDOR CARDS GRID ═══════════════════════════════════════════════ --}}
    @forelse($vendors as $v)
    @php $v = (object) $v; @endphp
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
         x-data="{ expanded: false }">

        {{-- Card header --}}
        <div class="flex items-start gap-4 p-5">
            {{-- Avatar --}}
            <div class="w-11 h-11 rounded-xl bg-brand-100 flex items-center justify-center shrink-0 text-brand-700 font-bold text-lg">
                {{ strtoupper(substr($v->name, 0, 1)) }}
            </div>

            {{-- Core info --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h3 class="font-semibold text-gray-900">{{ $v->name }}</h3>
                    @if($v->is_active)
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-green-100 text-green-700 rounded-full">ACTIVE</span>
                    @else
                        <span class="px-2 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full">INACTIVE</span>
                    @endif
                    @if($v->payment_terms === 'monthly_account')
                        <span class="px-2 py-0.5 text-[10px] font-semibold bg-purple-100 text-purple-700 rounded-full">Monthly Account</span>
                    @endif
                </div>

                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
                    @if($v->contact_person)
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $v->contact_person }}
                    </span>
                    @endif
                    @if($v->phone)
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        {{ $v->phone }}
                    </span>
                    @endif
                    @if($v->email)
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        {{ $v->email }}
                    </span>
                    @endif
                    @if($v->address)
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ Str::limit($v->address, 50) }}
                    </span>
                    @endif
                </div>

                {{-- KPI strip --}}
                <div class="mt-2 flex gap-4 flex-wrap items-end">
                    <div class="text-center">
                        <div class="text-sm font-bold text-gray-700">{{ $v->total_cases }}</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Cases</div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm font-bold text-gray-700">Rs.{{ number_format($v->total_spend, 0) }}</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Total Spend</div>
                    </div>
                    @if($v->avg_turnaround)
                    <div class="text-center">
                        <div class="text-sm font-bold {{ $v->avg_turnaround > ($v->default_turnaround_days ?? 7) ? 'text-red-600' : 'text-green-600' }}">{{ $v->avg_turnaround }}d</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Avg Turn</div>
                    </div>
                    @endif
                    @if($v->delay_rate !== null)
                    <div class="text-center">
                        <div class="text-sm font-bold {{ $v->delay_rate > 20 ? 'text-red-600' : 'text-gray-700' }}">{{ $v->delay_rate }}%</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Delay Rate</div>
                    </div>
                    @endif
                    @if($v->remake_rate !== null)
                    <div class="text-center">
                        <div class="text-sm font-bold {{ $v->remake_rate > 10 ? 'text-red-600' : 'text-gray-700' }}">{{ $v->remake_rate }}%</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Remake Rate</div>
                    </div>
                    @endif
                    @if($v->quality_score !== null)
                    <div class="text-center">
                        <div class="text-sm font-bold text-amber-500">{{ number_format($v->quality_score, 1) }}/5</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Quality</div>
                    </div>
                    @endif
                    @if($v->active_case_count > 0)
                    <div class="text-center">
                        <div class="text-sm font-bold text-blue-600">{{ $v->active_case_count }}</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Active</div>
                    </div>
                    @endif
                    {{-- Vendor score pill --}}
                    @if($v->vendor_score !== null)
                    <div class="ml-1">
                        <div class="flex items-center gap-1">
                            <div class="text-xs font-bold {{ $v->vendor_score >= 70 ? 'text-green-700' : ($v->vendor_score >= 50 ? 'text-amber-600' : 'text-red-600') }}">
                                Score: {{ $v->vendor_score }}/100
                            </div>
                            <div class="w-20 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $v->vendor_score >= 70 ? 'bg-green-500' : ($v->vendor_score >= 50 ? 'bg-amber-400' : 'bg-red-400') }}"
                                     style="width: {{ $v->vendor_score }}%"></div>
                            </div>
                        </div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide">Vendor Score</div>
                    </div>
                    @endif
                </div>
                {{-- Recommendation badge --}}
                @if($v->recommendation_badge)
                <div class="mt-1.5">
                    <span class="px-2 py-0.5 text-[10px] font-bold rounded-full {{ $v->recommendation_badge_color }}">
                        ★ {{ $v->recommendation_badge }}
                    </span>
                </div>
                @endif
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-2 shrink-0">
                <button @click="openEdit({{ json_encode($v) }})"
                        class="p-1.5 text-gray-400 hover:text-brand-700 hover:bg-brand-50 rounded-lg transition" title="Edit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click="expanded = !expanded"
                        class="p-1.5 text-gray-400 hover:text-brand-700 hover:bg-brand-50 rounded-lg transition" title="View details">
                    <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>
        </div>

        {{-- Expanded panel --}}
        <div x-show="expanded" x-collapse class="border-t border-gray-100">
            <div class="p-5 space-y-5" x-data="vendorDetail({{ $v->id }})">

                {{-- ── Tabs ── --}}
                <div class="flex gap-1 border-b border-gray-100 pb-0">
                    <button @click="tab='info'" :class="tab==='info' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 text-sm font-medium border-b-2 -mb-px transition">Details</button>
                    <button @click="tab='contacts'" :class="tab==='contacts' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 text-sm font-medium border-b-2 -mb-px transition">
                        Contacts <span class="ml-1 text-xs bg-gray-100 text-gray-500 px-1.5 rounded-full">{{ count($v->contacts) }}</span>
                    </button>
                    <button @click="tab='services'" :class="tab==='services' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 text-sm font-medium border-b-2 -mb-px transition">
                        Services <span class="ml-1 text-xs bg-gray-100 text-gray-500 px-1.5 rounded-full">{{ count($v->services) }}</span>
                    </button>
                    <button @click="tab='performance'" :class="tab==='performance' ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 text-sm font-medium border-b-2 -mb-px transition">
                        Performance
                    </button>
                </div>

                {{-- Details tab --}}
                <div x-show="tab==='info'" class="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Email</div>
                        <div class="text-gray-700">{{ $v->email ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Digital Work Email</div>
                        <div class="text-gray-700">{{ $v->digital_email ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">WhatsApp</div>
                        <div class="text-gray-700">{{ $v->whatsapp_number ?: '—' }}</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Default Turnaround</div>
                        <div class="text-gray-700">{{ $v->default_turnaround_days ?? 7 }} days</div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Payment Terms</div>
                        <div class="text-gray-700">
                            {{ $v->payment_terms === 'monthly_account' ? 'Monthly Account' : 'Per Case' }}
                            @if($v->payment_terms === 'monthly_account' && $v->credit_days)
                                <span class="text-gray-400">· Net {{ $v->credit_days }}</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Last Order</div>
                        <div class="text-gray-700">{{ $v->last_order_date ? \Carbon\Carbon::parse($v->last_order_date)->format('d M Y') : 'Never' }}</div>
                    </div>
                    @if($v->address)
                    <div class="col-span-2 md:col-span-3">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Address</div>
                        <div class="text-gray-700">{{ $v->address }}</div>
                    </div>
                    @endif
                    @if(!empty($v->capability_categories))
                    <div class="col-span-2 md:col-span-3">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Work Categories</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($v->capability_categories as $sp)
                            <span class="px-2 py-0.5 text-xs bg-brand-50 text-brand-700 border border-brand-200 rounded-full">{{ $sp }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @if($v->notes)
                    <div class="col-span-2 md:col-span-3">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">Notes</div>
                        <div class="text-gray-600 text-xs">{{ $v->notes }}</div>
                    </div>
                    @endif
                </div>

                {{-- Contacts tab --}}
                <div x-show="tab==='contacts'" class="space-y-3">
                    <template x-for="c in contacts" :key="c.id">
                        <div class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="w-8 h-8 rounded-lg bg-brand-100 text-brand-700 font-bold text-sm flex items-center justify-center shrink-0"
                                 x-text="c.name ? c.name[0].toUpperCase() : '?'"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-sm text-gray-800" x-text="c.name"></span>
                                    <template x-if="c.is_primary">
                                        <span class="px-1.5 py-0.5 text-[10px] font-bold bg-brand-100 text-brand-700 rounded">PRIMARY</span>
                                    </template>
                                    <span class="text-xs text-gray-400" x-text="c.role || ''"></span>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5 space-y-0.5">
                                    <div x-show="c.phone" x-text="'' + c.phone"></div>
                                    <div x-show="c.whatsapp" x-text="'' + c.whatsapp"></div>
                                    <div x-show="c.email" x-text="'' + c.email"></div>
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button @click="editContact(c)"
                                        class="p-1 text-gray-400 hover:text-brand-700 rounded transition text-xs">Edit</button>
                                <button @click="deleteContact(c.id)"
                                        class="p-1 text-gray-400 hover:text-red-600 rounded transition text-xs">✕</button>
                            </div>
                        </div>
                    </template>

                    {{-- Add contact inline form --}}
                    <div x-show="!showContactForm">
                        <button @click="showContactForm=true; contactForm={}"
                                class="w-full py-2 text-sm text-brand-600 border border-dashed border-brand-300 rounded-lg hover:bg-brand-50 transition">
                            + Add Contact
                        </button>
                    </div>
                    <div x-show="showContactForm" class="p-4 bg-brand-50 border border-brand-200 rounded-lg space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Name *</label>
                                <input x-model="contactForm.name" type="text" placeholder="Full name"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</label>
                                <input x-model="contactForm.role" type="text" placeholder="e.g. Lab Manager, Accounts"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Phone</label>
                                <input x-model="contactForm.phone" type="tel"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">WhatsApp</label>
                                <input x-model="contactForm.whatsapp" type="tel"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Email</label>
                                <input x-model="contactForm.email" type="email"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" x-model="contactForm.is_primary" id="cp-primary-{{ $v->id }}" class="text-brand-600">
                            <label for="cp-primary-{{ $v->id }}" class="text-xs text-gray-600">Set as primary contact</label>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button @click="showContactForm=false" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button @click="saveContact({{ $v->id }})"
                                    class="px-4 py-1.5 text-xs font-semibold bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                                <span x-text="contactForm.id ? 'Update' : 'Add Contact'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Services tab --}}
                <div x-show="tab==='services'" class="space-y-3">
                    <template x-for="s in services" :key="s.id">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-100">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-sm text-gray-800" x-text="s.service_name"></span>
                                    <span x-show="s.category" class="text-xs text-gray-400" x-text="'· ' + s.category"></span>
                                    <span x-show="!s.is_active" class="px-1.5 py-0.5 text-[10px] font-bold bg-gray-100 text-gray-400 rounded">INACTIVE</span>
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5 flex gap-4">
                                    <span class="font-semibold text-brand-700" x-text="'Rs. ' + parseFloat(s.default_rate).toLocaleString() + (s.unit ? ' / ' + s.unit : '')"></span>
                                    <span x-show="s.turnaround_days" x-text="s.turnaround_days + 'd turnaround'"></span>
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0">
                                <button @click="editService(s)" class="p-1 text-gray-400 hover:text-brand-700 rounded text-xs transition">Edit</button>
                                <button @click="deleteService(s.id)" class="p-1 text-gray-400 hover:text-red-600 rounded text-xs transition">✕</button>
                            </div>
                        </div>
                    </template>

                    {{-- Add service form(s) --}}
                    <div x-show="!showServiceForm && !showBulkForm" class="flex gap-2">
                        <button @click="showServiceForm=true; serviceForm={is_active:true}"
                                class="flex-1 py-2 text-sm text-brand-600 border border-dashed border-brand-300 rounded-lg hover:bg-brand-50 transition">
                            + Add Service / Rate
                        </button>
                        <button @click="showBulkForm=true; if(!bulkRows.length) addBulkRow()"
                                class="flex-1 py-2 text-sm text-gray-600 border border-dashed border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            + Add Price List (multiple)
                        </button>
                    </div>

                    {{-- Bulk price list — Treatment/Item + Cost, one row per line --}}
                    <div x-show="showBulkForm" class="p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-2">
                        <p class="text-xs text-gray-500">Add the lab's price list — treatment/item on one side, cost on the other. These feed directly into the Lab tab / lab entry forms, so the cost auto-fills the moment that treatment is selected for this vendor.</p>
                        <div class="grid grid-cols-12 gap-1.5 text-[10px] font-semibold text-gray-400 uppercase tracking-wide px-1">
                            <div class="col-span-5">Treatment / Item</div>
                            <div class="col-span-3">Category</div>
                            <div class="col-span-2">Cost (Rs.)</div>
                            <div class="col-span-1">Days</div>
                        </div>
                        <div class="space-y-1.5 max-h-80 overflow-y-auto pr-1">
                            <template x-for="(row, idx) in bulkRows" :key="idx">
                                <div class="grid grid-cols-12 gap-1.5 items-center bg-white p-1.5 rounded border border-gray-100">
                                    <input x-model="row.service_name" type="text" placeholder="e.g. Zirconia Crown"
                                           class="col-span-5 border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-brand-300 focus:outline-none">
                                    <select x-model="row.category" class="col-span-3 border border-gray-200 rounded px-1 py-1 text-xs focus:ring-1 focus:ring-brand-300 focus:outline-none">
                                        <option value="">— Category —</option>
                                        @foreach(array_keys(\App\Models\LabCase::WORK_CATEGORIES) as $cat)
                                        <option value="{{ $cat }}">{{ $cat }}</option>
                                        @endforeach
                                    </select>
                                    <input x-model="row.default_rate" type="number" min="0" step="0.01" placeholder="0.00"
                                           class="col-span-2 border border-gray-200 rounded px-2 py-1 text-xs focus:ring-1 focus:ring-brand-300 focus:outline-none">
                                    <input x-model="row.turnaround_days" type="number" min="1" max="90" placeholder="—"
                                           class="col-span-1 border border-gray-200 rounded px-1 py-1 text-xs focus:ring-1 focus:ring-brand-300 focus:outline-none">
                                    <button @click="bulkRows.splice(idx, 1)" class="col-span-1 text-gray-300 hover:text-red-500 text-xs">✕</button>
                                </div>
                            </template>
                        </div>
                        <button @click="addBulkRow()" class="text-xs text-brand-600 hover:text-brand-700 font-semibold">+ Add Row</button>
                        <div class="flex gap-2 justify-end pt-1">
                            <button @click="showBulkForm=false; bulkRows=[]" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button @click="saveBulkRows({{ $v->id }})" :disabled="bulkSaving"
                                    class="px-4 py-1.5 text-xs font-semibold bg-brand-600 text-white rounded-lg hover:bg-brand-700 disabled:opacity-50">
                                <span x-text="bulkSaving ? 'Saving…' : 'Save All'"></span>
                            </button>
                        </div>
                    </div>
                    <div x-show="showServiceForm" class="p-4 bg-brand-50 border border-brand-200 rounded-lg space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Treatment / Prosthesis *</label>
                                <input x-model="serviceForm.service_name" type="text" placeholder="e.g. Zirconia Crown, PFM Crown, Cast Partial Denture"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</label>
                                <select x-model="serviceForm.category"
                                        class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                                    <option value="">— Select —</option>
                                    @foreach(array_keys(\App\Models\LabCase::WORK_CATEGORIES) as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Turnaround (days)</label>
                                <input x-model="serviceForm.turnaround_days" type="number" min="1" max="90"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Amount / Charges (Rs.) *</label>
                                <input x-model="serviceForm.default_rate" type="number" min="0" step="0.01"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Unit</label>
                                <input x-model="serviceForm.unit" type="text" placeholder="per unit / per tooth"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                            <div class="col-span-2">
                                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Notes</label>
                                <input x-model="serviceForm.notes" type="text" placeholder="GST included? Special conditions?"
                                       class="mt-1 w-full border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" x-model="serviceForm.is_active" id="svc-active-{{ $v->id }}" class="text-brand-600" checked>
                            <label for="svc-active-{{ $v->id }}" class="text-xs text-gray-600">Active / available</label>
                        </div>
                        <div class="flex gap-2 justify-end">
                            <button @click="showServiceForm=false" class="px-3 py-1.5 text-xs text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button @click="saveService({{ $v->id }})"
                                    class="px-4 py-1.5 text-xs font-semibold bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                                <span x-text="serviceForm.id ? 'Update' : 'Add Service'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Performance tab --}}
                <div x-show="tab==='performance'" class="space-y-4">
                    @if($v->vendor_score !== null || $v->quality_score !== null)
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

                        {{-- Vendor Score --}}
                        @if($v->vendor_score !== null)
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold {{ $v->vendor_score >= 70 ? 'text-green-600' : ($v->vendor_score >= 50 ? 'text-amber-500' : 'text-red-500') }}">
                                {{ $v->vendor_score }}
                            </div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 mt-0.5">Vendor Score</div>
                            <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full {{ $v->vendor_score >= 70 ? 'bg-green-500' : ($v->vendor_score >= 50 ? 'bg-amber-400' : 'bg-red-400') }}"
                                     style="width: {{ $v->vendor_score }}%"></div>
                            </div>
                        </div>
                        @endif

                        {{-- Quality Score --}}
                        @if($v->quality_score !== null)
                        <div class="bg-amber-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold text-amber-600">{{ number_format($v->quality_score, 1) }}<span class="text-sm text-amber-400">/5</span></div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 mt-0.5">Avg Quality</div>
                            <div class="flex justify-center gap-0.5 mt-1.5">
                                @for($i = 1; $i <= 5; $i++)
                                    <span class="text-xs {{ $i <= round($v->quality_score) ? 'text-amber-400' : 'text-gray-200' }}">★</span>
                                @endfor
                            </div>
                        </div>
                        @endif

                        {{-- Delay Rate --}}
                        @if($v->delay_rate !== null)
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold {{ $v->delay_rate > 20 ? 'text-red-600' : 'text-green-600' }}">{{ $v->delay_rate }}%</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 mt-0.5">Delay Rate</div>
                            <div class="text-[10px] mt-1 {{ $v->delay_rate > 20 ? 'text-red-500' : 'text-green-500' }}">
                                {{ $v->delay_rate > 20 ? 'High — investigate' : ($v->delay_rate > 10 ? 'Moderate' : 'Excellent') }}
                            </div>
                        </div>
                        @endif

                        {{-- Remake Rate --}}
                        @if($v->remake_rate !== null)
                        <div class="bg-gray-50 rounded-xl p-4 text-center">
                            <div class="text-2xl font-bold {{ $v->remake_rate > 10 ? 'text-red-600' : 'text-green-600' }}">{{ $v->remake_rate }}%</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 mt-0.5">Remake Rate</div>
                            <div class="text-[10px] mt-1 {{ $v->remake_rate > 10 ? 'text-red-500' : 'text-green-500' }}">
                                {{ $v->remake_rate > 10 ? 'High' : ($v->remake_rate > 5 ? 'Moderate' : 'Excellent') }}
                            </div>
                        </div>
                        @endif
                    </div>

                    {{-- Recommendation badge --}}
                    @if($v->recommendation_badge)
                    <div class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-xl">
                        <span class="text-green-600 text-lg">★</span>
                        <div>
                            <span class="font-semibold text-green-700 text-sm">{{ $v->recommendation_badge }}</span>
                            <span class="text-xs text-green-600 ml-2">— This lab is performing well across all metrics</span>
                        </div>
                    </div>
                    @endif

                    @else
                    <div class="text-center py-8 text-gray-400 text-sm">
                        No performance data yet — send some cases to this lab to start tracking.
                    </div>
                    @endif
                </div>

                {{-- Deactivate button --}}
                @if($v->is_active)
                <div class="pt-2 border-t border-gray-100 flex justify-end">
                    <form method="POST" action="{{ route('lab-vendors.destroy', $v->id) }}"
                          onsubmit="return confirm('Deactivate this lab vendor?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline transition">
                            Deactivate vendor
                        </button>
                    </form>
                </div>
                @endif

            </div>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-8 py-16 text-center">
        <div class="text-4xl mb-3"></div>
        <div class="text-gray-500 text-sm">No lab vendors yet.</div>
        <button @click="openAdd()" class="mt-3 text-brand-600 text-sm hover:underline">+ Add your first lab</button>
    </div>
    @endforelse

    {{-- ══ ADD / EDIT MODAL ════════════════════════════════════════════════ --}}
    <div x-show="modalOpen"
         class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
         style="display:none" @click.self="modalOpen=false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                <h2 class="text-lg font-semibold text-gray-800" x-text="editId ? 'Edit Lab Vendor' : 'Add Lab Vendor'"></h2>
                <button @click="modalOpen=false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-5">
                <form :id="editId ? 'vform-edit' : 'vform-add'"
                      :action="editId ? '/lab-vendors/' + editId : '/lab-vendors'"
                      method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="_method" :value="editId ? 'PUT' : 'POST'">

                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Name *</label>
                        <input type="text" name="name" x-model="form.name" dusk="labvendor-name" required placeholder="e.g. Smile Dental Lab"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>

                    {{-- Address --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Address</label>
                        <textarea name="address" x-model="form.address" rows="2" placeholder="Full address of the lab"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                    </div>

                    {{-- Contact Person 1 + 2 --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Primary Contact Person</label>
                            <input type="text" name="contact_person" x-model="form.contact_person" placeholder="Name"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Phone / WhatsApp</label>
                            <input type="tel" name="phone" x-model="form.phone" placeholder="+91 XXXXX XXXXX"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        </div>
                    </div>

                    {{-- WhatsApp --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">WhatsApp Number <span class="text-gray-400 font-normal">(if different from phone)</span></label>
                        <input type="tel" name="whatsapp_number" x-model="form.whatsapp_number"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>

                    {{-- Emails --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Email ID</label>
                            <input type="email" name="email" x-model="form.email" placeholder="billing@lab.com"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Digital Work Email</label>
                            <input type="email" name="digital_email" x-model="form.digital_email" placeholder="cases@lab.com"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <p class="text-[10px] text-gray-400 mt-0.5">For STL files, scan orders, DSD</p>
                        </div>
                    </div>

                    {{-- Operational --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Default Turnaround (days)</label>
                            <input type="number" name="default_turnaround_days" x-model="form.default_turnaround_days" min="1" max="90" placeholder="7"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Payment Terms</label>
                            <select name="payment_terms" x-model="form.payment_terms"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                                <option value="per_case">Per Case</option>
                                <option value="monthly_account">Monthly Account</option>
                            </select>
                        </div>
                    </div>

                    <div x-show="form.payment_terms === 'monthly_account'">
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Credit Days</label>
                        <input type="number" name="credit_days" x-model="form.credit_days" min="0" max="180" placeholder="e.g. 30"
                               class="w-full max-w-[160px] border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        <p class="text-[10px] text-gray-400 mt-0.5">Days after the monthly bill before payment is due.</p>
                    </div>

                    <p class="text-xs text-gray-400 -mt-1">
                        Work categories this lab can do are set from its priced Service catalog — save the vendor first, then add services from the expanded card.
                    </p>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</label>
                        <textarea name="notes" x-model="form.notes" rows="2" placeholder="Any special instructions, terms, or notes about this lab…"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                    </div>

                    <input type="hidden" name="is_active" value="1">
                </form>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
                <button @click="modalOpen=false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
                <button @click="submitVendorForm()"
                        dusk="labvendor-save"
                        class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                    <span x-text="editId ? 'Save Changes' : 'Add Lab Vendor'"></span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('head-extra')
<script>
function labVendors() {
    return {
        modalOpen: false,
        editId: null,
        form: {
            name: '', address: '', contact_person: '',
            phone: '', whatsapp_number: '',
            email: '', digital_email: '',
            default_turnaround_days: 7,
            payment_terms: 'per_case',
            credit_days: null,
            notes: '',
        },

        openAdd() {
            this.editId = null;
            this.form = { name:'', address:'', contact_person:'', phone:'', whatsapp_number:'',
                          email:'', digital_email:'', default_turnaround_days:7,
                          payment_terms:'per_case', credit_days:null, notes:'' };
            this.modalOpen = true;
        },

        openEdit(v) {
            this.editId = v.id;
            this.form = {
                name:                    v.name || '',
                address:                 v.address || '',
                contact_person:          v.contact_person || '',
                phone:                   v.phone || '',
                whatsapp_number:         v.whatsapp_number || '',
                email:                   v.email || '',
                digital_email:           v.digital_email || '',
                default_turnaround_days: v.default_turnaround_days || 7,
                payment_terms:           v.payment_terms || 'per_case',
                credit_days:             v.credit_days ?? null,
                notes:                   v.notes || '',
            };
            this.modalOpen = true;
        },

        submitVendorForm() {
            const formId = this.editId ? 'vform-edit' : 'vform-add';
            document.getElementById(formId).submit();
        },
    };
}

function vendorDetail(vendorId) {
    return {
        tab: 'info',
        contacts: [],
        services: [],
        showContactForm: false,
        showServiceForm: false,
        showBulkForm: false,
        bulkRows: [],
        bulkSaving: false,
        contactForm: {},
        serviceForm: { is_active: true },

        init() {
            // Load contacts and services from the pre-rendered PHP data
            const allVendors = @json($vendors);
            const vendor = allVendors.find(v => v.id == vendorId);
            if (vendor) {
                this.contacts = vendor.contacts || [];
                this.services = vendor.services || [];
            }
        },

        formatDate(d) {
            if (!d) return '';
            return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        async saveContact(vendorId) {
            if (!this.contactForm.name) { alert('Contact name is required.'); return; }
            const url = this.contactForm.id
                ? `/lab-vendors/${vendorId}/contacts/${this.contactForm.id}`
                : `/lab-vendors/${vendorId}/contacts`;
            const method = this.contactForm.id ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(this.contactForm),
            });
            const data = await res.json();
            if (data.success) {
                if (this.contactForm.id) {
                    const idx = this.contacts.findIndex(c => c.id === this.contactForm.id);
                    if (idx > -1) this.contacts[idx] = data.contact;
                } else {
                    this.contacts.push(data.contact);
                }
                this.showContactForm = false;
                this.contactForm = {};
            }
        },

        editContact(c) {
            this.contactForm = { ...c };
            this.showContactForm = true;
        },

        async deleteContact(id) {
            if (!confirm('Remove this contact?')) return;
            const res = await fetch(`/lab-vendors/${vendorId}/contacts/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            const data = await res.json();
            if (data.success) this.contacts = this.contacts.filter(c => c.id !== id);
        },

        async saveService(vendorId) {
            if (!this.serviceForm.service_name) { alert('Service name is required.'); return; }
            if (!this.serviceForm.default_rate && this.serviceForm.default_rate !== 0) { alert('Rate is required.'); return; }
            const url = this.serviceForm.id
                ? `/lab-vendors/${vendorId}/services/${this.serviceForm.id}`
                : `/lab-vendors/${vendorId}/services`;
            const method = this.serviceForm.id ? 'PUT' : 'POST';
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(this.serviceForm),
            });
            const data = await res.json();
            if (data.success) {
                if (this.serviceForm.id) {
                    const idx = this.services.findIndex(s => s.id === this.serviceForm.id);
                    if (idx > -1) this.services[idx] = data.service;
                } else {
                    this.services.push(data.service);
                }
                this.showServiceForm = false;
                this.serviceForm = { is_active: true };
            }
        },

        editService(s) {
            this.serviceForm = { ...s };
            this.showServiceForm = true;
        },

        async deleteService(id) {
            if (!confirm('Remove this service?')) return;
            const res = await fetch(`/lab-vendors/${vendorId}/services/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            });
            const data = await res.json();
            if (data.success) this.services = this.services.filter(s => s.id !== id);
        },

        addBulkRow() {
            this.bulkRows.push({ service_name: '', category: '', default_rate: null, turnaround_days: null });
        },

        async saveBulkRows(vendorId) {
            const rows = this.bulkRows.filter(r => r.service_name && r.category && r.default_rate !== null && r.default_rate !== '');
            const skipped = this.bulkRows.length - rows.length;
            if (!rows.length) { alert('Fill in treatment, category, and cost for at least one row.'); return; }
            this.bulkSaving = true;
            try {
                const res = await fetch(`/lab-vendors/${vendorId}/services/bulk`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ rows }),
                });
                const data = await res.json();
                if (data.success) {
                    this.services.push(...data.services);
                    this.showBulkForm = false;
                    this.bulkRows = [];
                    if (skipped > 0) alert(`Saved ${data.count}. Skipped ${skipped} incomplete row(s) — treatment, category, and cost are all required.`);
                } else {
                    alert(data.message || 'Could not save the price list.');
                }
            } catch (e) {
                alert('Save failed. Please try again.');
            } finally {
                this.bulkSaving = false;
            }
        },
    };
}
</script>
@endsection
