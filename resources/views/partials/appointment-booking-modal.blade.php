{{--
    partials/appointment-booking-modal.blade.php
    Self-contained booking modal — works on any page.
    Triggered by: window.dispatchEvent(new CustomEvent('open-booking-modal'))
    Requires parent to have Alpine component with newAppt, treatmentCategories,
    timeSlots, doctors, searchPatients(), selectPatient(), filteredTreatments(),
    applyTreatmentDefaults(), submitAppointment() defined.
    Use via @include inside a div with x-data="huddleBooking"
--}}

{{-- Backdrop + Modal --}}
<div x-show="newAppt.open"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
     @click.self="newAppt.open=false"
     style="display:none;">

    <div class="bg-white w-full max-w-lg shadow-2xl border border-[#6a0f70]/20 max-h-[90vh] flex flex-col" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 bg-[#380740] flex-shrink-0">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-white/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <h2 class="font-['Cormorant_Garamond'] text-xl text-white font-semibold">Book Appointment</h2>
            </div>
            <button @click="newAppt.open=false" class="text-white/60 hover:text-white transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Errors --}}
        <div x-show="newAppt.errors && newAppt.errors.length" class="mx-6 mt-4 px-3 py-2 bg-red-50 border border-red-200 text-red-600 text-xs font-['DM_Sans'] flex-shrink-0">
            <template x-for="err in newAppt.errors" :key="err"><div x-text="err"></div></template>
        </div>

        {{-- Form body --}}
        <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

            {{-- Patient search --}}
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

            {{-- Mobile --}}
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

            {{-- Date + Time --}}
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

            {{-- Doctor + Duration --}}
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
                    <button type="button"
                            @click="newAppt.type='consultation'; newAppt.treatmentCategoryId=''; newAppt.treatmentId='';"
                            :class="newAppt.type==='consultation' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                            class="flex-1 py-2 text-xs font-['DM_Sans'] font-medium transition-all">Consultation</button>
                    <button type="button"
                            @click="newAppt.type='treatment'"
                            :class="newAppt.type==='treatment' ? 'bg-[#6a0f70] text-white' : 'text-[#6a0f70] hover:bg-[#6a0f70]/5'"
                            class="flex-1 py-2 text-xs font-['DM_Sans'] font-medium transition-all border-l border-[#6a0f70]/20">Treatment</button>
                </div>
            </div>

            {{-- Treatment section --}}
            <div x-show="newAppt.type === 'treatment'"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="border border-[#6a0f70]/15 bg-[#f5eef9]/40 p-4 space-y-3">
                <p class="text-[10px] font-['DM_Sans'] text-[#380740]/40 uppercase tracking-widest">Treatment Details</p>
                <div>
                    <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Treatment Category <span class="text-red-400">*</span></label>
                    <select x-model="newAppt.treatmentCategoryId"
                            @change="newAppt.treatmentId = ''"
                            class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="">— Select Category —</option>
                        <template x-for="cat in treatmentCategories" :key="cat.id">
                            <option :value="cat.id" x-text="cat.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Treatment <span class="text-red-400">*</span></label>
                    <select x-model="newAppt.treatmentId"
                            @change="applyTreatmentDefaults()"
                            :disabled="!newAppt.treatmentCategoryId"
                            class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white disabled:opacity-40">
                        <option value="">— Select Treatment —</option>
                        <template x-for="t in filteredTreatments()" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                    <p x-show="!newAppt.treatmentCategoryId" class="text-[10px] font-['DM_Sans'] text-[#380740]/30 mt-1">Select a category first</p>
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="text-[10px] font-['DM_Sans'] text-[#380740]/50 uppercase tracking-widest block mb-1">Notes <span class="text-red-400">*</span></label>
                <textarea x-model="newAppt.notes" rows="3"
                          placeholder="Reason for visit, patient concerns, instructions…"
                          class="w-full border border-[#6a0f70]/20 px-3 py-2 text-sm font-['DM_Sans'] text-[#380740] focus:outline-none focus:border-[#6a0f70] bg-white resize-none"
                          :class="newAppt.notes.trim() === '' && newAppt.errors.length ? 'border-red-300' : ''"></textarea>
            </div>

        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-[#6a0f70]/10 flex gap-2 flex-shrink-0">
            <button type="button" @click="newAppt.open=false"
                    class="flex-1 py-2.5 text-xs font-['DM_Sans'] font-medium border border-[#6a0f70]/30 text-[#6a0f70] hover:bg-[#6a0f70]/5 transition-all">
                Cancel
            </button>
            <button type="button"
                    @click="submitAppointment()"
                    :disabled="newAppt.submitting"
                    class="flex-1 py-2.5 text-xs font-['DM_Sans'] font-medium bg-[#6a0f70] text-white hover:bg-[#380740] transition-all disabled:opacity-50 flex items-center justify-center gap-2">
                <svg x-show="newAppt.submitting" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                <span x-text="newAppt.submitting ? 'Saving…' : 'Book Appointment'"></span>
            </button>
        </div>

    </div>
</div>
