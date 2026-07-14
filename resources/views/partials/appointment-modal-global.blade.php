{{--
┌─────────────────────────────────────────────────────────────────────────┐
│  GLOBAL APPOINTMENT MODAL                                               │
│  resources/views/partials/appointment-modal-global.blade.php            │
│                                                                         │
│  Included once in layouts/app.blade.php — available on every page.     │
│                                                                         │
│  TRIGGER (any page):                                                    │
│    window.openAppointmentModal()                                        │
│    window.openAppointmentModal('walkin')                                │
│    window.openAppointmentModal('appointment', '2026-06-15', patientId)  │
│    window.dispatchEvent(new CustomEvent('df:open-appointment', {        │
│      detail: { tab:'appointment', date:'2026-06-15', patientId: 5 }     │
│    }))                                                                  │
│                                                                         │
│  CALLBACK after successful booking:                                     │
│    window.__dfApptOnSuccess = function(appointment) { ... }             │
└─────────────────────────────────────────────────────────────────────────┘
--}}

<div
    x-data="dfAppointmentModal()"
    x-on:df:open-appointment.window="openFromEvent($event)"
    x-cloak
>
    {{-- ── Backdrop ──────────────────────────────────────────────────── --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9990] bg-black/50 backdrop-blur-sm flex items-center justify-center p-4"
        x-on:click.self="close()"
        style="display:none;"
    >
        {{-- ── Modal Panel ─────────────────────────────────────────── --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative w-full max-w-lg bg-white shadow-2xl flex flex-col max-h-[92vh]"
            x-on:click.stop
            style="display:none;"
        >
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 bg-[#380740] flex-shrink-0">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                    <h2 class="text-lg text-white font-semibold" style="font-family:'Cormorant Garamond',serif">
                        <span x-text="editMode ? 'Edit Appointment' : (activeTab === 'walkin' ? 'Walk-In' : (activeTab === 'block' ? 'Block Doctor Slot' : 'New Appointment'))"></span>
                    </h2>
                </div>
                <button x-on:click="close()" class="text-white/60 hover:text-white transition-colors p-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Tabs --}}
            <div class="flex border-b border-[#e8d5f0] flex-shrink-0 bg-white">
                <button type="button"
                    x-on:click="setTab('appointment')"
                    :class="activeTab === 'appointment' ? 'border-b-2 border-[#6a0f70] text-[#380740] font-semibold' : 'text-gray-400 hover:text-[#6a0f70]'"
                    class="flex-1 py-3 text-xs uppercase tracking-widest transition-all"
                    style="font-family:'Inter',sans-serif">
                    Appointment
                </button>
                <button type="button"
                    x-on:click="setTab('walkin')"
                    :class="activeTab === 'walkin' ? 'border-b-2 border-[#6a0f70] text-[#380740] font-semibold' : 'text-gray-400 hover:text-[#6a0f70]'"
                    class="flex-1 py-3 text-xs uppercase tracking-widest transition-all border-l border-[#e8d5f0]"
                    style="font-family:'Inter',sans-serif">
                    Walk-In
                </button>
                <button type="button"
                    x-on:click="setTab('block')"
                    :class="activeTab === 'block' ? 'border-b-2 border-red-500 text-red-700 font-semibold' : 'text-gray-400 hover:text-red-600'"
                    class="flex-1 py-3 text-xs uppercase tracking-widest transition-all border-l border-[#e8d5f0]"
                    style="font-family:'Inter',sans-serif">
                    Block Slot
                </button>
            </div>

            {{-- ═══════════════════════════════════════════════════
                 SUCCESS STATE (shared)
            ═══════════════════════════════════════════════════ --}}
            <div x-show="success" class="flex-1 flex flex-col items-center justify-center py-16 px-6">
                <div class="w-16 h-16 rounded-full bg-green-50 flex items-center justify-center mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-xl font-semibold text-[#380740] mb-1" style="font-family:'Cormorant Garamond',serif">
                    <span x-text="activeTab === 'walkin' ? 'Walk-In Added!' : (activeTab === 'block' ? 'Slot Blocked!' : 'Appointment Booked!')"></span>
                </p>
                <p class="text-xs text-gray-400" style="font-family:'Inter',sans-serif">Updating your schedule…</p>
            </div>

            {{-- ═══════════════════════════════════════════════════
                 TAB: APPOINTMENT
            ═══════════════════════════════════════════════════ --}}
            <div x-show="!success && activeTab === 'appointment'" class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

                {{-- General error --}}
                <template x-if="errors._general">
                    <div class="px-3 py-2 bg-red-50 border border-red-200 text-red-600 text-xs" style="font-family:'Inter',sans-serif">
                        <span x-text="errors._general"></span>
                    </div>
                </template>

                {{-- Patient Search --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Patient <span class="text-red-400">*</span>
                    </label>

                    {{-- Selected patient chip --}}
                    <div x-show="appt.patientId" class="flex items-center gap-2 px-3 py-2 bg-[#f5eef9] border border-[#c084d0]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#6a0f70] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/>
                        </svg>
                        <span class="text-sm text-[#380740] flex-1" x-text="appt.patientName" style="font-family:'Inter',sans-serif"></span>
                        <span class="text-xs text-gray-400" x-text="appt.patientPhone" style="font-family:'Inter',sans-serif"></span>
                        <button type="button" x-on:click="clearPatient()" class="text-gray-400 hover:text-red-500 transition ml-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Search input --}}
                    <div x-show="!appt.patientId" class="relative">
                        <input type="text"
                            x-model="appt.patientSearch"
                            x-on:input.debounce.300ms="searchPatients()"
                            placeholder="Search by name or phone…"
                            class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition pr-8"
                            style="font-family:'Inter',sans-serif"
                            :class="errors.patient_id ? 'border-red-400' : 'border-gray-200'"
                        />
                        <div x-show="patientLoading" class="absolute right-3 top-2.5">
                            <svg class="animate-spin w-4 h-4 text-[#6a0f70]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                        </div>
                        <div x-show="patientResults.length > 0"
                             class="absolute z-50 w-full bg-white border border-[#e8d5f0] shadow-lg mt-0.5 max-h-48 overflow-y-auto">
                            <template x-for="p in patientResults" :key="p.id">
                                <button type="button"
                                    x-on:click="selectPatient(p)"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-[#faf5fc] text-left transition border-b border-[#f0e4f5] last:border-0">
                                    <div class="w-7 h-7 rounded-full bg-[#e8d5f0] flex items-center justify-center shrink-0">
                                        <span class="text-xs font-semibold text-[#6a0f70]" x-text="p.name.charAt(0).toUpperCase()"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-[#380740]" x-text="p.name" style="font-family:'Inter',sans-serif"></p>
                                        <p class="text-xs text-gray-400" x-text="p.phone" style="font-family:'Inter',sans-serif"></p>
                                    </div>
                                </button>
                            </template>
                        </div>

                        {{-- No results found → offer to add new patient --}}
                        <div x-show="!patientLoading && patientResults.length === 0 && appt.patientSearch.length >= 2"
                             class="absolute z-50 w-full bg-white border border-[#e8d5f0] shadow-lg mt-0.5">
                            <div class="px-3 py-2.5 text-xs text-gray-400 border-b border-[#f0e4f5]" style="font-family:'Inter',sans-serif">
                                No patient found for "<span x-text="appt.patientSearch" class="font-medium text-gray-600"></span>"
                            </div>
                            <button type="button"
                                x-on:click="showAddPatient = true"
                                class="w-full flex items-center gap-2 px-3 py-2.5 hover:bg-[#faf5fc] text-left transition text-sm font-medium text-[#6a0f70]"
                                style="font-family:'Inter',sans-serif">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add New Patient
                            </button>
                        </div>
                    </div>

                    {{-- Quick add patient form --}}
                    <div x-show="showAddPatient" x-cloak
                         class="mt-2 border border-[#e8d5f0] bg-[#fdf8ff] p-3 rounded-sm">
                        <p class="text-[10px] uppercase tracking-widest text-[#6a0f70] font-semibold mb-2" style="font-family:'Inter',sans-serif">
                            Quick Register Patient
                        </p>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <div>
                                <label class="block text-[10px] text-gray-500 mb-0.5" style="font-family:'Inter',sans-serif">First Name <span class="text-red-400">*</span></label>
                                <input type="text" x-model="newPt.firstName" placeholder="First name"
                                    class="w-full border border-gray-200 px-2 py-1.5 text-sm focus:outline-none focus:border-[#6a0f70] transition"
                                    style="font-family:'Inter',sans-serif" />
                            </div>
                            <div>
                                <label class="block text-[10px] text-gray-500 mb-0.5" style="font-family:'Inter',sans-serif">Last Name <span class="text-red-400">*</span></label>
                                <input type="text" x-model="newPt.lastName" placeholder="Last name"
                                    class="w-full border border-gray-200 px-2 py-1.5 text-sm focus:outline-none focus:border-[#6a0f70] transition"
                                    style="font-family:'Inter',sans-serif" />
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="block text-[10px] text-gray-500 mb-0.5" style="font-family:'Inter',sans-serif">Mobile <span class="text-red-400">*</span></label>
                            <input type="text" x-model="newPt.phone" placeholder="Mobile number"
                                class="w-full border border-gray-200 px-2 py-1.5 text-sm focus:outline-none focus:border-[#6a0f70] transition"
                                style="font-family:'Inter',sans-serif" />
                        </div>
                        <div x-show="newPt.error" class="text-xs text-red-500 mb-2" x-text="newPt.error" style="font-family:'Inter',sans-serif"></div>

                        {{-- Duplicate phone prompt --}}
                        <div x-show="dupePt" x-cloak class="mb-2 border border-amber-300 bg-amber-50 rounded-sm p-2.5">
                            <p class="text-xs font-medium text-amber-800 mb-1" style="font-family:'Inter',sans-serif">
                                This number is already registered to:
                            </p>
                            <p class="text-sm font-semibold text-[#380740] mb-2" x-text="dupePt ? dupePt.name + ' (' + dupePt.phone + ')' : ''" style="font-family:'Inter',sans-serif"></p>
                            <div class="flex gap-2">
                                <button type="button"
                                    x-on:click="selectPatient(dupePt); showAddPatient = false; dupePt = null; newPt = { firstName:'', lastName:'', phone:'', saving:false, error:'' }"
                                    class="flex-1 bg-amber-600 text-white text-xs py-1.5 px-2 hover:bg-amber-700 transition"
                                    style="font-family:'Inter',sans-serif">
                                    Use This Patient
                                </button>
                                <button type="button"
                                    x-on:click="dupePt = null; newPt.phone = ''"
                                    class="flex-1 border border-amber-400 text-amber-700 text-xs py-1.5 px-2 hover:bg-amber-100 transition"
                                    style="font-family:'Inter',sans-serif">
                                    Enter Different Number
                                </button>
                            </div>
                        </div>

                        <div x-show="!dupePt" class="flex gap-2">
                            <button type="button"
                                x-on:click="addQuickPatient()"
                                :disabled="newPt.saving"
                                class="flex-1 bg-[#6a0f70] text-white text-xs py-1.5 px-3 hover:bg-[#580c5e] transition disabled:opacity-60"
                                style="font-family:'Inter',sans-serif">
                                <span x-show="!newPt.saving">Register & Select</span>
                                <span x-show="newPt.saving">Saving…</span>
                            </button>
                            <button type="button"
                                x-on:click="showAddPatient = false; newPt = { firstName:'', lastName:'', phone:'', saving:false, error:'' }; dupePt = null"
                                class="px-3 py-1.5 text-xs text-gray-500 border border-gray-200 hover:bg-gray-50 transition"
                                style="font-family:'Inter',sans-serif">
                                Cancel
                            </button>
                        </div>
                    </div>
                    <template x-if="errors.patient_id">
                        <p class="text-xs text-red-500 mt-1" x-text="errors.patient_id" style="font-family:'Inter',sans-serif"></p>
                    </template>
                </div>

                {{-- Date + Time --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                            Date <span class="text-red-400">*</span>
                        </label>
                        <input type="date" x-model="appt.date"
                            class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                            style="font-family:'Inter',sans-serif"
                            :class="errors.appointment_date ? 'border-red-400' : 'border-gray-200'"
                        />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                            Time <span class="text-red-400">*</span>
                        </label>
                        <input type="time" x-model="appt.time"
                            class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                            style="font-family:'Inter',sans-serif"
                            :class="errors.appointment_time ? 'border-red-400' : 'border-gray-200'"
                        />
                    </div>
                </div>

                {{-- Doctor + Duration --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                            Doctor <span class="text-red-400">*</span>
                        </label>
                        <select x-model="appt.doctorId"
                            x-on:change="checkConflict()"
                            class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white"
                            style="font-family:'Inter',sans-serif"
                            :class="errors.doctor_id ? 'border-red-400' : 'border-gray-200'"
                        >
                            <option value="">— Select —</option>
                            <template x-for="doc in doctors" :key="doc.id">
                                <option :value="doc.id" x-text="doc.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                            Duration
                        </label>
                        <select x-model="appt.duration"
                            class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white"
                            style="font-family:'Inter',sans-serif"
                        >
                            <option value="15">15 min</option>
                            <option value="30">30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">60 min</option>
                            <option value="90">90 min</option>
                            <option value="120">120 min</option>
                        </select>
                    </div>
                </div>

                {{-- Conflict warning --}}
                <div x-show="conflict" class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 text-amber-700 text-xs" style="font-family:'Inter',sans-serif">
                    <span></span>
                    <span x-text="conflictText"></span>
                </div>

                {{-- Type --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Type <span class="text-red-400">*</span>
                    </label>
                    <div class="flex border border-[#6a0f70]/20">
                        <button type="button"
                            x-on:click="appt.type = 'consultation'; appt.treatmentCategoryId = ''; appt.treatmentId = ''"
                            :class="appt.type === 'consultation' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                            class="flex-1 py-2 text-xs transition-all" style="font-family:'Inter',sans-serif">
                            Consultation
                        </button>
                        <button type="button"
                            x-on:click="appt.type = 'treatment'"
                            :class="appt.type === 'treatment' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                            class="flex-1 py-2 text-xs transition-all border-l border-[#6a0f70]/20" style="font-family:'Inter',sans-serif">
                            Treatment
                        </button>
                        <button type="button"
                            x-on:click="appt.type = 'follow-up'"
                            :class="appt.type === 'follow-up' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                            class="flex-1 py-2 text-xs transition-all border-l border-[#6a0f70]/20" style="font-family:'Inter',sans-serif">
                            Follow-Up
                        </button>
                    </div>
                </div>

                {{-- Treatment section (only when type = treatment) --}}
                <div x-show="appt.type === 'treatment'"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="border border-[#6a0f70]/15 bg-[#f5eef9]/40 p-4 space-y-3">
                    <p class="text-[10px] uppercase tracking-widest text-[#380740]/40" style="font-family:'Inter',sans-serif">Treatment Details</p>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Treatment Category</label>
                        <select x-model="appt.treatmentCategoryId"
                            x-on:change="loadTreatments(appt.treatmentCategoryId); appt.treatmentId = ''"
                            class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] bg-white"
                            style="font-family:'Inter',sans-serif">
                            <option value="">— Select Category —</option>
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="cat.id" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>
                    <div x-show="appt.treatmentCategoryId">
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Treatment</label>
                        <select x-model="appt.treatmentId"
                            class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] bg-white"
                            style="font-family:'Inter',sans-serif">
                            <option value="">— Select Treatment —</option>
                            <template x-for="t in treatments" :key="t.id">
                                <option :value="t.id" x-text="t.name"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Treatment Category (consultation/followup - optional) --}}
                <div x-show="appt.type !== 'treatment'">
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Treatment Category <span class="text-gray-300 font-normal normal-case">optional</span></label>
                    <select x-model="appt.treatmentCategoryId"
                        class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white"
                        style="font-family:'Inter',sans-serif">
                        <option value="">— Select —</option>
                        <template x-for="cat in categories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Notes / Chief Complaint</label>
                    <textarea x-model="appt.notes" rows="2"
                        placeholder="e.g. Toothache upper left, sensitivity to cold…"
                        class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition resize-none"
                        style="font-family:'Inter',sans-serif"></textarea>
                </div>

                {{-- Operatory (only shown if clinic has operatories configured) --}}
                <div x-show="operatories.length > 0" x-cloak>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Operatory <span class="text-gray-300 font-normal normal-case">optional</span>
                    </label>
                    <select x-model="appt.operatoryId"
                        class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white"
                        style="font-family:'Inter',sans-serif">
                        <option value="">— None —</option>
                        <template x-for="op in operatories" :key="op.id">
                            <option :value="op.id" x-text="op.name"></option>
                        </template>
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1" style="font-family:'Inter',sans-serif">
                        Can be changed at check-in if needed.
                    </p>
                </div>

            </div>{{-- /tab-appointment --}}

            {{-- ═══════════════════════════════════════════════════
                 TAB: WALK-IN
            ═══════════════════════════════════════════════════ --}}
            <div x-show="!success && activeTab === 'walkin'" class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

                {{-- General error --}}
                <template x-if="errors._general">
                    <div class="px-3 py-2 bg-red-50 border border-red-200 text-red-600 text-xs" style="font-family:'Inter',sans-serif">
                        <span x-text="errors._general"></span>
                    </div>
                </template>

                {{-- Walk-in mode toggle --}}
                <div class="flex border border-[#6a0f70]/20">
                    <button type="button"
                        x-on:click="wi.isNewPatient = false"
                        :class="!wi.isNewPatient ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                        class="flex-1 py-2 text-xs transition-all" style="font-family:'Inter',sans-serif">
                        Existing Patient
                    </button>
                    <button type="button"
                        x-on:click="wi.isNewPatient = true"
                        :class="wi.isNewPatient ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                        class="flex-1 py-2 text-xs transition-all border-l border-[#6a0f70]/20" style="font-family:'Inter',sans-serif">
                        New Patient
                    </button>
                </div>

                {{-- Existing patient search --}}
                <div x-show="!wi.isNewPatient">
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Patient <span class="text-red-400">*</span>
                    </label>
                    <div x-show="wi.patientId" class="flex items-center gap-2 px-3 py-2 bg-[#f5eef9] border border-[#c084d0]">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#6a0f70] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/>
                        </svg>
                        <span class="text-sm text-[#380740] flex-1" x-text="wi.patientName" style="font-family:'Inter',sans-serif"></span>
                        <button type="button" x-on:click="wi.patientId = ''; wi.patientName = ''; wi.patientSearch = ''" class="text-gray-400 hover:text-red-500 transition">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div x-show="!wi.patientId" class="relative">
                        <input type="text"
                            x-model="wi.patientSearch"
                            x-on:input.debounce.300ms="searchWiPatients()"
                            placeholder="Search by name or phone…"
                            class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                            style="font-family:'Inter',sans-serif"
                        />
                        <div x-show="wiPatientResults.length > 0"
                             class="absolute z-50 w-full bg-white border border-[#e8d5f0] shadow-lg mt-0.5 max-h-48 overflow-y-auto">
                            <template x-for="p in wiPatientResults" :key="p.id">
                                <button type="button"
                                    x-on:click="wi.patientId = p.id; wi.patientName = p.name; wi.patientSearch = p.name; wiPatientResults = []"
                                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-[#faf5fc] text-left border-b border-[#f0e4f5] last:border-0">
                                    <div class="w-6 h-6 rounded-full bg-[#e8d5f0] flex items-center justify-center shrink-0">
                                        <span class="text-xs font-semibold text-[#6a0f70]" x-text="p.name.charAt(0)"></span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-[#380740]" x-text="p.name" style="font-family:'Inter',sans-serif"></p>
                                        <p class="text-xs text-gray-400" x-text="p.phone" style="font-family:'Inter',sans-serif"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- New patient fields --}}
                <div x-show="wi.isNewPatient" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">First Name <span class="text-red-400">*</span></label>
                            <input type="text" x-model="wi.firstName" placeholder="Priya"
                                class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                                style="font-family:'Inter',sans-serif" />
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Last Name <span class="text-red-400">*</span></label>
                            <input type="text" x-model="wi.lastName" placeholder="Sharma"
                                class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                                style="font-family:'Inter',sans-serif" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Mobile <span class="text-red-400">*</span></label>
                        <input type="tel" x-model="wi.mobile" placeholder="9876543210"
                            class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                            style="font-family:'Inter',sans-serif" />
                    </div>
                </div>

                {{-- Doctor + Time --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Doctor</label>
                        <select x-model="wi.doctorId"
                            class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white"
                            style="font-family:'Inter',sans-serif">
                            <option value="">— Select —</option>
                            <template x-for="doc in doctors" :key="doc.id">
                                <option :value="doc.id" x-text="doc.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Time</label>
                        <input type="time" x-model="wi.time"
                            class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                            style="font-family:'Inter',sans-serif" />
                    </div>
                </div>

                {{-- Treatment Category --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Treatment Category</label>
                    <select x-model="wi.treatmentCategoryId"
                        class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white"
                        style="font-family:'Inter',sans-serif">
                        <option value="">— Select —</option>
                        <template x-for="cat in categories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Notes</label>
                    <input type="text" x-model="wi.notes"
                        placeholder="Chief complaint or reason for visit"
                        class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                        style="font-family:'Inter',sans-serif" />
                </div>

            </div>{{-- /tab-walkin --}}

            {{-- ═══════════════════════════════════════════════════
                 TAB: BLOCK SLOT
            ═══════════════════════════════════════════════════ --}}
            <div x-show="!success && activeTab === 'block'" class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

                {{-- Info banner --}}
                <div class="flex items-start gap-2 px-3 py-2.5 bg-red-50 border border-red-200 text-red-700 text-xs" style="font-family:'Inter',sans-serif">
                    <span class="text-base leading-none mt-0.5"></span>
                    <span>Blocked slots prevent any appointment from being scheduled for that doctor during the selected time range.</span>
                </div>

                {{-- General error --}}
                <template x-if="errors._general">
                    <div class="px-3 py-2 bg-red-50 border border-red-200 text-red-600 text-xs" style="font-family:'Inter',sans-serif">
                        <span x-text="errors._general"></span>
                    </div>
                </template>

                {{-- Doctor --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Doctor <span class="text-red-400">*</span>
                    </label>
                    <select x-model="blk.doctorId"
                        class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-red-400 transition bg-white"
                        style="font-family:'Inter',sans-serif"
                        :class="errors.doctor_id ? 'border-red-400' : 'border-gray-200'">
                        <option value="">— Select Doctor —</option>
                        <template x-for="doc in doctors" :key="doc.id">
                            <option :value="doc.id" x-text="doc.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Date --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Date <span class="text-red-400">*</span>
                    </label>
                    <input type="date" x-model="blk.date"
                        class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-red-400 transition"
                        style="font-family:'Inter',sans-serif"
                        :class="errors.block_date ? 'border-red-400' : 'border-gray-200'"
                    />
                </div>

                {{-- From / To time --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                            From <span class="text-red-400">*</span>
                        </label>
                        <input type="time" x-model="blk.startTime"
                            class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-red-400 transition"
                            style="font-family:'Inter',sans-serif"
                            :class="errors.start_time ? 'border-red-400' : 'border-gray-200'"
                        />
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                            To <span class="text-red-400">*</span>
                        </label>
                        <input type="time" x-model="blk.endTime"
                            class="w-full border px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-red-400 transition"
                            style="font-family:'Inter',sans-serif"
                            :class="errors.end_time ? 'border-red-400' : 'border-gray-200'"
                        />
                    </div>
                </div>

                {{-- Block Type --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">Block Type</label>
                    <div class="flex border border-red-200">
                        <button type="button"
                            x-on:click="blk.blockType = 'unavailable'"
                            :class="blk.blockType === 'unavailable' ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50'"
                            class="flex-1 py-2 text-xs transition-all" style="font-family:'Inter',sans-serif">
                            Unavailable
                        </button>
                        <button type="button"
                            x-on:click="blk.blockType = 'break'"
                            :class="blk.blockType === 'break' ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50'"
                            class="flex-1 py-2 text-xs transition-all border-l border-red-200" style="font-family:'Inter',sans-serif">
                            Break / Lunch
                        </button>
                        <button type="button"
                            x-on:click="blk.blockType = 'emergency'"
                            :class="blk.blockType === 'emergency' ? 'bg-red-600 text-white' : 'text-red-600 hover:bg-red-50'"
                            class="flex-1 py-2 text-xs transition-all border-l border-red-200" style="font-family:'Inter',sans-serif">
                            Emergency
                        </button>
                    </div>
                </div>

                {{-- Reason --}}
                <div>
                    <label class="block text-[10px] uppercase tracking-widest text-gray-500 mb-1" style="font-family:'Inter',sans-serif">
                        Reason <span class="text-gray-300 font-normal normal-case">optional</span>
                    </label>
                    <input type="text" x-model="blk.reason"
                        placeholder="e.g. Conference, Out of city, Team meeting…"
                        class="w-full border border-gray-200 px-3 py-2 text-sm text-gray-800 focus:outline-none focus:border-red-400 transition"
                        style="font-family:'Inter',sans-serif"
                    />
                </div>

            </div>{{-- /tab-block --}}

            {{-- ── Footer ──────────────────────────────────────────── --}}
            <div x-show="!success" class="px-6 py-4 border-t border-[#e8d5f0] flex items-center justify-between flex-shrink-0 bg-white">
                <button type="button" x-on:click="close()"
                    class="text-xs uppercase tracking-widest text-gray-400 hover:text-gray-600 transition"
                    style="font-family:'Inter',sans-serif">
                    Cancel
                </button>
                <button type="button"
                    x-on:click="activeTab === 'walkin' ? submitWalkin() : (activeTab === 'block' ? submitBlockSlot() : submitAppointment())"
                    :disabled="submitting"
                    :class="activeTab === 'block'
                        ? 'bg-red-600 hover:bg-red-700'
                        : 'bg-[#6a0f70] hover:bg-[#380740]'"
                    class="flex items-center gap-2 px-5 py-2.5 text-white text-xs font-semibold uppercase tracking-widest transition disabled:opacity-50"
                    style="font-family:'Inter',sans-serif">
                    <svg x-show="submitting" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                    </svg>
                    <span x-text="submitting ? 'Saving…' : (editMode ? 'Update Appointment' : (activeTab === 'walkin' ? 'Add Walk-In' : (activeTab === 'block' ? 'Block Slot' : 'Book Appointment')))"></span>
                </button>
            </div>

        </div>{{-- /modal panel --}}
    </div>{{-- /backdrop --}}
</div>{{-- /x-data --}}


<script>
const _dfBaseUrl = '{{ url('') }}';

function dfAppointmentModal() {
    return {
        open:        false,
        activeTab:   'appointment',
        success:     false,
        submitting:  false,
        conflict:    false,
        conflictText:'',
        errors:      {},
        editMode:    false,   // true when editing an existing appointment
        editId:      null,    // the appointment ID being edited

        // Appointment tab state
        appt: {
            patientId:           '',
            patientName:         '',
            patientPhone:        '',
            patientSearch:       '',
            doctorId:            '',
            date:                '',
            time:                '',
            duration:            '30',
            type:                'consultation',
            treatmentCategoryId: '',
            treatmentId:         '',
            notes:               '',
            operatoryId:         '',
        },

        // Walk-in tab state
        wi: {
            isNewPatient:        true,
            patientId:           '',
            patientName:         '',
            patientSearch:       '',
            firstName:           '',
            lastName:            '',
            mobile:              '',
            doctorId:            '',
            time:                new Date().toTimeString().slice(0, 5),
            treatmentCategoryId: '',
            notes:               '',
        },

        // Block slot tab state
        blk: {
            doctorId:  '',
            date:      '',
            startTime: '',
            endTime:   '',
            reason:    '',
            blockType: 'unavailable',
        },

        // Shared data (loaded once on first open)
        doctors:          [],
        categories:       [],
        treatments:       [],
        operatories:      [],
        patientResults:   [],
        wiPatientResults: [],
        patientLoading:   false,
        dataLoaded:       false,
        showAddPatient:   false,
        newPt:            { firstName:'', lastName:'', phone:'', saving:false, error:'' },
        dupePt:           null,   // set when server returns a duplicate phone patient

        // ── Open ──────────────────────────────────────────────────────
        async openModal(tab, date, patientId, time) {
            this.reset();
            this.activeTab = tab || 'appointment';
            if (date) this.appt.date = date.split('T')[0];
            if (time) this.appt.time = time;
            if (patientId) {
                // Pre-fill patient if ID given
                try {
                    const r = await fetch(`${_dfBaseUrl}/patients/search?q=${patientId}&by_id=1`, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                    });
                    const data = await r.json();
                    const list = Array.isArray(data) ? data : (data.data ?? []);
                    const p = list.find(x => x.id == patientId) || list[0];
                    if (p) this.selectPatient(p);
                } catch {}
            }
            if (!this.dataLoaded) await this.loadData();
            this.open = true;
        },

        openFromEvent(e) {
            const d = e.detail || {};
            if (d.editId) { this.openEdit(d.editId); return; }
            this.openModal(d.tab, d.date, d.patientId, d.time);
        },

        // ── Open in EDIT mode — pre-fill all fields from existing appointment ──
        async openEdit(id) {
            this.reset();
            this.editMode  = true;
            this.editId    = id;
            this.activeTab = 'appointment';
            if (!this.dataLoaded) await this.loadData();
            try {
                const r = await fetch(`${_dfBaseUrl}/appointments/${id}/quick`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                const apt = await r.json();
                this.appt.patientId            = apt.patient_id;
                this.appt.patientName          = apt.patient_name;
                this.appt.patientPhone         = apt.patient_phone || '';
                this.appt.patientSearch        = apt.patient_name;
                this.appt.doctorId             = apt.doctor_id;
                this.appt.date                 = apt.appointment_date;
                this.appt.time                 = apt.appointment_time;
                this.appt.duration             = String(apt.duration_minutes || 30);
                this.appt.type                 = apt.type || 'consultation';
                this.appt.treatmentCategoryId  = apt.treatment_category_id || '';
                this.appt.treatmentId          = apt.treatment_id || '';
                this.appt.notes                = apt.notes || '';
                this.appt.operatoryId          = apt.operatory_id || '';
                if (apt.treatment_category_id) await this.loadTreatments(apt.treatment_category_id);
            } catch {
                this.errors._general = 'Could not load appointment data.';
            }
            this.open = true;
        },

        // ── Close ─────────────────────────────────────────────────────
        close() {
            this.open = false;
        },

        // ── Tab switch ────────────────────────────────────────────────
        setTab(tab) {
            this.activeTab = tab;
            this.errors = {};
            this.conflict = false;
        },

        // ── Load doctors + categories once ────────────────────────────
        async loadData() {
            try {
                // Doctors from injected global (set in layout)
                if (window.__DF_DOCTORS && window.__DF_DOCTORS.length) {
                    this.doctors = window.__DF_DOCTORS;
                }
                // Categories via AJAX
                const r = await fetch(`${_dfBaseUrl}/treatment-categories`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                if (r.ok) {
                    const data = await r.json();
                    this.categories = Array.isArray(data) ? data : (data.data ?? []);
                }
                // Operatories via AJAX (only if clinic has any)
                const ro = await fetch(`${_dfBaseUrl}/settings/operatories`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                if (ro.ok) {
                    const od = await ro.json();
                    this.operatories = (Array.isArray(od) ? od : (od.data ?? [])).filter(o => o.is_active);
                    // Pre-select the first operatory by default
                    if (this.operatories.length > 0 && !this.appt.operatoryId) {
                        this.appt.operatoryId = this.operatories[0].id;
                    }
                }
                this.dataLoaded = true;
            } catch {}
        },

        // ── Load treatments for category ──────────────────────────────
        async loadTreatments(catId) {
            this.treatments = [];
            if (!catId) return;
            try {
                const r = await fetch(`${_dfBaseUrl}/treatment-categories/${catId}/treatments`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                if (r.ok) {
                    const data = await r.json();
                    this.treatments = Array.isArray(data) ? data : (data.data ?? []);
                }
            } catch {}
        },

        // ── Patient search (appointment tab) ──────────────────────────
        async searchPatients() {
            const q = this.appt.patientSearch;
            if (!q || q.length < 2) { this.patientResults = []; return; }
            this.patientLoading = true;
            try {
                const r = await fetch(`${_dfBaseUrl}/patients/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                if (r.ok) {
                    const data = await r.json();
                    this.patientResults = (Array.isArray(data) ? data : (data.data ?? [])).slice(0, 8);
                }
            } catch {}
            this.patientLoading = false;
        },

        selectPatient(p) {
            this.appt.patientId    = p.id;
            this.appt.patientName  = p.name;
            this.appt.patientPhone = p.phone || '';
            this.appt.patientSearch = p.name;
            this.patientResults    = [];
            this.showAddPatient    = false;
        },

        async addQuickPatient() {
            const pt = this.newPt;
            if (!pt.firstName.trim() || !pt.lastName.trim() || !pt.phone.trim()) {
                pt.error = 'First name, last name and mobile are required.';
                return;
            }
            pt.error  = '';
            pt.saving = true;
            this.dupePt = null;
            try {
                const r = await fetch(`${_dfBaseUrl}/patients/quick-store`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF,
                    },
                    body: JSON.stringify({
                        first_name: pt.firstName.trim(),
                        last_name:  pt.lastName.trim(),
                        phone:      pt.phone.trim(),
                    }),
                });
                const data = await r.json();
                if (r.status === 409 && data.duplicate) {
                    // Phone already belongs to an existing patient — show prompt
                    this.dupePt = data.patient;
                    return;
                }
                if (!r.ok) {
                    const firstError = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Failed to register patient.');
                    pt.error = firstError;
                    return;
                }
                // Select the newly created patient (server returns { ok, patient })
                this.selectPatient(data.patient);
                this.showAddPatient = false;
                this.newPt  = { firstName:'', lastName:'', phone:'', saving:false, error:'' };
                this.dupePt = null;
            } catch(e) {
                pt.error = 'Network error. Please try again.';
            } finally {
                pt.saving = false;
            }
        },

        clearPatient() {
            this.appt.patientId    = '';
            this.appt.patientName  = '';
            this.appt.patientPhone = '';
            this.appt.patientSearch = '';
            this.patientResults    = [];
        },

        // ── Patient search (walk-in tab) ──────────────────────────────
        async searchWiPatients() {
            const q = this.wi.patientSearch;
            if (!q || q.length < 2) { this.wiPatientResults = []; return; }
            try {
                const r = await fetch(`${_dfBaseUrl}/patients/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                if (r.ok) {
                    const data = await r.json();
                    this.wiPatientResults = (Array.isArray(data) ? data : (data.data ?? [])).slice(0, 8);
                }
            } catch {}
        },

        // ── Conflict check ────────────────────────────────────────────
        // Raw fetch, shared by the passive banner (checkConflict) and the
        // submit-time confirm prompt (confirmIfDoubleBooked below).
        async fetchConflictData(doctorId, date, time, duration, excludeId = null) {
            if (!doctorId || !date || !time) return null;
            try {
                const url = new URL(_dfBaseUrl + '/appointments/check-conflict', location.origin);
                url.searchParams.set('doctor_id',        doctorId);
                url.searchParams.set('appointment_date', date);
                url.searchParams.set('appointment_time', time);
                url.searchParams.set('duration_minutes', duration);
                if (excludeId) url.searchParams.set('exclude_id', excludeId);
                const r = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__DF_CSRF }
                });
                return await r.json();
            } catch {
                return null;
            }
        },

        async checkConflict() {
            const { doctorId, date, time, duration } = this.appt;
            const excludeId = this.editMode ? this.editId : null;
            const data = await this.fetchConflictData(doctorId, date, time, duration, excludeId);
            if (!data) return;
            if (data.has_conflict && data.conflicts.length) {
                const c = data.conflicts[0];
                this.conflictText = `Conflict with ${c.patient_name} at ${c.time} (${c.duration} min)`;
                this.conflict = true;
            } else {
                this.conflict = false;
            }
        },

        // Doctor may be double-booked on purpose (second chair, overlap
        // consult) — warn and let the user decide rather than hard-blocking.
        //
        // The server now ENFORCES the overlap rule (it previously did not), so
        // when the user knowingly confirms we must tell the server that by
        // sending allow_overlap. This sets this.allowOverlap, which the submit
        // bodies below include.
        async confirmIfDoubleBooked(doctorId, date, time, duration, excludeId = null) {
            this.allowOverlap = false;

            const data = await this.fetchConflictData(doctorId, date, time, duration, excludeId);
            if (!data || !data.has_conflict || !data.conflicts.length) return true;

            const c = data.conflicts[0];
            const doctorName = this.doctors.find(d => d.id == doctorId)?.name || 'This doctor';
            const proceed = confirm(
                `Dr. ${doctorName} already has an appointment with ${c.patient_name} at ${c.time} ` +
                `(${c.duration} min). Book this appointment for the same doctor anyway?`
            );

            this.allowOverlap = proceed;
            return proceed;
        },

        // ── Submit Appointment ────────────────────────────────────────
        async submitAppointment() {
            this.errors = {};
            if (!this.appt.patientId) { this.errors.patient_id = 'Patient is required'; return; }
            if (!this.appt.doctorId)  { this.errors.doctor_id  = 'Doctor is required'; return; }
            if (!this.appt.date)      { this.errors.appointment_date = 'Date is required'; return; }
            if (!this.appt.time)      { this.errors.appointment_time = 'Time is required'; return; }

            const okToBook = await this.confirmIfDoubleBooked(
                this.appt.doctorId, this.appt.date, this.appt.time, this.appt.duration,
                this.editMode ? this.editId : null
            );
            if (!okToBook) return;

            this.submitting = true;
            const url    = this.editMode ? `${_dfBaseUrl}/appointments/${this.editId}` : `${_dfBaseUrl}/appointments`;
            const method = this.editMode ? 'PATCH' : 'POST';
            try {
                const r = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF,
                    },
                    body: JSON.stringify({
                        patient_id:            this.appt.patientId,
                        doctor_id:             this.appt.doctorId,
                        appointment_date:      this.appt.date,
                        appointment_time:      this.appt.time,
                        duration_minutes:      parseInt(this.appt.duration) || 30,
                        type:                  this.appt.type,
                        treatment_category_id: this.appt.treatmentCategoryId || null,
                        treatment_id:          this.appt.treatmentId          || null,
                        notes:                 this.appt.notes,
                        operatory_id:          this.appt.operatoryId          || null,
                        allow_overlap:         !!this.allowOverlap,
                    }),
                });
                const data = await r.json();
                if (data.ok || data.success) {
                    this.success = true;
                    if (typeof window.__dfApptOnSuccess === 'function') {
                        window.__dfApptOnSuccess(data.appointment);
                    }
                    const evt = this.editMode ? 'df:appointment-updated' : 'df:appointment-booked';
                    window.dispatchEvent(new CustomEvent(evt, {
                        detail: { appointment: data.appointment }
                    }));
                    setTimeout(() => { this.close(); }, 1500);
                } else {
                    this.errors = data.errors ?? {};
                    if (data.message) this.errors._general = data.message;
                }
            } catch {
                this.errors._general = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        // ── Submit Walk-In ────────────────────────────────────────────
        async submitWalkin() {
            this.errors = {};
            // en-CA gives LOCAL YYYY-MM-DD. toISOString() is UTC — between
            // 00:00 and 05:29 IST it returned YESTERDAY while the time below is
            // local, so an early-hours walk-in landed on the wrong day-sheet.
            const today = new Date().toLocaleDateString('en-CA');

            let body = {
                appointment_date:      today,
                appointment_time:      this.wi.time || new Date().toTimeString().slice(0, 5),
                doctor_id:             this.wi.doctorId || null,
                treatment_category_id: this.wi.treatmentCategoryId || null,
                notes:                 this.wi.notes || '',
                is_walkin:             true,
            };

            if (this.wi.isNewPatient) {
                if (!this.wi.firstName || !this.wi.lastName || !this.wi.mobile) {
                    this.errors._general = 'First name, last name, and mobile are required.';
                    return;
                }
                body.first_name = this.wi.firstName;
                body.last_name  = this.wi.lastName;
                body.mobile     = this.wi.mobile;
            } else {
                if (!this.wi.patientId) {
                    this.errors._general = 'Please select a patient.';
                    return;
                }
                body.patient_id = this.wi.patientId;
            }

            if (this.wi.doctorId) {
                const okToBook = await this.confirmIfDoubleBooked(
                    this.wi.doctorId, today, body.appointment_time, 30
                );
                if (!okToBook) return;
                body.allow_overlap = !!this.allowOverlap;
            }

            this.submitting = true;
            try {
                const r = await fetch(`${_dfBaseUrl}/appointments`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF,
                    },
                    body: JSON.stringify(body),
                });
                const data = await r.json();
                if (data.ok || data.success) {
                    this.success = true;
                    window.dispatchEvent(new CustomEvent('df:appointment-booked', {
                        detail: { appointment: data.appointment, isWalkin: true }
                    }));
                    if (typeof window.__dfApptOnSuccess === 'function') {
                        window.__dfApptOnSuccess(data.appointment);
                    }
                    setTimeout(() => { this.close(); }, 1500);
                } else {
                    this.errors = data.errors ?? {};
                    if (data.message) this.errors._general = data.message;
                }
            } catch {
                this.errors._general = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        // ── Submit Block Slot ─────────────────────────────────────────
        async submitBlockSlot() {
            this.errors = {};
            if (!this.blk.doctorId)  { this.errors._general = 'Please select a doctor.'; return; }
            if (!this.blk.date)      { this.errors._general = 'Please select a date.'; return; }
            if (!this.blk.startTime) { this.errors._general = 'Start time is required.'; return; }
            if (!this.blk.endTime)   { this.errors._general = 'End time is required.'; return; }
            if (this.blk.startTime >= this.blk.endTime) {
                this.errors._general = '"To" time must be after "From" time.'; return;
            }

            this.submitting = true;
            try {
                const r = await fetch(`${_dfBaseUrl}/appointments/block-slot`, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': window.__DF_CSRF,
                    },
                    body: JSON.stringify({
                        doctor_id:  this.blk.doctorId,
                        block_date: this.blk.date,
                        start_time: this.blk.startTime,
                        end_time:   this.blk.endTime,
                        reason:     this.blk.reason    || null,
                        block_type: this.blk.blockType || 'unavailable',
                    }),
                });
                const data = await r.json();
                if (data.ok) {
                    this.success = true;
                    window.dispatchEvent(new CustomEvent('df:slot-blocked', { detail: data.slot }));
                    setTimeout(() => { this.close(); }, 1500);
                } else {
                    this.errors = data.errors ?? {};
                    if (data.message) this.errors._general = data.message;
                }
            } catch {
                this.errors._general = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        // ── Reset ─────────────────────────────────────────────────────
        reset() {
            this.success        = false;
            this.submitting     = false;
            this.conflict       = false;
            this.errors         = {};
            this.showAddPatient = false;
            this.newPt          = { firstName:'', lastName:'', phone:'', saving:false, error:'' };
            this.dupePt         = null;
            this.patientResults   = [];
            this.wiPatientResults = [];
            this.treatments = [];
            this.appt = {
                patientId: '', patientName: '', patientPhone: '', patientSearch: '',
                doctorId: '', date: new Date().toISOString().split('T')[0],
                time: new Date().toTimeString().slice(0, 5),
                duration: '30', type: 'consultation',
                treatmentCategoryId: '', treatmentId: '', notes: '',
                operatoryId: this.operatories.length > 0 ? this.operatories[0].id : '',
            };
            this.wi = {
                isNewPatient: true, patientId: '', patientName: '',
                patientSearch: '', firstName: '', lastName: '', mobile: '',
                doctorId: '', time: new Date().toTimeString().slice(0, 5),
                treatmentCategoryId: '', notes: '',
            };
            this.blk = {
                doctorId: '', date: new Date().toISOString().split('T')[0],
                startTime: '', endTime: '', reason: '', blockType: 'unavailable',
            };
        },
    };
}


// ── Global helper so any page can call window.openAppointmentModal(...) ──
window.openAppointmentModal = function(tab, date, patientId, time) {
    window.dispatchEvent(new CustomEvent('df:open-appointment', {
        detail: { tab: tab || 'appointment', date: date || null, patientId: patientId || null, time: time || null }
    }));
};

// ── Global helper to open the modal in EDIT mode ──────────────────────────
window.openEditAppointmentModal = function(id) {
    window.dispatchEvent(new CustomEvent('df:open-appointment', {
        detail: { editId: id }
    }));
};
</script>