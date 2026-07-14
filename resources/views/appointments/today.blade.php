@extends('layouts.app')

@section('content')
<div class="min-h-screen" style="background:#f5eef9;">
    <div x-data="todaySchedule()" x-init="init()">

        {{-- Top Bar --}}
        <div class="border-b border-[#e8d5f0] bg-white px-6 py-4 flex items-center justify-between">
            <div>
                <h1 class="font-['Cormorant_Garamond'] text-2xl font-semibold text-[#380740] leading-tight">
                    Today's Schedule
                </h1>
                <p class="font-['DM_Sans'] text-xs text-[#6a0f70]/60 mt-0.5" x-text="dateLabel"></p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Live indicator --}}
                <div class="flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    <span class="font-['DM_Sans'] text-xs text-[#380740]/50">Live</span>
                </div>
                <a href="{{ route('appointments.create') }}"
                   class="flex items-center gap-2 bg-[#6a0f70] text-white font-['DM_Sans'] text-xs font-medium px-4 py-2 hover:bg-[#380740] transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                        <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Book
                </a>
            </div>
        </div>

        {{-- Stats Strip --}}
        <div class="grid grid-cols-5 border-b border-[#e8d5f0] bg-white">
            <template x-for="stat in stats" :key="stat.label">
                <div class="px-6 py-3 border-r border-[#e8d5f0] last:border-r-0">
                    <div class="font-['Cormorant_Garamond'] text-2xl font-bold text-[#380740]" x-text="stat.count"></div>
                    <div class="font-['DM_Sans'] text-[10px] uppercase tracking-widest text-[#6a0f70]/60 mt-0.5" x-text="stat.label"></div>
                </div>
            </template>
            {{-- Chair utilization — booked minutes / (chairs x clinic hours). See
                 AppointmentController::getChairUtilization() for how this is computed
                 and what it defaults to when operatories/capacity aren't configured. --}}
            <div class="px-6 py-3" :title="utilizationTooltip">
                <div class="font-['Cormorant_Garamond'] text-2xl font-bold" :class="utilizationColor" x-text="(counts.chair_utilization_pct ?? 0) + '%'"></div>
                <div class="font-['DM_Sans'] text-[10px] uppercase tracking-widest text-[#6a0f70]/60 mt-0.5">Chair Utilization</div>
            </div>
        </div>

        {{-- Doctor Filter Tabs --}}
        <div class="bg-white border-b border-[#e8d5f0] px-6 flex gap-0 overflow-x-auto">
            <button @click="activeDoctor = null"
                    class="font-['DM_Sans'] text-xs py-3 px-4 border-b-2 transition-colors whitespace-nowrap"
                    :class="activeDoctor === null ? 'border-[#6a0f70] text-[#6a0f70] font-medium' : 'border-transparent text-[#380740]/50 hover:text-[#380740]'">
                All Doctors
            </button>
            <template x-for="doc in doctors" :key="doc">
                <button @click="activeDoctor = doc"
                        class="font-['DM_Sans'] text-xs py-3 px-4 border-b-2 transition-colors whitespace-nowrap"
                        :class="activeDoctor === doc ? 'border-[#6a0f70] text-[#6a0f70] font-medium' : 'border-transparent text-[#380740]/50 hover:text-[#380740]'"
                        x-text="doc">
                </button>
            </template>
        </div>

        {{-- Main Content --}}
        <div class="p-6">

            {{-- Loading --}}
            <div x-show="loading" class="flex items-center justify-center py-20">
                <svg class="animate-spin text-[#6a0f70]" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                    <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                </svg>
            </div>

            {{-- Empty --}}
            <div x-show="!loading && filtered.length === 0"
                 class="flex flex-col items-center justify-center py-20 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                     fill="none" stroke="#d4b8dc" stroke-width="1.5" stroke-linecap="square" class="mb-4">
                    <rect x="3" y="4" width="18" height="18" rx="0" ry="0"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <p class="font-['Cormorant_Garamond'] text-xl text-[#380740]/40">No appointments today</p>
                <a href="{{ route('appointments.create') }}"
                   class="mt-4 font-['DM_Sans'] text-xs text-[#6a0f70] underline underline-offset-4">
                    Book the first one
                </a>
            </div>

            {{-- Appointment List --}}
            <div x-show="!loading && filtered.length > 0" class="space-y-2">
                <template x-for="appt in filtered" :key="appt.id">
                    <div class="bg-white border border-[#e8d5f0] hover:border-[#c9a8d4] transition-colors"
                         :class="appt.status === 'in_chair' ? 'border-l-4 border-l-[#6a0f70]' : ''">
                        <div class="flex items-stretch">

                            {{-- Time Column --}}
                            <div class="w-20 flex-shrink-0 flex flex-col items-center justify-center border-r border-[#e8d5f0] py-4 px-2">
                                <span class="font-['Cormorant_Garamond'] text-lg font-bold text-[#380740] leading-none" x-text="appt.appointment_time"></span>
                                <span class="font-['DM_Sans'] text-[10px] text-[#6a0f70]/50 mt-1" x-text="appt.duration_minutes + 'm'"></span>
                            </div>

                            {{-- Patient Info --}}
                            <div class="flex-1 px-4 py-3 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-['DM_Sans'] text-sm font-semibold text-[#380740]" x-text="appt.patient_name"></p>
                                        <p class="font-['DM_Sans'] text-xs text-[#6a0f70]/60 mt-0.5" x-text="appt.patient_phone"></p>
                                    </div>
                                    {{-- Status Badge --}}
                                    <span class="flex-shrink-0 font-['DM_Sans'] text-[10px] font-medium px-2 py-0.5 uppercase tracking-wide"
                                          :class="statusClass(appt.status)"
                                          x-text="statusLabel(appt.status)">
                                    </span>
                                </div>

                                <div class="flex items-center gap-3 mt-2">
                                    <span class="font-['DM_Sans'] text-xs text-[#380740]/50" x-text="appt.doctor_name"></span>
                                    <span class="text-[#d4b8dc]">·</span>
                                    <span class="font-['DM_Sans'] text-xs capitalize"
                                          :class="appt.type === 'treatment' ? 'text-[#6a0f70]' : 'text-[#380740]/50'"
                                          x-text="appt.type"></span>
                                    <template x-if="appt.chief_complaint">
                                        <span class="text-[#d4b8dc]">·</span>
                                    </template>
                                    <template x-if="appt.chief_complaint">
                                        <span class="font-['DM_Sans'] text-xs text-[#380740]/40 truncate max-w-xs" x-text="appt.chief_complaint"></span>
                                    </template>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex-shrink-0 flex items-center gap-1 px-3 border-l border-[#e8d5f0]">
                                <template x-if="appt.status === 'scheduled'">
                                    <button @click="updateStatus(appt, 'checkin')"
                                            class="font-['DM_Sans'] text-[10px] font-medium px-3 py-1.5 bg-[#f5eef9] text-[#6a0f70] hover:bg-[#6a0f70] hover:text-white transition-colors uppercase tracking-wide">
                                        Check In
                                    </button>
                                </template>
                                <template x-if="appt.status === 'checkin'">
                                    <button @click="updateStatus(appt, 'in_chair')"
                                            class="font-['DM_Sans'] text-[10px] font-medium px-3 py-1.5 bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors uppercase tracking-wide">
                                        Start
                                    </button>
                                </template>
                                <template x-if="appt.status === 'in_chair'">
                                    <button @click="updateStatus(appt, 'checkout')"
                                            class="font-['DM_Sans'] text-[10px] font-medium px-3 py-1.5 bg-[#059669] text-white hover:bg-[#047857] transition-colors uppercase tracking-wide">
                                        Done
                                    </button>
                                </template>
                                <template x-if="['scheduled','checkin'].includes(appt.status)">
                                    <button @click="updateStatus(appt, 'no_show')"
                                            title="No Show"
                                            class="p-1.5 text-[#380740]/30 hover:text-red-500 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                        </svg>
                                    </button>
                                </template>
                            </div>

                        </div>
                    </div>
                </template>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('todaySchedule', () => ({
        appointments: [],
        counts: {},
        loading: true,
        activeDoctor: null,
        dateLabel: '',
        refreshTimer: null,

        get utilizationColor() {
            const pct = this.counts.chair_utilization_pct ?? 0;
            if (pct >= 80) return 'text-emerald-600';
            if (pct >= 50) return 'text-[#380740]';
            return 'text-amber-600';
        },

        get utilizationTooltip() {
            if (!this.counts.chair_capacity_minutes) return '';
            const booked = this.counts.chair_booked_minutes ?? 0;
            const capacity = this.counts.chair_capacity_minutes ?? 0;
            const chairs = this.counts.chair_count ?? 1;
            return `${booked} of ${capacity} chair-minutes booked across ${chairs} chair(s) today`;
        },

        get doctors() {
            return [...new Set(this.appointments.map(a => a.doctor_name))].filter(Boolean);
        },

        get filtered() {
            if (!this.activeDoctor) return this.appointments;
            return this.appointments.filter(a => a.doctor_name === this.activeDoctor);
        },

        get stats() {
            const all = this.appointments;
            return [
                { label: 'Total',    count: all.length },
                { label: 'Waiting',  count: all.filter(a => a.status === 'checkin').length },
                { label: 'In Chair', count: all.filter(a => a.status === 'in_chair').length },
                { label: 'Done',     count: all.filter(a => ['checkout','done'].includes(a.status)).length },
            ];
        },

        async init() {
            const d = new Date();
            this.dateLabel = d.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            await this.load();
            this.refreshTimer = setInterval(() => this.load(), 30000);
        },

        async load() {
            const today = new Date();
            const pad = n => String(n).padStart(2,'0');
            const dateStr = `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;
            try {
                const [apptRes, countsRes] = await Promise.all([
                    fetch(`{{ route('appointments.index') }}?json=1&view=day&date=${dateStr}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }),
                    fetch(`{{ route('appointments.status.counts') }}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }),
                ]);
                this.appointments = await apptRes.json();
                this.counts = await countsRes.json();
            } catch(e) {}
            this.loading = false;
        },

        async updateStatus(appt, status) {
            const prev = appt.status;
            appt.status = status;
            try {
                const res = await fetch(`/appointments/${appt.id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ status })
                });
                const data = await res.json();
                if (data.counts) this.counts = data.counts;
            } catch(e) {
                appt.status = prev;
            }
        },

        statusLabel(s) {
            const map = {
                scheduled: 'Scheduled', checkin: 'Waiting',
                in_chair: 'In Chair',   checkout: 'Done',
                done: 'Done',           cancelled: 'Cancelled',
                no_show: 'No Show',
            };
            return map[s] ?? s;
        },

        statusClass(s) {
            const map = {
                scheduled: 'bg-[#f5eef9] text-[#6a0f70]',
                checkin:   'bg-amber-50 text-amber-700',
                in_chair:  'bg-[#6a0f70] text-white',
                checkout:  'bg-emerald-50 text-emerald-700',
                done:      'bg-emerald-50 text-emerald-700',
                cancelled: 'bg-red-50 text-red-600',
                no_show:   'bg-slate-100 text-slate-500',
            };
            return map[s] ?? 'bg-gray-100 text-gray-500';
        },
    }));
});
</script>
@endpush
