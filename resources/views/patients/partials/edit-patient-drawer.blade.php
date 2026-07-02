{{-- Slide-in right drawer for editing patient details --}}
{{-- Requires x-data context from parent (patientProfile()) --}}

{{-- Backdrop --}}
<div
    x-show="editDrawerOpen"
    x-transition:enter="transition-opacity ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-on:click="editDrawerOpen = false"
    class="fixed inset-0 bg-black/30 z-40"
    style="display:none"
></div>

{{-- Drawer --}}
<div
    x-show="editDrawerOpen"
    x-transition:enter="transition transform ease-out duration-250"
    x-transition:enter-start="translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition transform ease-in duration-200"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="translate-x-full"
    class="fixed inset-y-0 right-0 w-full max-w-lg bg-white shadow-2xl z-50 flex flex-col"
    style="display:none"
>
    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-[#380740] text-white">
        <h2 class="text-base font-semibold" style="font-family: 'Cormorant Garamond', serif;">Edit Patient</h2>
        <button x-on:click="editDrawerOpen = false" class="text-white/70 hover:text-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    {{-- Scrollable form body --}}
    <div class="flex-1 overflow-y-auto">
        <form
            id="editPatientForm"
            x-on:submit.prevent="submitEditPatient()"
            class="divide-y divide-gray-100"
        >
            @csrf
            @method('PATCH')

            {{-- Personal --}}
            <div class="px-6 py-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Personal Information</h3>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" value="{{ $patient->name }}" required
                           class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Mobile <span class="text-red-400">*</span></label>
                        <input type="text" name="phone" value="{{ $patient->phone }}" required
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Email</label>
                        <input type="email" name="email" value="{{ $patient->email }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div x-data="{ dobUnknown: {{ $patient->dob_unknown ? 'true' : 'false' }} }">
                        <label class="block text-xs text-gray-500 mb-1">Date of Birth</label>
                        <input type="date" name="dob"
                               value="{{ $patient->date_of_birth?->format('Y-m-d') }}"
                               :disabled="dobUnknown"
                               :class="dobUnknown ? 'bg-gray-100 text-gray-400' : ''"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        <label class="flex items-center gap-1.5 mt-1.5 cursor-pointer">
                            <input type="checkbox" name="dob_unknown" value="1"
                                   x-model="dobUnknown"
                                   {{ $patient->dob_unknown ? 'checked' : '' }}
                                   class="accent-[#6a0f70]">
                            <span class="text-xs text-gray-400">DOB unknown</span>
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Gender</label>
                        <select name="gender" class="w-full text-sm border border-gray-200 px-3 py-2 bg-white focus:outline-none focus:border-[#6a0f70]">
                            <option value="">Select</option>
                            @foreach(['male'=>'Male','female'=>'Female','other'=>'Other','prefer_not_to_say'=>'Prefer not to say'] as $v => $l)
                                <option value="{{ $v }}" {{ $patient->gender === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Occupation</label>
                        <input type="text" name="occupation" value="{{ $patient->occupation }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
            </div>

            {{-- Address --}}
            <div class="px-6 py-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Address</h3>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Address</label>
                    <textarea name="address" rows="2"
                              class="w-full text-sm border border-gray-200 px-3 py-2 resize-none focus:outline-none focus:border-[#6a0f70]">{{ $patient->address }}</textarea>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">City</label>
                        <input type="text" name="city" value="{{ $patient->city }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">State</label>
                        <input type="text" name="state" value="{{ $patient->state }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Pincode</label>
                        <input type="text" name="pincode" value="{{ $patient->pincode }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
            </div>

            {{-- Clinical --}}
            <div class="px-6 py-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Clinical Context</h3>

                {{-- Habits checkboxes --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-2">Habits</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Smoking','Alcohol','Tobacco','Betel Nut','Bruxism','Mouth Breathing'] as $habit)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="habits[]" value="{{ $habit }}"
                                   {{ in_array($habit, $patient->habits ?? []) ? 'checked' : '' }}
                                   class="accent-[#6a0f70]">
                            <span class="text-xs text-gray-600">{{ $habit }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Medical Conditions --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-2">Medical Conditions</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Diabetes','Hypertension','Heart Disease','Asthma','Epilepsy','Thyroid Disorder','Kidney Disease','Liver Disease','HIV/AIDS','Hepatitis B/C','Osteoporosis','Pregnancy'] as $cond)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="medical_conditions[]" value="{{ $cond }}"
                                   {{ in_array($cond, $patient->medical_conditions ?? []) ? 'checked' : '' }}
                                   class="accent-[#6a0f70]">
                            <span class="text-xs text-gray-600">{{ $cond }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Medical Alerts (clinical flags needing immediate attention) --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-2">
                        Medical Alerts
                        <span class="text-gray-400">(shown prominently on profile)</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Blood Thinners / Anticoagulants','Diabetic on Insulin','Steroid Medication','Immunocompromised','Pacemaker / Cardiac Device','Allergic to Anaesthesia','Allergic to Penicillin','Latex Allergy','Bisphosphonate Therapy','Bleeding Disorder','Organ Transplant','Chemotherapy / Radiotherapy'] as $alert)
                        @php
                            // medical_alert stored as comma-separated string; split for pre-check
                            $currentAlerts = array_map('trim', explode(',', $patient->medical_alert ?? ''));
                        @endphp
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="medical_alert_flags[]" value="{{ $alert }}"
                                   {{ in_array($alert, $currentAlerts) ? 'checked' : '' }}
                                   class="accent-red-500">
                            <span class="text-xs text-gray-600">{{ $alert }}</span>
                        </label>
                        @endforeach
                    </div>
                    {{-- Custom / free text alert --}}
                    <input type="text" name="medical_alert_custom"
                           id="medicalAlertCustom"
                           placeholder="Other alert (optional)"
                           class="mt-2 w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-red-400">
                </div>

                {{-- Allergies --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Allergies <span class="text-gray-400">(comma separated)</span></label>
                    <input type="text" name="allergies_text"
                           value="{{ implode(', ', $patient->allergies ?? []) }}"
                           placeholder="e.g. Penicillin, Latex, Aspirin"
                           class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>

                {{-- Family notes --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Family Notes</label>
                    <input type="text" name="family_notes" value="{{ $patient->family_notes }}"
                           placeholder="e.g. Mother also a patient, ID#123"
                           class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
            </div>

            {{-- Source & Referral --}}
            <div class="px-6 py-5 space-y-4"
                 x-data="editReferralPanel({
                     initSource:             '{{ $patient->source }}',
                     initReferralType:       '{{ $patient->referral_type }}',
                     initReferredPatientId:   {{ $patient->referred_patient_id ?? 'null' }},
                     initReferredPatientName: '{{ addslashes($patient->referredPatient?->name ?? '') }}',
                     initReferredPatientPid:  '{{ addslashes($patient->referredPatient?->patient_id ?? '') }}',
                     initReferredPatientPhone:'{{ addslashes($patient->referredPatient?->phone ?? '') }}',
                     initReferrerName:   '{{ addslashes($patient->referrer_name ?? '') }}',
                     initReferrerMobile: '{{ $patient->referrer_mobile ?? '' }}',
                     initReferrerType:   '{{ $patient->referrer_type ?? '' }}',
                     initReferrerNotes:  '{{ addslashes($patient->referrer_notes ?? '') }}',
                 })">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Source</h3>

                {{-- Source selector --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Source</label>
                    <select name="source" x-model="source"
                            class="w-full text-sm border border-gray-200 px-3 py-2 bg-white focus:outline-none focus:border-[#6a0f70]">
                        <option value="">Select</option>
                        @foreach(['Walk-in','Google','Instagram','Facebook','Referral','JustDial','Practo','Other'] as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Referral structured panel — visible only when source = Referral --}}
                <div x-show="source === 'Referral'" class="space-y-3 border border-[#e8d5f0] p-3 bg-[#fdf9ff]">
                    <p class="text-xs font-semibold text-[#6a0f70] uppercase tracking-wide">Referral Details</p>

                    {{-- Hidden fields (submitted with form via PATCH) --}}
                    <input type="hidden" name="referral_type"       x-bind:value="referralType">
                    <input type="hidden" name="referred_patient_id" x-bind:value="referredPatientId ?? ''">
                    <input type="hidden" name="referrer_name"       x-bind:value="referrerName">
                    <input type="hidden" name="referrer_mobile"     x-bind:value="referrerMobile">
                    <input type="hidden" name="referrer_type"       x-bind:value="referrerType">
                    <input type="hidden" name="referrer_notes"      x-bind:value="referrerNotes">

                    {{-- Toggle: Existing Patient vs Others --}}
                    <div class="flex gap-2">
                        <button type="button"
                            x-on:click="referralType = 'existing_patient'"
                            :class="referralType === 'existing_patient'
                                ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70]'"
                            class="flex-1 py-1.5 text-xs font-semibold border transition-colors">
                            Existing Patient
                        </button>
                        <button type="button"
                            x-on:click="referralType = 'other'"
                            :class="referralType === 'other'
                                ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70]'"
                            class="flex-1 py-1.5 text-xs font-semibold border transition-colors">
                            Others
                        </button>
                    </div>

                    {{-- Existing Patient: autocomplete --}}
                    <div x-show="referralType === 'existing_patient'" class="space-y-2">
                        <label class="block text-xs text-gray-500 mb-1">Search Patient</label>
                        <div class="relative">
                            <input type="text"
                                x-model="refSearchQuery"
                                x-on:input.debounce.300="doRefSearch()"
                                x-on:focus="doRefSearch()"
                                placeholder="Name, mobile, or UHID…"
                                class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70] pr-8" />
                            <svg x-show="refSearching" class="animate-spin w-4 h-4 absolute right-2.5 top-2.5 text-[#6a0f70]"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                            </svg>
                        </div>

                        {{-- Results dropdown --}}
                        <div x-show="refResults.length > 0 && !selectedRefPatient"
                             class="border border-gray-200 bg-white shadow max-h-40 overflow-y-auto z-10 relative">
                            <template x-for="p in refResults" :key="p.id">
                                <button type="button"
                                    x-on:click="selectRefPatient(p)"
                                    class="w-full text-left px-3 py-2 hover:bg-[#f5eef9] transition border-b border-gray-50 last:border-0">
                                    <div class="text-sm font-medium text-gray-800" x-text="p.name"></div>
                                    <div class="text-xs text-gray-400">
                                        <span x-text="p.patient_id ?? ''"></span>
                                        <span x-show="p.phone" x-text="' · ' + p.phone"></span>
                                    </div>
                                </button>
                            </template>
                        </div>

                        {{-- Selected chip --}}
                        <div x-show="selectedRefPatient"
                             class="flex items-center gap-2 px-3 py-2 bg-[#f5eef9] border border-[#6a0f70]/20">
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-[#380740]" x-text="selectedRefPatient?.name"></div>
                                <div class="text-xs text-gray-500">
                                    <span x-text="selectedRefPatient?.patient_id ?? ''"></span>
                                    <span x-show="selectedRefPatient?.phone" x-text="' · ' + selectedRefPatient?.phone"></span>
                                </div>
                            </div>
                            <button type="button" x-on:click="clearRefPatient()"
                                class="text-[#6a0f70]/60 hover:text-[#6a0f70] text-lg leading-none">×</button>
                        </div>
                    </div>

                    {{-- Others panel --}}
                    <div x-show="referralType === 'other'" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Referrer Name</label>
                                <input type="text" x-model="referrerName"
                                    placeholder="e.g. Dr. Mehta"
                                    class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Mobile</label>
                                <input type="tel" x-model="referrerMobile"
                                    placeholder="9876543210"
                                    class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Referral Type</label>
                            <select x-model="referrerType"
                                    class="w-full text-sm border border-gray-200 px-3 py-2 bg-white focus:outline-none focus:border-[#6a0f70]">
                                <option value="">Select type</option>
                                <option value="Doctor">Doctor</option>
                                <option value="Friend">Friend</option>
                                <option value="Family">Family</option>
                                <option value="Staff">Staff</option>
                                <option value="Corporate">Corporate</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Notes <span class="text-gray-400">(optional)</span></label>
                            <input type="text" x-model="referrerNotes"
                                placeholder="e.g. colleague from ABC Corp"
                                class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>

    {{-- Footer --}}
    <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-white">
        <button
            type="button"
            x-on:click="submitEditPatient()"
            class="flex-1 py-2.5 text-sm bg-[#380740] text-white hover:bg-[#6a0f70] transition-colors font-medium">
            Save Changes
        </button>
        <button
            type="button"
            x-on:click="editDrawerOpen = false"
            class="px-5 py-2.5 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">
            Cancel
        </button>
    </div>
</div>

{{-- submitEditPatient() is defined in patientProfile() in show.blade.php @push('scripts') --}}

<script>
function editReferralPanel(cfg) {
    return {
        source:            cfg.initSource ?? '',
        referralType:      cfg.initReferralType ?? '',
        referredPatientId: cfg.initReferredPatientId ?? null,
        referrerName:      cfg.initReferrerName ?? '',
        referrerMobile:    cfg.initReferrerMobile ?? '',
        referrerType:      cfg.initReferrerType ?? '',
        referrerNotes:     cfg.initReferrerNotes ?? '',

        // Autocomplete state
        refSearchQuery:    cfg.initReferredPatientName ?? '',
        refResults:        [],
        refSearching:      false,
        selectedRefPatient: cfg.initReferredPatientId
            ? { id: cfg.initReferredPatientId, name: cfg.initReferredPatientName, patient_id: cfg.initReferredPatientPid, phone: cfg.initReferredPatientPhone }
            : null,

        async doRefSearch() {
            if (this.selectedRefPatient || this.refSearchQuery.length < 2) {
                this.refResults = [];
                return;
            }
            this.refSearching = true;
            try {
                const res = await fetch(
                    '{{ route('patients.search') }}?q=' + encodeURIComponent(this.refSearchQuery),
                    { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' } }
                );
                this.refResults = await res.json();
            } catch(e) {
                this.refResults = [];
            } finally {
                this.refSearching = false;
            }
        },

        selectRefPatient(p) {
            this.selectedRefPatient = p;
            this.referredPatientId  = p.id;
            this.refSearchQuery     = p.name;
            this.refResults         = [];
        },

        clearRefPatient() {
            this.selectedRefPatient = null;
            this.referredPatientId  = null;
            this.refSearchQuery     = '';
            this.refResults         = [];
        },
    };
}
</script>
