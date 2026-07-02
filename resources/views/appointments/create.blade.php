@extends('layouts.app')

@section('content')
<div class="min-h-screen" style="background:#f5eef9;">
    <div class="max-w-3xl mx-auto px-6 py-10">

        {{-- Header --}}
        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('appointments.index') }}"
               class="flex items-center justify-center w-9 h-9 border border-[#6a0f70] text-[#6a0f70] hover:bg-[#6a0f70] hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </a>
            <div>
                <h1 class="font-['Cormorant_Garamond'] text-3xl font-semibold text-[#380740] leading-tight">
                    Book Appointment
                </h1>
                <p class="font-['DM_Sans'] text-sm text-[#6a0f70]/60 mt-0.5">Tulip Dental — Dombivli East</p>
            </div>
        </div>

        {{-- Form Card --}}
        <div class="bg-white border border-[#e8d5f0] shadow-sm"
             x-data="appointmentForm()"
             x-init="init()">

            {{-- Section: Patient --}}
            <div class="border-b border-[#e8d5f0] px-8 py-6">
                <h2 class="font-['Cormorant_Garamond'] text-lg font-semibold text-[#380740] mb-4 uppercase tracking-widest text-xs" style="font-family:'Inter',sans-serif;font-size:10px;letter-spacing:.12em;color:#6a0f70;">
                    Patient
                </h2>

                <div class="relative">
                    <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                        Search Patient <span class="text-red-500">*</span>
                    </label>

                    <div class="relative">
                        <input type="text"
                               x-model="patientQuery"
                               @input.debounce.300ms="searchPatients()"
                               @focus="showDropdown = true"
                               @keydown.escape="showDropdown = false"
                               @keydown.arrow-down.prevent="highlightNext()"
                               @keydown.arrow-up.prevent="highlightPrev()"
                               @keydown.enter.prevent="selectHighlighted()"
                               placeholder="Name or phone number…"
                               autocomplete="off"
                               class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 pr-10 text-[#380740] placeholder-[#b09ab8] focus:outline-none focus:border-[#6a0f70] transition-colors"
                               :class="patientId ? 'border-[#6a0f70] bg-[#faf5fc]' : ''">

                        <div class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6a0f70]/50">
                            <svg x-show="!searching" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                                <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            <svg x-show="searching" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                            </svg>
                        </div>
                    </div>

                    {{-- Dropdown --}}
                    <div x-show="showDropdown && results.length > 0"
                         @click.outside="showDropdown = false"
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute z-50 w-full bg-white border border-[#d4b8dc] shadow-lg mt-0.5 max-h-56 overflow-y-auto">
                        <template x-for="(p, i) in results" :key="p.id">
                            <div @click="selectPatient(p)"
                                 class="px-4 py-2.5 cursor-pointer font-['DM_Sans'] text-sm flex justify-between items-center transition-colors"
                                 :class="highlighted === i ? 'bg-[#f5eef9] text-[#380740]' : 'hover:bg-[#faf5fc] text-[#380740]'">
                                <span x-text="p.name" class="font-medium"></span>
                                <span x-text="p.phone" class="text-xs text-[#6a0f70]/60"></span>
                            </div>
                        </template>
                    </div>

                    {{-- No results --}}
                    <div x-show="showDropdown && patientQuery.length > 1 && results.length === 0 && !searching"
                         @click.outside="showDropdown = false"
                         class="absolute z-50 w-full bg-white border border-[#d4b8dc] shadow-lg mt-0.5 px-4 py-3 font-['DM_Sans'] text-sm text-[#6a0f70]/60">
                        No patients found.
                        <a href="{{ route('patients.create') }}" class="text-[#6a0f70] underline ml-1">Create new patient</a>
                    </div>

                    <input type="hidden" name="patient_id" :value="patientId">

                    {{-- Selected patient chip --}}
                    <div x-show="patientId" class="mt-2 inline-flex items-center gap-2 bg-[#f5eef9] border border-[#d4b8dc] px-3 py-1.5">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                             fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="square">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span x-text="selectedPatientName" class="font-['DM_Sans'] text-xs font-medium text-[#380740]"></span>
                        <button type="button" @click="clearPatient()" class="text-[#6a0f70]/50 hover:text-[#6a0f70] ml-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                                <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Section: Schedule --}}
            <div class="border-b border-[#e8d5f0] px-8 py-6">
                <h2 class="font-['DM_Sans'] text-[10px] font-semibold uppercase tracking-[.12em] text-[#6a0f70] mb-4">
                    Schedule
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {{-- Date --}}
                    <div>
                        <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                            Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date"
                               name="appointment_date"
                               x-model="apptDate"
                               :min="today"
                               class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 text-[#380740] focus:outline-none focus:border-[#6a0f70] transition-colors"
                               required>
                    </div>

                    {{-- Time --}}
                    <div>
                        <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                            Time <span class="text-red-500">*</span>
                        </label>
                        <select name="appointment_time"
                                class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 text-[#380740] focus:outline-none focus:border-[#6a0f70] transition-colors appearance-none"
                                required>
                            <option value="">— Select —</option>
                            @foreach($timeSlots as $slot)
                                <option value="{{ $slot }}">{{ $slot }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Duration --}}
                    <div>
                        <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                            Duration
                        </label>
                        <select name="duration_minutes"
                                class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 text-[#380740] focus:outline-none focus:border-[#6a0f70] transition-colors appearance-none">
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">60 min</option>
                            <option value="90">90 min</option>
                        </select>
                    </div>
                </div>
            </div>

            {{-- Section: Details --}}
            <div class="border-b border-[#e8d5f0] px-8 py-6">
                <h2 class="font-['DM_Sans'] text-[10px] font-semibold uppercase tracking-[.12em] text-[#6a0f70] mb-4">
                    Details
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    {{-- Doctor --}}
                    <div>
                        <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                            Doctor <span class="text-red-500">*</span>
                        </label>
                        <select name="doctor_id"
                                class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 text-[#380740] focus:outline-none focus:border-[#6a0f70] transition-colors appearance-none"
                                required>
                            <option value="">— Select Doctor —</option>
                            @foreach($doctors as $doctor)
                                <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Operatory (optional) --}}
                    @if($operatories->count() > 0)
                    <div>
                        <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                            Operatory
                        </label>
                        <select name="operatory_id"
                                class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 text-[#380740] focus:outline-none focus:border-[#6a0f70] transition-colors appearance-none">
                            <option value="">— None —</option>
                            @foreach($operatories as $op)
                                <option value="{{ $op->id }}">{{ $op->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    {{-- Type --}}
                    <div>
                        <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                            Type <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-0 border border-[#d4b8dc]" x-data="{ type: 'consultation' }">
                            <label class="flex-1 text-center cursor-pointer">
                                <input type="radio" name="type" value="consultation" class="sr-only"
                                       @change="type = 'consultation'" checked>
                                <span class="block font-['DM_Sans'] text-xs py-2.5 px-3 transition-colors"
                                      :class="type === 'consultation' ? 'bg-[#6a0f70] text-white' : 'bg-white text-[#380740] hover:bg-[#f5eef9]'">
                                    Consultation
                                </span>
                            </label>
                            <label class="flex-1 text-center cursor-pointer border-l border-[#d4b8dc]">
                                <input type="radio" name="type" value="treatment" class="sr-only"
                                       @change="type = 'treatment'">
                                <span class="block font-['DM_Sans'] text-xs py-2.5 px-3 transition-colors"
                                      :class="type === 'treatment' ? 'bg-[#6a0f70] text-white' : 'bg-white text-[#380740] hover:bg-[#f5eef9]'">
                                    Treatment
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Chief Complaint --}}
                <div>
                    <label class="block font-['DM_Sans'] text-xs font-medium text-[#380740]/70 mb-1.5 uppercase tracking-wide">
                        Chief Complaint
                    </label>
                    <textarea name="chief_complaint"
                              rows="2"
                              placeholder="Reason for visit…"
                              class="w-full font-['DM_Sans'] text-sm border border-[#d4b8dc] bg-white px-4 py-2.5 text-[#380740] placeholder-[#b09ab8] focus:outline-none focus:border-[#6a0f70] transition-colors resize-none"></textarea>
                </div>
            </div>

            {{-- Actions --}}
            <div class="px-8 py-5 flex items-center justify-between bg-[#faf5fc]">
                <a href="{{ route('appointments.index') }}"
                   class="font-['DM_Sans'] text-sm text-[#6a0f70]/60 hover:text-[#6a0f70] transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        form="appointment-form"
                        :disabled="!patientId || submitting"
                        class="font-['DM_Sans'] text-sm font-medium px-8 py-2.5 bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-2">
                    <svg x-show="submitting" class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
                    </svg>
                    <span x-text="submitting ? 'Booking…' : 'Book Appointment'"></span>
                </button>
            </div>

        </div>{{-- /card --}}

        {{-- Actual form wrapping everything (hidden, for submission) --}}
    </div>
</div>

{{-- Wrap the card in a real form --}}
<script>
    // Move form wrapper around the card via DOM after Alpine init
    // Instead, we use the form id trick below
</script>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('appointmentForm', () => ({
        patientQuery: '',
        patientId: null,
        selectedPatientName: '',
        results: [],
        searching: false,
        showDropdown: false,
        highlighted: -1,
        apptDate: '',
        today: '',
        submitting: false,

        init() {
            const d = new Date();
            const pad = n => String(n).padStart(2, '0');
            this.today = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
            this.apptDate = this.today;

            // Wrap the card content inside a real form
            const card = this.$el;
            const form = document.createElement('form');
            form.id = 'appointment-form';
            form.method = 'POST';
            form.action = '{{ route('appointments.store') }}';
            card.parentNode.insertBefore(form, card);
            form.appendChild(card);

            // CSRF
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.prepend(csrf);

            form.addEventListener('submit', () => { this.submitting = true; });
        },

        async searchPatients() {
            if (this.patientQuery.length < 2) {
                this.results = [];
                this.showDropdown = false;
                return;
            }
            this.searching = true;
            this.showDropdown = true;
            try {
                const res = await fetch(`{{ route('patients.search') }}?q=${encodeURIComponent(this.patientQuery)}&json=1`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.results = await res.json();
                this.highlighted = -1;
            } catch(e) {
                this.results 