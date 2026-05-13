@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f5eef9]" x-data="appointmentCalendar">

    {{-- TOP BAR --}}
    <div class="flex items-center justify-between px-6 py-4 bg-white border-b border-[#6a0f70]/10">
        <div class="flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6a0f70]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <h1 class="font-['Cormorant_Garamond'] text-2xl font-semibold text-[#380740] tracking-wide">Appointments</h1>
        </div>

        {{-- VIEW TOGGLE --}}
        <div class="flex items-center gap-1 bg-[#f5eef9] border border-[#6a0f70]/20 p-0.5">
            <button @click="view='day'; fetchAppointments()"
                :class="view==='day' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/10'"
                class="px-4 py-1.5 text-xs font-['DM_Sans'] font-medium transition-all">Day</button>
            <button @click="view='week'; fetchAppointments()"
                :class="view==='week' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/10'"
                class="px-4 py-1.5 text-xs font-['DM_Sans'] font-medium transition-all">Week</button>
            <button @click="view='month'; fetchAppointments()"
                :class="view==='month' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/10'"
                class="px-4 py-1.5 text-xs font-['DM_Sans'] font-medium transition-all">Month</button>
        </div>

        {{-- NAV + DATE --}}
        <div class="flex items-center gap-3">
            <button @click="prev()" class="p-1.5 border border-[#6a0f70]/20 text-[#6a0f70] hover:bg-[#6a0f70] hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <span x-text="headerLabel()" class="font-['Cormorant_Garamond'] text-lg font-semibold text-[#380740] min-w-[180px] text-center"></span>
            <button @click="next()" class="p-1.5 border border-[#6a0f70]/20 text-[#6a0f70] hover:bg-[#6a0f70] hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <button @click="goToday()" class="px-3 py-1.5 text-xs font-['DM_Sans'] border border-[#6a0f70]/30 text-[#6a0f70] hover:bg-[#6a0f70] hover:text-white transition-all">Today</button>
        </div>

        <button @click="openNewAt(currentDate, new Date().getHours() < 9 ? 9 : (new Date().getHours() > 20 ? 20 : new Date().getHours()))"
           class="flex items-center gap-2 bg-[#6a0f70] text-white px-4 py-2 text-sm font-['DM_Sans'] hover:bg-[#380740] transition-all">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            New Appointment
        </button>
    </div>

    {{-- STATUS LEGEND --}}
    <div class="flex items-center gap-4 px-6 py-2.5 bg-white border-b border-[#6a0f70]/10">
        <span class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest">Status</span>
        <template x-for="(cfg, key) in statusConfig" :key="key">
            <div class="flex items-center gap-1.5">
                <span :class="cfg.dot" class="w-2 h-2 rounded-full"></span>
                <span x-text="cfg.label" class="text-[11px] font-['DM_Sans'] text-[#380740]/60"></span>
            </div>
        </template>
    </div>

    {{-- FLASH --}}
    @if(session('success'))
    <div class="mx-6 mt-4 flex items-center gap-3 px-4 py-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-['DM_Sans']"
         x-data="{show:true}" x-show="show">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        {{ session('success') }}
        <button @click="show=false" class="ml-auto text-emerald-400 hover:text-emerald-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- CALENDAR BODY --}}
    <div class="p-6">

        {{-- ===== DAY VIEW ===== --}}
        <div x-show="view==='day'" class="bg-white border border-[#6a0f70]/10">
            <div class="grid" style="grid-template-columns: 56px 1fr;">
                <div class="border-r border-[#6a0f70]/10">
                    <div class="h-10 border-b border-[#6a0f70]/10"></div>
                    <template x-for="hour in hours" :key="hour">
                        <div class="h-16 border-b border-[#6a0f70]/5 flex items-start justify-end pr-2 pt-1">
                            <span x-text="formatHour(hour)" class="text-[10px] font-['DM_Sans'] text-[#380740]/30"></span>
                        </div>
                    </template>
                </div>
                <div class="relative">
                    <div class="h-10 border-b border-[#6a0f70]/10 flex items-center justify-center">
                        <span x-text="dayViewHeader()" class="text-xs font-['DM_Sans'] font-medium text-[#380740]"></span>
                    </div>
                    <div class="relative">
                        <template x-for="hour in hours" :key="hour">
                            <div class="h-16 border-b border-[#6a0f70]/5 hover:bg-[#f5eef9]/40 transition-colors cursor-pointer"
                                 @click="openNewAt(currentDate, hour)"></div>
                        </template>
                        <div x-show="isToday(currentDate)" :style="'top:' + nowLineTop() + 'px'" class="absolute left-0 right-0 flex items-center pointer-events-none z-20">
                            <div class="w-2 h-2 rounded-full bg-rose-500 -ml-1 flex-shrink-0"></div>
                            <div class="flex-1 h-px bg-rose-500"></div>
                        </div>
                        <template x-for="appt in dayAppointments()" :key="appt.id">
                            <div :style="apptStyle(appt)"
                                 :class="statusConfig[appt.status]?.card ?? 'border-[#6a0f70]/30 bg-[#f5eef9]'"
                                 class="absolute left-1 right-1 border-l-4 px-2 py-1 cursor-pointer z-10 overflow-hidden transition-all hover:brightness-95 shadow-sm"
                                 @click.stop="openAppt(appt)">
                                <div class="flex items-center justify-between">
                                    <span x-text="appt.patient_name" class="text-[11px] font-['DM_Sans'] font-semibold text-[#380740] truncate"></span>
                                    <span x-text="appt.appointment_time.slice(0,5)" class="text-[10px] font-['DM_Sans'] text-[#380740]/50 flex-shrink-0 ml-1"></span>
                                </div>
                                <span x-text="appt.doctor_name" class="text-[10px] font-['DM_Sans'] text-[#380740]/60 truncate block"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== WEEK VIEW ===== --}}
        <div x-show="view==='week'" class="bg-white border border-[#6a0f70]/10 overflow-auto">
            <div class="min-w-[700px]">
                <div class="grid border-b border-[#6a0f70]/10" style="grid-template-columns: 56px repeat(7, 1fr);">
                    <div class="border-r border-[#6a0f70]/10 h-10"></div>
                    <template x-for="d in weekDays()" :key="d.iso">
                        <div :class="isToday(d.date) ? 'bg-[#6a0f70]/5' : ''"
                             class="h-10 border-r border-[#6a0f70]/10 flex flex-col items-center justify-center last:border-r-0">
                            <span x-text="d.dayName" class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest"></span>
                            <span x-text="d.dayNum"
                                  :class="isToday(d.date) ? 'bg-[#6a0f70] text-white w-5 h-5 flex items-center justify-center text-[11px]' : 'text-[13px] text-[#380740] font-semibold'"
                                  class="font-['DM_Sans'] mt-0.5 inline-flex items-center justify-center"></span>
                        </div>
                    </template>
                </div>
                <template x-for="hour in hours" :key="hour">
                    <div class="grid border-b border-[#6a0f70]/5" style="grid-template-columns: 56px repeat(7, 1fr);">
                        <div class="h-16 border-r border-[#6a0f70]/10 flex items-start justify-end pr-2 pt-1">
                            <span x-text="formatHour(hour)" class="text-[10px] font-['DM_Sans'] text-[#380740]/30"></span>
                        </div>
                        <template x-for="d in weekDays()" :key="d.iso + '-' + hour">
                            <div :class="isToday(d.date) ? 'bg-[#6a0f70]/5' : ''"
                                 class="h-16 border-r border-[#6a0f70]/5 relative hover:bg-[#f5eef9]/50 cursor-pointer transition-colors last:border-r-0"
                                 @click="openNewAt(d.date, hour)">
                                <template x-for="(appt, apptIdx) in weekCellAppts(d.date, hour)" :key="appt.id">
                                    <div :class="statusConfig[appt.status]?.card ?? 'border-[#6a0f70]/30 bg-[#f5eef9]'"
                                         :style="'top:' + (2 + apptIdx * 20) + 'px; left:4px; right:4px;'"
                                         class="absolute border-l-2 px-1 py-0.5 text-[10px] font-['DM_Sans'] truncate cursor-pointer hover:brightness-95 z-10"
                                         @click.stop="openAppt(appt)">
                                        <span x-text="appt.patient_name" class="font-semibold text-[#380740]"></span>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- ===== MONTH VIEW ===== --}}
        <div x-show="view==='month'" class="bg-white border border-[#6a0f70]/10">
            <div class="grid grid-cols-7 border-b border-[#6a0f70]/10">
                <template x-for="name in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="name">
                    <div class="py-2 text-center text-[10px] font-['DM_Sans'] font-medium text-[#380740]/50 uppercase tracking-widest border-r border-[#6a0f70]/10 last:border-r-0">
                        <span x-text="name"></span>
                    </div>
                </template>
            </div>
            <div class="grid grid-cols-7">
                <template x-for="cell in monthCells()" :key="cell.iso">
                    <div :class="[
                            cell.currentMonth ? 'bg-white' : 'bg-[#f5eef9]/30',
                            isToday(cell.date) ? 'ring-1 ring-inset ring-[#6a0f70]/30' : '',
                            'border-r border-b border-[#6a0f70]/10 min-h-[100px] p-1.5 relative last:border-r-0 hover:bg-[#f5eef9]/60 cursor-pointer transition-colors'
                         ]"
                         @click="openNewAt(cell.date, 9)">
                        <span x-text="cell.day"
                              :class="isToday(cell.date) ? 'bg-[#6a0f70] text-white w-5 h-5 flex items-center justify-center' : 'text-[#380740]/60'"
                              class="text-[11px] font-['DM_Sans'] font-semibold mb-1 inline-flex"></span>
                        <div class="space-y-0.5">
                            <template x-for="appt in monthCellAppts(cell.date).slice(0,3)" :key="appt.id">
                                <div :class="statusConfig[appt.status]?.pill ?? 'bg-[#6a0f70]/10 text-[#6a0f70]'"
                                     class="text-[10px] font-['DM_Sans'] px-1 py-0.5 truncate cursor-pointer hover:opacity-80"
                                     @click.stop="openAppt(appt)">
                                    <span x-text="appt.appointment_time.slice(0,5) + ' ' + appt.patient_name"></span>
                                </div>
                            </template>
                            <template x-if="monthCellAppts(cell.date).length > 3">
                                <div class="text-[10px] font-['DM_Sans'] text-[#6a0f70]/70 px-1">
                                    +<span x-text="monthCellAppts(cell.date).length - 3"></span> more
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>

    </div>

    {{-- ===== NEW APPOINTMENT MODAL ===== --}}
    <div x-show="newAppt.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
         @click.self="newAppt.open=false"
         style="display:none;">

        <div class="bg-white w-full max-w-lg shadow-2xl border border-[#6a0f70]/20 max-h-[90vh] flex flex-col" @click.stop>

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-4 bg-[#380740] flex-shrink-0">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <h2 class="font-['Cormorant_Garamond'] text-xl text-white font-semibold">Book Appointment</h2>
                </div>
                <button @click="newAppt.open=false" class="text-white/60 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Error display --}}
            <div x-show="newAppt.errors && newAppt.errors.length" class="mx-6 mt-4 px-3 py-2 bg-red-50 border border-red-200 text-red-600 text-xs font-['DM_Sans'] flex-shrink-0">
                <template x-for="err in newAppt.errors" :key="err"><div x-text="err"></div></template>
            </div>

            {{-- Scrollable form body --}}
            <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

                {{-- ── PATIENT SECTION ── --}}
                {{-- Search field --}}
                <div>
                    <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">
                        Patient Name <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <input type="text"
                               x-model="newAppt.patientSearch"
                               @input.debounce.300ms="searchPatients()"
                               @focus="newAppt.showPatientDropdown = true"
                               placeholder="Search by name…"
                               class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white pr-8">
                        <template x-if="newAppt.patientId">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500 absolute right-2 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </template>
                        {{-- Patient search dropdown --}}
                        <div x-show="newAppt.showPatientDropdown && newAppt.patientResults.length > 0"
                             @click.outside="newAppt.showPatientDropdown = false"
                             class="absolute top-full left-0 right-0 bg-white border border-[#6a0f70]/20 shadow-lg z-50 max-h-40 overflow-y-auto">
                            <template x-for="p in newAppt.patientResults" :key="p.id">
                                <div @click="selectPatient(p)"
                                     class="px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] hover:bg-[#f5eef9] cursor-pointer flex items-center justify-between">
                                    <span x-text="p.name"></span>
                                    <span x-text="p.phone" class="text-xs text-[#380740]/40"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Mobile No. — auto-filled on patient select, editable --}}
                <div>
                    <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">
                        Mobile No.
                        <span x-show="newAppt.patientId" class="normal-case tracking-normal text-emerald-500 ml-1">auto-filled</span>
                    </label>
                    <input type="tel"
                           x-model="newAppt.patientPhone"
                           placeholder="Patient mobile number"
                           class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white"
                           :class="newAppt.patientId ? 'bg-[#f5eef9]/60' : 'bg-white'">
                </div>

                {{-- Date + Time row --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Date <span class="text-red-400">*</span></label>
                        <input type="date" x-model="newAppt.date"
                               class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white">
                    </div>
                    <div>
                        <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Time <span class="text-red-400">*</span></label>
                        <select x-model="newAppt.time"
                                class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white">
                            <template x-for="slot in timeSlots" :key="slot">
                                <option :value="slot" x-text="slot"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Doctor + Duration row --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Doctor <span class="text-red-400">*</span></label>
                        <select x-model="newAppt.doctorId"
                                class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white">
                            <option value="">— Select —</option>
                            @foreach($doctors as $doc)
                            <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Duration</label>
                        <select x-model="newAppt.duration"
                                class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white">
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">60 min</option>
                            <option value="90">90 min</option>
                            <option value="120">120 min</option>
                        </select>
                    </div>
                </div>

                {{-- Type toggle --}}
                <div>
                    <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Type <span class="text-red-400">*</span></label>
                    <div class="flex border border-[#6a0f70]/20">
                        <button type="button" @click="newAppt.type='consultation'; newAppt.treatmentCategoryId=''; newAppt.treatmentId='';"
                                :class="newAppt.type==='consultation' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                                class="flex-1 py-2 text-xs font-['DM_Sans'] font-medium transition-all">Consultation</button>
                        <button type="button" @click="newAppt.type='treatment'"
                                :class="newAppt.type==='treatment' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                                class="flex-1 py-2 text-xs font-['DM_Sans'] font-medium transition-all border-l border-[#6a0f70]/20">Treatment</button>
                    </div>
                </div>

                {{-- ── TREATMENT SECTION (only when type = treatment) ── --}}
                <div x-show="newAppt.type === 'treatment'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="border border-[#6a0f70]/15 bg-[#f5eef9]/40 p-4 space-y-3">

                    <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest">Treatment Details</p>

                    {{-- Treatment Category --}}
                    <div>
                        <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">
                            Treatment Category <span class="text-red-400">*</span>
                        </label>
                        <select x-model="newAppt.treatmentCategoryId"
                                @change="newAppt.treatmentId = ''"
                                class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white">
                            <option value="">— Select Category —</option>
                            <template x-for="cat in treatmentCategories" :key="cat.id">
                                <option :value="cat.id" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Treatment — filtered by selected category --}}
                    <div>
                        <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">
                            Treatment <span class="text-red-400">*</span>
                        </label>
                        <select x-model="newAppt.treatmentId"
                                @change="applyTreatmentDefaults()"
                                :disabled="!newAppt.treatmentCategoryId"
                                class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white disabled:opacity-40 disabled:cursor-not-allowed">
                            <option value="">— Select Treatment —</option>
                            <template x-for="t in filteredTreatments()" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </select>
                        <p x-show="!newAppt.treatmentCategoryId" class="text-[10px] font-['DM_Sans'] text-[#380740]/30 mt-1">
                            Select a category first
                        </p>
                    </div>
                </div>

                {{-- ── NOTES (mandatory) ── --}}
                <div>
                    <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">
                        Notes <span class="text-red-400">*</span>
                    </label>
                    <textarea x-model="newAppt.notes" rows="3"
                              placeholder="Reason for visit, patient concerns, instructions…"
                              class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white resize-none"
                              :class="newAppt.notes.trim() === '' && newAppt.errors.length ? 'border-red-300' : ''"></textarea>
                </div>

            </div>

            {{-- Footer actions --}}
            <div class="px-6 py-4 border-t border-[#6a0f70]/10 flex gap-2 flex-shrink-0">
                <button type="button" @click="newAppt.open=false"
                        class="flex-1 py-2.5 text-xs font-['DM_Sans'] font-medium border border-[#6a0f70]/30 text-[#6a0f70] hover:bg-[#6a0f70]/5 transition-all">
                    Cancel
                </button>
                <button type="button"
                        @click="submitAppointment()"
                        :disabled="newAppt.submitting"
                        class="flex-1 py-2.5 text-xs font-['DM_Sans'] font-medium bg-[#6a0f70] text-white hover:bg-[#380740] transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <svg x-show="newAppt.submitting" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                    <span x-text="newAppt.submitting ? 'Saving…' : 'Book Appointment'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ===== APPOINTMENT DETAIL DRAWER ===== --}}
    <div x-show="drawer.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/20 z-40" @click="drawer.open=false" style="display:none;"></div>

    <div x-show="drawer.open"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
         class="fixed top-0 right-0 h-full w-80 bg-white border-l border-[#6a0f70]/20 z-50 flex flex-col shadow-xl" style="display:none;">

        <div class="flex items-center justify-between px-5 py-4 border-b border-[#6a0f70]/10 bg-[#380740]">
            <h2 class="font-['Cormorant_Garamond'] text-lg text-white font-semibold">Appointment</h2>
            <button @click="drawer.open=false" class="text-white/60 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-5 space-y-4" x-show="drawer.appt">
            <div class="flex items-center justify-between">
                <span :class="statusConfig[drawer.appt?.status]?.pill ?? 'bg-[#6a0f70]/10 text-[#6a0f70]'"
                      x-text="statusConfig[drawer.appt?.status]?.label"
                      class="text-xs font-['DM_Sans'] font-semibold px-2 py-1 uppercase tracking-widest"></span>
                <span x-text="drawer.appt?.appointment_time?.slice(0,5)" class="text-sm font-['DM_Sans'] text-[#380740]/60"></span>
            </div>

            <div class="border-t border-[#6a0f70]/10 pt-4">
                <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Patient</p>
                <p x-text="drawer.appt?.patient_name" class="text-base font-['Cormorant_Garamond'] font-semibold text-[#380740]"></p>
                <p x-text="drawer.appt?.patient_phone" class="text-sm font-['DM_Sans'] text-[#380740]/60"></p>
            </div>

            <div class="border-t border-[#6a0f70]/10 pt-4">
                <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Doctor</p>
                <p x-text="drawer.appt?.doctor_name" class="text-sm font-['DM_Sans'] text-[#380740]"></p>
            </div>

            <div class="border-t border-[#6a0f70]/10 pt-4 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Type</p>
                    <p x-text="drawer.appt?.type" class="text-sm font-['DM_Sans'] text-[#380740] capitalize"></p>
                </div>
                <div>
                    <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Duration</p>
                    <p class="text-sm font-['DM_Sans'] text-[#380740]"><span x-text="drawer.appt?.duration_minutes"></span> min</p>
                </div>
            </div>

            {{-- Treatment info (shown only if treatment type) --}}
            <template x-if="drawer.appt?.treatment_category || drawer.appt?.treatment">
                <div class="border-t border-[#6a0f70]/10 pt-4 space-y-2">
                    <template x-if="drawer.appt?.treatment_category">
                        <div>
                            <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Category</p>
                            <p x-text="drawer.appt?.treatment_category" class="text-sm font-['DM_Sans'] text-[#380740]"></p>
                        </div>
                    </template>
                    <template x-if="drawer.appt?.treatment">
                        <div>
                            <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Treatment</p>
                            <p x-text="drawer.appt?.treatment" class="text-sm font-['DM_Sans'] text-[#380740]"></p>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Notes (or legacy chief_complaint fallback) --}}
            <div x-show="drawer.appt?.notes || drawer.appt?.chief_complaint" class="border-t border-[#6a0f70]/10 pt-4">
                <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-1">Notes</p>
                <p x-text="drawer.appt?.notes || drawer.appt?.chief_complaint" class="text-sm font-['DM_Sans'] text-[#380740]/80"></p>
            </div>

            <div class="border-t border-[#6a0f70]/10 pt-4">
                <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest mb-2">Update Status</p>
                <div class="grid grid-cols-2 gap-1.5">
                    <template x-for="(cfg, key) in statusConfig" :key="key">
                        <button @click="updateStatus(drawer.appt, key)"
                                :class="drawer.appt?.status === key ? 'ring-2 ring-[#6a0f70] opacity-100' : 'opacity-60 hover:opacity-100'"
                                :style="'background:' + cfg.hex + '15; border-color:' + cfg.hex + '40; color:' + cfg.hex"
                                class="text-[10px] font-['DM_Sans'] font-semibold px-2 py-1.5 border uppercase tracking-widest transition-all">
                            <span x-text="cfg.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-[#6a0f70]/10 flex gap-2">
            <a :href="drawer.appt ? '/appointments/' + drawer.appt.id + '/edit' : '#'"
               class="flex-1 text-center py-2 text-xs font-['DM_Sans'] font-medium border border-[#6a0f70]/30 text-[#6a0f70] hover:bg-[#6a0f70] hover:text-white transition-all">Edit</a>
            <a :href="drawer.appt?.patient_id ? '/patients/' + drawer.appt.patient_id : '#'"
               class="flex-1 text-center py-2 text-xs font-['DM_Sans'] font-medium bg-[#380740] text-white hover:bg-[#6a0f70] transition-all">Patient</a>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('appointmentCalendar', () => ({
        view: 'week',
        currentDate: new Date(),
        hours: Array.from({length: 14}, (_, i) => i + 8), // 8am–9pm
        drawer: { open: false, appt: null },

        // Treatment data passed from Blade
        treatmentCategories: @json($treatmentCategories ?? []),

        newAppt: {
            open: false,
            date: '', hour: 9, time: '09:00',
            // Patient fields
            patientId: null,
            patientSearch: '',
            patientPhone: '',
            patientResults: [],
            showPatientDropdown: false,
            // Appointment fields
            doctorId: '',
            duration: '30',
            type: 'consultation',
            // Treatment fields
            treatmentCategoryId: '',
            treatmentId: '',
            // Notes (mandatory)
            notes: '',
            // State
            submitting: false,
            errors: [],
        },

        timeSlots: @json($timeSlots ?? []),

        statusConfig: {
            scheduled:  { label: 'Scheduled',  dot: 'bg-blue-400',    card: 'border-blue-400 bg-blue-50',      pill: 'bg-blue-100 text-blue-700',      hex: '#3b82f6' },
            checkin:    { label: 'Checked In',  dot: 'bg-amber-400',   card: 'border-amber-400 bg-amber-50',    pill: 'bg-amber-100 text-amber-700',    hex: '#f59e0b' },
            in_chair:   { label: 'In Chair',    dot: 'bg-violet-500',  card: 'border-violet-500 bg-violet-50',  pill: 'bg-violet-100 text-violet-700',  hex: '#8b5cf6' },
            checkout:   { label: 'Checkout',    dot: 'bg-cyan-500',    card: 'border-cyan-500 bg-cyan-50',      pill: 'bg-cyan-100 text-cyan-700',      hex: '#06b6d4' },
            done:       { label: 'Done',        dot: 'bg-emerald-500', card: 'border-emerald-500 bg-emerald-50',pill: 'bg-emerald-100 text-emerald-700', hex: '#10b981' },
            cancelled:  { label: 'Cancelled',   dot: 'bg-red-400',     card: 'border-red-400 bg-red-50',        pill: 'bg-red-100 text-red-600',        hex: '#ef4444' },
            no_show:    { label: 'No Show',     dot: 'bg-slate-400',   card: 'border-slate-400 bg-slate-50',    pill: 'bg-slate-100 text-slate-600',    hex: '#94a3b8' },
        },

        appointments: @json($appointments ?? []),

        init() {
            setInterval(() => this.fetchAppointments(), 60000);
        },

        // ── Treatment helpers ──────────────────────────────────────────

        filteredTreatments() {
            if (!this.newAppt.treatmentCategoryId) return [];
            const cat = this.treatmentCategories.find(
                c => String(c.id) === String(this.newAppt.treatmentCategoryId)
            );
            return cat ? (cat.treatments ?? []) : [];
        },

        // Auto-fill duration when a treatment is selected
        applyTreatmentDefaults() {
            const treatments = this.filteredTreatments();
            const t = treatments.find(
                t => String(t.id) === String(this.newAppt.treatmentId)
            );
            if (t && t.default_duration_minutes) {
                // Round to nearest available slot option
                const durations = [15, 30, 45, 60, 90, 120];
                const closest = durations.reduce((prev, curr) =>
                    Math.abs(curr - t.default_duration_minutes) < Math.abs(prev - t.default_duration_minutes) ? curr : prev
                );
                this.newAppt.duration = String(closest);
            }
        },

        // ── Patient helpers ────────────────────────────────────────────

        async fetchAppointments() {
            try {
                const dateStr = this.isoDate(this.currentDate);
                const res = await fetch(`/appointments?json=1&date=${dateStr}&view=${this.view}`);
                if (res.ok) this.appointments = await res.json();
            } catch(e) {}
        },

        async searchPatients() {
            const q = this.newAppt.patientSearch.trim();
            if (q.length < 2) { this.newAppt.patientResults = []; return; }
            try {
                const res = await fetch(`/patients/search?q=${encodeURIComponent(q)}&json=1`);
                if (res.ok) this.newAppt.patientResults = await res.json();
            } catch(e) {}
        },

        selectPatient(p) {
            this.newAppt.patientId     = p.id;
            this.newAppt.patientSearch = p.name;       // name field only
            this.newAppt.patientPhone  = p.phone ?? ''; // phone fills separately
            this.newAppt.patientResults = [];
            this.newAppt.showPatientDropdown = false;
        },

        // ── Modal open ────────────────────────────────────────────────

        openNewAt(date, hour) {
            const h = Math.min(Math.max(hour, 9), 20);
            const slot = String(h).padStart(2, '0') + ':00';
            this.newAppt = {
                open: true,
                date: this.isoDate(date),
                hour: h,
                time: slot,
                patientId: null,
                patientSearch: '',
                patientPhone: '',
                patientResults: [],
                showPatientDropdown: false,
                doctorId: '',
                duration: '30',
                type: 'consultation',
                treatmentCategoryId: '',
                treatmentId: '',
                notes: '',
                submitting: false,
                errors: [],
            };
        },

        openAppt(appt) {
            this.drawer.appt = appt;
            this.drawer.open = true;
        },

        // ── Submit ────────────────────────────────────────────────────

        async submitAppointment() {
            this.newAppt.errors = [];

            if (!this.newAppt.patientId)        this.newAppt.errors.push('Please select a patient.');
            if (!this.newAppt.doctorId)          this.newAppt.errors.push('Please select a doctor.');
            if (!this.newAppt.date)              this.newAppt.errors.push('Date is required.');
            if (!this.newAppt.time)              this.newAppt.errors.push('Time is required.');
            if (!this.newAppt.notes.trim())      this.newAppt.errors.push('Notes are required.');
            if (this.newAppt.type === 'treatment') {
                if (!this.newAppt.treatmentCategoryId) this.newAppt.errors.push('Please select a treatment category.');
                if (!this.newAppt.treatmentId)         this.newAppt.errors.push('Please select a treatment.');
            }
            if (this.newAppt.errors.length) return;

            this.newAppt.submitting = true;
            try {
                const res = await fetch('/appointments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        patient_id:             this.newAppt.patientId,
                        doctor_id:              this.newAppt.doctorId,
                        appointment_date:       this.newAppt.date,
                        appointment_time:       this.newAppt.time,
                        duration_minutes:       this.newAppt.duration,
                        type:                   this.newAppt.type,
                        notes:                  this.newAppt.notes,
                        treatment_category_id:  this.newAppt.treatmentCategoryId || null,
                        treatment_id:           this.newAppt.treatmentId || null,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.newAppt.open = false;
                    await this.fetchAppointments();
                } else {
                    if (data.errors) {
                        this.newAppt.errors = Object.values(data.errors).flat();
                    } else {
                        this.newAppt.errors = [data.message ?? 'Something went wrong.'];
                    }
                }
            } catch(e) {
                this.newAppt.errors = ['Network error. Please try again.'];
            } finally {
                this.newAppt.submitting = false;
            }
        },

        // ── Status update ─────────────────────────────────────────────

        async updateStatus(appt, status) {
            if (!appt) return;
            try {
                const res = await fetch(`/appointments/${appt.id}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ status }),
                });
                if (res.ok) {
                    const idx = this.appointments.findIndex(a => a.id === appt.id);
                    if (idx !== -1) this.appointments[idx].status = status;
                    this.drawer.appt = { ...appt, status };
                }
            } catch(e) { alert('Status update failed.'); }
        },

        // ── Calendar helpers ──────────────────────────────────────────

        isoDate(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        },

        isToday(d) {
            const t = new Date();
            return d.getFullYear() === t.getFullYear() && d.getMonth() === t.getMonth() && d.getDate() === t.getDate();
        },

        formatHour(h) {
            if (h === 12) return '12 PM';
            if (h === 0)  return '12 AM';
            return h > 12 ? (h - 12) + ' PM' : h + ' AM';
        },

        nowLineTop() {
            const now = new Date();
            const startHour = 8;
            const offset = (now.getHours() - startHour) * 64 + (now.getMinutes() / 60) * 64;
            return 40 + Math.max(0, offset);
        },

        headerLabel() {
            if (this.view === 'day') return this.currentDate.toLocaleDateString('en-IN', {weekday:'long', day:'numeric', month:'long', year:'numeric'});
            if (this.view === 'week') {
                const wd = this.weekDays();
                const first = wd[0].date.toLocaleDateString('en-IN', {day:'numeric', month:'short'});
                const last  = wd[6].date.toLocaleDateString('en-IN', {day:'numeric', month:'short', year:'numeric'});
                return `${first} – ${last}`;
            }
            return this.currentDate.toLocaleDateString('en-IN', {month:'long', year:'numeric'});
        },

        dayViewHeader() {
            return this.currentDate.toLocaleDateString('en-IN', {weekday:'long', day:'numeric', month:'short'});
        },

        prev() {
            const d = new Date(this.currentDate);
            if (this.view === 'day')   d.setDate(d.getDate() - 1);
            if (this.view === 'week')  d.setDate(d.getDate() - 7);
            if (this.view === 'month') d.setMonth(d.getMonth() - 1);
            this.currentDate = d;
            this.fetchAppointments();
        },

        next() {
            const d = new Date(this.currentDate);
            if (this.view === 'day')   d.setDate(d.getDate() + 1);
            if (this.view === 'week')  d.setDate(d.getDate() + 7);
            if (this.view === 'month') d.setMonth(d.getMonth() + 1);
            this.currentDate = d;
            this.fetchAppointments();
        },

        goToday() {
            this.currentDate = new Date();
            this.fetchAppointments();
        },

        goDay(d) { this.currentDate = d; this.view = 'day'; },

        weekDays() {
            const days = [];
            const d = new Date(this.currentDate);
            const dow = d.getDay();
            d.setDate(d.getDate() - dow);
            for (let i = 0; i < 7; i++) {
                const day = new Date(d);
                days.push({
                    date: day,
                    iso: this.isoDate(day),
                    dayName: day.toLocaleDateString('en-IN', {weekday:'short'}),
                    dayNum: day.getDate(),
                });
                d.setDate(d.getDate() + 1);
            }
            return days;
        },

        monthCells() {
            const cells = [];
            const y = this.currentDate.getFullYear();
            const m = this.currentDate.getMonth();
            const first = new Date(y, m, 1);
            const last  = new Date(y, m + 1, 0);
            for (let i = 0; i < first.getDay(); i++) {
                const d = new Date(y, m, -first.getDay() + i + 1);
                cells.push({ date: d, iso: this.isoDate(d), day: d.getDate(), currentMonth: false });
            }
            for (let i = 1; i <= last.getDate(); i++) {
                const d = new Date(y, m, i);
                cells.push({ date: d, iso: this.isoDate(d), day: i, currentMonth: true });
            }
            const rem = 7 - (cells.length % 7);
            if (rem < 7) {
                for (let i = 1; i <= rem; i++) {
                    const d = new Date(y, m + 1, i);
                    cells.push({ date: d, iso: this.isoDate(d), day: i, currentMonth: false });
                }
            }
            return cells;
        },

        dayAppointments() {
            const iso = this.isoDate(this.currentDate);
            return this.appointments.filter(a => a.appointment_date === iso)
                .sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
        },

        weekCellAppts(date, hour) {
            const iso = this.isoDate(date);
            return this.appointments.filter(a => {
                if (a.appointment_date !== iso) return false;
                const h = parseInt(a.appointment_time.split(':')[0]);
                return h === hour;
            });
        },

        monthCellAppts(date) {
            const iso = this.isoDate(date);
            return this.appointments.filter(a => a.appointment_date === iso)
                .sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
        },

        apptStyle(appt) {
            const [hh, mm] = appt.appointment_time.split(':').map(Number);
            const startHour = 8;
            const top = 40 + (hh - startHour) * 64 + (mm / 60) * 64;
            const height = Math.max(28, ((appt.duration_minutes || 30) / 60) * 64 - 2);
            return `top:${top}px; height:${height}px;`;
        },
    }));
});
</script>
@endpush
@endsection