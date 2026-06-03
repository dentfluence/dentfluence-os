{{--
    partials/add-patient-modal.blade.php
    Triggered by: $dispatch('open-add-patient') from anywhere on the page.
    5-tab registration: Basic Info → Contact → Medical & Dental → Habits → Source & Notes
    POST to: /patients  |  expects JSON { success, patient, patient_url }
--}}

{{-- ── Preset data ──────────────────────────────────────────────────────── --}}
@php
$medicalPresets = ['Diabetes','Hypertension','Thyroid','Asthma','Heart Disease','Kidney Disease','Epilepsy','Blood Disorder','Bleeding Disorder','HIV/AIDS','Hepatitis'];
$dentalPresets  = ['Missing Teeth','Crowding','Spacing','Caries','Bruxism','Gum Disease','Sensitive Teeth','Root Canal Treated','Implants Present','Dentures'];
$habitPresets   = ['Tobacco (Chewing)','Gutkha','Smoking','Alcohol','Pan','Supari','Betel Nut'];
$tagPresets     = ['Implant Prospect','AOCP Prospect','Family Patient','Pediatric','VIP','Senior Citizen','Referred Patient','Corporate Patient'];
@endphp

<div
    x-data="addPatientModal()"
    x-on:open-add-patient.window="openModal($event.detail ?? {})"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[60] flex items-center justify-center"
    style="font-family: 'DM Sans', sans-serif;"
>
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" x-on:click="closeModal()"></div>

    {{-- Modal panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full max-w-2xl bg-white shadow-2xl mx-4 flex flex-col"
        style="max-height: 92vh; border-radius: 2px;"
        x-on:click.stop
    >

        {{-- ── Header ── --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0] shrink-0 bg-white">
            <div>
                <h2 class="text-xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">
                    New Patient
                </h2>
                <p class="text-xs text-gray-400 mt-0.5 uppercase tracking-widest">Register a patient in under 90 seconds</p>
            </div>
            <button x-on:click="closeModal()" class="text-gray-400 hover:text-[#6a0f70] transition p-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- ── Tab nav ── --}}
        <div class="flex border-b border-gray-200 shrink-0 bg-white px-6 overflow-x-auto" style="scrollbar-width:none;">
            <template x-for="(tab, i) in tabs" :key="i">
                <button
                    type="button"
                    x-on:click="currentTab = i"
                    :class="currentTab === i
                        ? 'border-b-2 border-[#6a0f70] text-[#6a0f70] font-semibold'
                        : 'text-gray-500 hover:text-gray-700 border-b-2 border-transparent'"
                    class="flex items-center gap-1.5 px-3 py-3 text-xs whitespace-nowrap transition-colors"
                >
                    <span
                        :class="tabCompleted(i) ? 'bg-green-500' : (currentTab === i ? 'bg-[#6a0f70]' : 'bg-gray-300')"
                        class="w-4 h-4 rounded-full text-white text-[9px] font-bold flex items-center justify-center flex-shrink-0"
                    >
                        <template x-if="tabCompleted(i)">
                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="!tabCompleted(i)">
                            <span x-text="i + 1"></span>
                        </template>
                    </span>
                    <span x-text="tab"></span>
                </button>
            </template>
        </div>

        {{-- ── Success state ── --}}
        <div x-show="success" class="flex-1 flex flex-col items-center justify-center py-14 px-6">
            <div class="w-16 h-16 rounded-full bg-green-50 flex items-center justify-center mb-4 border-2 border-green-200">
                <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p class="text-xl font-semibold text-[#380740] mb-1" style="font-family:'Cormorant Garamond',serif;">Patient Registered!</p>
            <p class="text-sm text-gray-500 mb-6" x-text="'ID: ' + (createdPatientId ?? '')"></p>
            <div class="flex gap-3">
                <button x-on:click="goToProfile()" class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-semibold hover:bg-[#380740] transition">
                    Open Profile →
                </button>
                <button x-on:click="addAnother()" class="px-5 py-2 border border-gray-300 text-sm text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70] transition">
                    Add Another
                </button>
            </div>
        </div>

        {{-- ── Form body ── --}}
        <div x-show="!success" class="overflow-y-auto flex-1 px-6 py-5" style="min-height:300px;">

            {{-- General error --}}
            <template x-if="errors._general">
                <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm">
                    <span x-text="errors._general[0]"></span>
                </div>
            </template>

            {{-- ════════════════════════════════════════════════
                 TAB 1 — BASIC INFORMATION
            ════════════════════════════════════════════════ --}}
            <div x-show="currentTab === 0" class="space-y-4">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold mb-3">Personal Details</p>

                {{-- Title + First Name + Middle Name --}}
                <div class="flex gap-3">
                    <div style="width:90px;flex-shrink:0;">
                        <label class="df-label">Title</label>
                        <select x-model="form.title" class="df-input">
                            <option value="">—</option>
                            <option>Mr.</option>
                            <option>Mrs.</option>
                            <option>Miss</option>
                            <option>Mast.</option>
                            <option>Dr.</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="df-label">First Name <span class="text-red-400">*</span></label>
                        <input type="text" x-model="form.first_name" placeholder="Priya"
                            :class="errors.first_name ? 'border-red-400' : 'border-gray-200'"
                            class="df-input" />
                        <template x-if="errors.first_name">
                            <p class="df-err" x-text="errors.first_name[0]"></p>
                        </template>
                    </div>
                    <div style="width:130px;flex-shrink:0;">
                        <label class="df-label">Middle Name</label>
                        <input type="text" x-model="form.middle_name" placeholder="Optional" class="df-input border-gray-200" />
                    </div>
                </div>

                {{-- Last Name + Gender --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="df-label">Last Name <span class="text-red-400">*</span></label>
                        <input type="text" x-model="form.last_name" placeholder="Sharma"
                            :class="errors.last_name ? 'border-red-400' : 'border-gray-200'"
                            class="df-input" />
                        <template x-if="errors.last_name">
                            <p class="df-err" x-text="errors.last_name[0]"></p>
                        </template>
                    </div>
                    <div>
                        <label class="df-label">Gender <span class="text-red-400">*</span></label>
                        <select x-model="form.gender" class="df-input border-gray-200">
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                            <option value="prefer_not_to_say">Prefer not to say</option>
                        </select>
                    </div>
                </div>

                {{-- DOB / Age --}}
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <label class="df-label" style="margin-bottom:0;">Date of Birth</label>
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" x-model="form.dob_unknown" class="rounded border-gray-300" style="accent-color:#6a0f70;" />
                            <span class="text-xs text-gray-500">DOB Unknown</span>
                        </label>
                    </div>
                    <div x-show="!form.dob_unknown" class="grid grid-cols-2 gap-3">
                        <input type="date" x-model="form.dob" class="df-input border-gray-200" />
                        <div class="flex items-center">
                            <template x-if="form.dob">
                                <div class="px-3 py-2 bg-[#f5eef9] text-[#6a0f70] text-sm font-semibold">
                                    <span x-text="calcAge(form.dob) + ' years old'"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div x-show="form.dob_unknown" class="flex items-center gap-2">
                        <input type="number" x-model="form.age_years" placeholder="Age" min="0" max="150"
                            class="df-input border-gray-200 w-28" />
                        <span class="text-sm text-gray-400">years</span>
                    </div>
                </div>

                {{-- Tags --}}
                <div>
                    <label class="df-label">Tags <span class="text-gray-400 font-normal normal-case text-xs">(multi-select)</span></label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tagPresets as $tag)
                        <button type="button"
                            x-on:click="toggleTag('{{ $tag }}')"
                            :class="form.tags.includes('{{ $tag }}')
                                ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                            class="px-3 py-1.5 text-xs font-medium border transition-colors">
                            {{ $tag }}
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════════════
                 TAB 2 — CONTACT
            ════════════════════════════════════════════════ --}}
            <div x-show="currentTab === 1" class="space-y-4">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold mb-3">Contact Details</p>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="df-label">Mobile <span class="text-red-400">*</span></label>
                        <input type="tel" x-model="form.mobile" placeholder="9876543210"
                            :class="errors.mobile ? 'border-red-400' : 'border-gray-200'"
                            class="df-input" />
                        <template x-if="errors.mobile">
                            <p class="df-err" x-text="errors.mobile[0]"></p>
                        </template>
                    </div>
                    <div>
                        <label class="df-label">Alternate Number</label>
                        <input type="tel" x-model="form.alternate_phone" placeholder="Optional" class="df-input border-gray-200" />
                    </div>
                </div>

                <div>
                    <label class="df-label">Email</label>
                    <input type="email" x-model="form.email" placeholder="priya@example.com" class="df-input border-gray-200" />
                </div>

                {{-- Emergency Contact --}}
                <div class="border border-gray-100 p-3 space-y-3 bg-gray-50">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Emergency Contact</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="df-label">Name</label>
                            <input type="text" x-model="form.emergency_contact_name" placeholder="Rahul Sharma" class="df-input border-gray-200" />
                        </div>
                        <div>
                            <label class="df-label">Relationship</label>
                            <input type="text" x-model="form.emergency_contact_relationship" placeholder="Husband" class="df-input border-gray-200" />
                        </div>
                    </div>
                    <div>
                        <label class="df-label">Number</label>
                        <input type="tel" x-model="form.emergency_contact_number" placeholder="9876543210" class="df-input border-gray-200" />
                    </div>
                </div>

                {{-- Address --}}
                <div>
                    <label class="df-label">Address</label>
                    <input type="text" x-model="form.address" placeholder="123, MG Road" class="df-input border-gray-200" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="df-label">Area / Locality <span class="text-red-400">*</span></label>
                        <input type="text" x-model="form.area" placeholder="Bandra West"
                            :class="errors.area ? 'border-red-400' : 'border-gray-200'"
                            class="df-input" />
                    </div>
                    <div>
                        <label class="df-label">City</label>
                        <input type="text" x-model="form.city" placeholder="Mumbai" class="df-input border-gray-200" />
                    </div>
                    <div>
                        <label class="df-label">Pincode</label>
                        <input type="text" x-model="form.pincode" placeholder="400001" class="df-input border-gray-200" />
                    </div>
                </div>
            </div>

            {{-- ════════════════════════════════════════════════
                 TAB 3 — MEDICAL & DENTAL
            ════════════════════════════════════════════════ --}}
            <div x-show="currentTab === 2" class="space-y-5">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold mb-3">Medical & Dental History</p>

                {{-- Medical Conditions --}}
                <div>
                    <label class="df-label">Medical Conditions</label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach($medicalPresets as $cond)
                        <button type="button"
                            x-on:click="toggleArr('medical_conditions', '{{ $cond }}')"
                            :class="form.medical_conditions.includes('{{ $cond }}')
                                ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70]'"
                            class="px-3 py-1.5 text-xs font-medium border transition-colors">
                            {{ $cond }}
                        </button>
                        @endforeach
                    </div>
                    <div class="flex gap-2">
                        <input type="text" x-model="customMedical" placeholder="Add custom condition…"
                            class="df-input border-gray-200 text-sm flex-1"
                            x-on:keydown.enter.prevent="addCustom('medical_conditions', customMedical); customMedical=''" />
                        <button type="button"
                            x-on:click="addCustom('medical_conditions', customMedical); customMedical=''"
                            class="px-3 py-1 text-xs bg-gray-100 text-gray-600 border border-gray-200 hover:bg-[#f5eef9] hover:text-[#6a0f70] hover:border-[#6a0f70] transition">
                            + Add
                        </button>
                    </div>
                    <template x-if="form.medical_conditions.length > 0">
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <template x-for="(item, idx) in form.medical_conditions" :key="idx">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#f5eef9] text-[#6a0f70] text-xs border border-[#6a0f70]/20">
                                    <span x-text="item"></span>
                                    <button type="button" x-on:click="removeArr('medical_conditions', idx)"
                                        class="ml-0.5 text-[#6a0f70]/60 hover:text-[#6a0f70] leading-none">×</button>
                                </span>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Current Medications --}}
                <div>
                    <label class="df-label">Current Medications</label>
                    <textarea x-model="form.current_medications" rows="2"
                        placeholder="e.g. Metformin 500mg, Amlodipine 5mg…"
                        class="df-input border-gray-200 resize-none"></textarea>
                </div>

                {{-- Dental Conditions --}}
                <div>
                    <label class="df-label">Dental Conditions</label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        @foreach($dentalPresets as $cond)
                        <button type="button"
                            x-on:click="toggleArr('dental_conditions', '{{ $cond }}')"
                            :class="form.dental_conditions.includes('{{ $cond }}')
                                ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70]'"
                            class="px-3 py-1.5 text-xs font-medium border transition-colors">
                            {{ $cond }}
                        </button>
                        @endforeach
                    </div>
                    <div class="flex gap-2">
                        <input type="text" x-model="customDental" placeholder="Add custom dental condition…"
                            class="df-input border-gray-200 text-sm flex-1"
                            x-on:keydown.enter.prevent="addCustom('dental_conditions', customDental); customDental=''" />
                        <button type="button"
                            x-on:click="addCustom('dental_conditions', customDental); customDental=''"
                            class="px-3 py-1 text-xs bg-gray-100 text-gray-600 border border-gray-200 hover:bg-[#f5eef9] hover:text-[#6a0f70] hover:border-[#6a0f70] transition">
                            + Add
                        </button>
                    </div>
                    <template x-if="form.dental_conditions.length > 0">
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            <template x-for="(item, idx) in form.dental_conditions" :key="idx">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#f5eef9] text-[#6a0f70] text-xs border border-[#6a0f70]/20">
                                    <span x-text="item"></span>
                                    <button type="button" x-on:click="removeArr('dental_conditions', idx)"
                                        class="ml-0.5 text-[#6a0f70]/60 hover:text-[#6a0f70] leading-none">×</button>
                                </span>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ════════════════════════════════════════════════
                 TAB 4 — HABITS
            ════════════════════════════════════════════════ --}}
            <div x-show="currentTab === 3" class="space-y-4">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold mb-3">Habits & Frequency</p>

                <div class="flex flex-wrap gap-2 mb-1">
                    @foreach($habitPresets as $habit)
                    <button type="button"
                        x-on:click="toggleArr('habits', '{{ $habit }}')"
                        :class="form.habits.includes('{{ $habit }}')
                            ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                            : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70]'"
                        class="px-3 py-1.5 text-xs font-medium border transition-colors">
                        {{ $habit }}
                    </button>
                    @endforeach
                </div>

                {{-- Frequency inputs for selected habits --}}
                <template x-if="form.habits.length > 0">
                    <div class="space-y-2 border border-gray-100 p-3 bg-gray-50">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-semibold mb-2">Frequency (optional)</p>
                        <template x-for="habit in form.habits" :key="habit">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-medium text-gray-700 w-36 truncate flex-shrink-0" x-text="habit"></span>
                                <input type="text"
                                    :value="form.habit_frequency[habit] ?? ''"
                                    x-on:input="form.habit_frequency[habit] = $event.target.value"
                                    placeholder="e.g. Daily / 5 per day"
                                    class="df-input border-gray-200 text-xs flex-1" />
                            </div>
                        </template>
                    </div>
                </template>

                <div class="flex gap-2">
                    <input type="text" x-model="customHabit" placeholder="Add custom habit…"
                        class="df-input border-gray-200 text-sm flex-1"
                        x-on:keydown.enter.prevent="addCustom('habits', customHabit); customHabit=''" />
                    <button type="button"
                        x-on:click="addCustom('habits', customHabit); customHabit=''"
                        class="px-3 py-1 text-xs bg-gray-100 text-gray-600 border border-gray-200 hover:bg-[#f5eef9] hover:text-[#6a0f70] hover:border-[#6a0f70] transition">
                        + Add
                    </button>
                </div>

                <template x-if="form.habits.length === 0">
                    <p class="text-xs text-gray-400 italic">No habits selected — tap any above or add a custom one.</p>
                </template>
            </div>

            {{-- ════════════════════════════════════════════════
                 TAB 5 — SOURCE & NOTES
            ════════════════════════════════════════════════ --}}
            <div x-show="currentTab === 4" class="space-y-4">
                <p class="text-xs text-gray-400 uppercase tracking-widest font-semibold mb-3">Source & Notes</p>

                {{-- Source --}}
                <div>
                    <label class="df-label">How did they find us? <span class="text-red-400">*</span></label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="src in sources" :key="src">
                            <button type="button"
                                x-on:click="form.source = src"
                                :class="form.source === src
                                    ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                    : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70]'"
                                class="px-4 py-2 text-xs font-medium border transition-colors"
                                x-text="src">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Dynamic source fields --}}
                <div x-show="form.source === 'Referral'" class="space-y-2">
                    <label class="df-label">Referred by (Patient / Person Name)</label>
                    <input type="text" x-model="form.source_referral_name" placeholder="e.g. Rahul Sharma"
                        class="df-input border-gray-200" />
                </div>
                <div x-show="form.source === 'Camp'" class="space-y-2">
                    <label class="df-label">Camp Name</label>
                    <input type="text" x-model="form.source_camp_name" placeholder="e.g. Dental Camp – Borivali 2026"
                        class="df-input border-gray-200" />
                </div>
                <div x-show="form.source === 'Instagram' || form.source === 'Google' || form.source === 'Facebook'" class="space-y-2">
                    <label class="df-label">Campaign Name</label>
                    <input type="text" x-model="form.source_campaign" placeholder="e.g. Implant Offer April 2026"
                        class="df-input border-gray-200" />
                </div>

                {{-- Notes --}}
                <div>
                    <label class="df-label">General Notes</label>
                    <textarea x-model="form.notes" rows="3" placeholder="Any additional notes, preferences, or observations…"
                        class="df-input border-gray-200 resize-none"></textarea>
                </div>
            </div>

        </div>{{-- /form body --}}

        {{-- ── Footer ── --}}
        <div x-show="!success" class="px-6 py-4 border-t border-[#e8d5f0] flex items-center justify-between shrink-0 bg-white">
            <div class="flex items-center gap-3">
                <button type="button" x-show="currentTab > 0"
                    x-on:click="currentTab--"
                    class="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/>
                    </svg>
                    Back
                </button>
                <button type="button" x-show="currentTab === 0"
                    x-on:click="closeModal()"
                    class="text-xs text-gray-400 hover:text-gray-600 transition uppercase tracking-wider">
                    Cancel
                </button>
            </div>

            {{-- Progress dots --}}
            <div class="flex gap-1.5 items-center">
                <template x-for="(tab, i) in tabs" :key="i">
                    <div :class="i === currentTab ? 'w-5 bg-[#6a0f70]' : (tabCompleted(i) ? 'w-2 bg-green-400' : 'w-2 bg-gray-200')"
                        class="h-1.5 rounded-full transition-all duration-200"></div>
                </template>
            </div>

            <div class="flex gap-2 items-center">
                {{-- Skip & Save — available from tab 1 onward --}}
                <template x-if="currentTab > 0 && currentTab < 4">
                    <button type="button"
                        x-on:click="submit()"
                        :disabled="loading"
                        class="text-xs text-gray-400 hover:text-[#6a0f70] transition px-2 py-1 disabled:opacity-40">
                        Save &amp; skip remaining →
                    </button>
                </template>

                {{-- Next --}}
                <template x-if="currentTab < 4">
                    <button type="button"
                        x-on:click="nextTab()"
                        class="flex items-center gap-1.5 px-5 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#380740] transition">
                        Next
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/>
                        </svg>
                    </button>
                </template>

                {{-- Save on last tab --}}
                <template x-if="currentTab === 4">
                    <button type="button"
                        x-on:click="submit()"
                        :disabled="loading"
                        class="flex items-center gap-2 px-5 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-wider hover:bg-[#380740] transition disabled:opacity-60">
                        <svg x-show="loading" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <span x-text="loading ? 'Saving…' : 'Register Patient'"></span>
                    </button>
                </template>
            </div>
        </div>

    </div>{{-- /modal panel --}}
</div>

{{-- ── Scoped styles ── --}}
<style>
[x-cloak] { display: none !important; }
.df-label { display:block; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:#6b7280; margin-bottom:4px; }
.df-input  { width:100%; border:1px solid; border-radius:2px; padding:8px 10px; font-size:13px; color:#111827; background:white; outline:none; transition:border-color .15s, box-shadow .15s; font-family:'DM Sans',sans-serif; }
.df-input:focus { border-color:#6a0f70 !important; box-shadow:0 0 0 3px rgba(106,15,112,.08); }
.df-input::placeholder { color:#9ca3af; }
.df-err   { font-size:11px; color:#ef4444; margin-top:3px; }
textarea.df-input { resize:vertical; }
</style>

{{-- ── Alpine component ── --}}
<script>
function addPatientModal() {
    return {
        open: false,
        loading: false,
        success: false,
        errors: {},
        currentTab: 0,
        createdPatientId: null,
        createdPatientUrl: null,
        customMedical: '',
        customDental: '',
        customHabit: '',

        tabs: ['Basic Info', 'Contact', 'Medical & Dental', 'Habits', 'Source & Notes'],
        sources: ['Google','Instagram','Facebook','Referral','Walk-In','Camp','Website','Other'],

        form: {
            title: '', first_name: '', middle_name: '', last_name: '', gender: '',
            dob: '', dob_unknown: false, age_years: '', tags: [],
            mobile: '', alternate_phone: '', email: '',
            emergency_contact_name: '', emergency_contact_relationship: '', emergency_contact_number: '',
            address: '', area: '', city: '', pincode: '',
            medical_conditions: [], current_medications: '', dental_conditions: [],
            habits: [], habit_frequency: {},
            source: '', source_referral_name: '', source_camp_name: '', source_campaign: '', notes: '',
        },

        openModal(prefill) {
            this.reset();
            if (prefill && typeof prefill === 'object') {
                Object.assign(this.form, prefill);
            }
            this.open = true;
        },

        closeModal() {
            this.open = false;
            setTimeout(() => this.reset(), 300);
        },

        reset() {
            this.currentTab = 0;
            this.loading = false;
            this.success = false;
            this.errors = {};
            this.createdPatientId = null;
            this.createdPatientUrl = null;
            this.customMedical = '';
            this.customDental = '';
            this.customHabit = '';
            this.form = {
                title: '', first_name: '', middle_name: '', last_name: '', gender: '',
                dob: '', dob_unknown: false, age_years: '', tags: [],
                mobile: '', alternate_phone: '', email: '',
                emergency_contact_name: '', emergency_contact_relationship: '', emergency_contact_number: '',
                address: '', area: '', city: '', pincode: '',
                medical_conditions: [], current_medications: '', dental_conditions: [],
                habits: [], habit_frequency: {},
                source: '', source_referral_name: '', source_camp_name: '', source_campaign: '', notes: '',
            };
        },

        calcAge(dob) {
            if (!dob) return '';
            const today = new Date();
            const birth = new Date(dob);
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            return age;
        },

        toggleTag(tag) {
            const i = this.form.tags.indexOf(tag);
            if (i === -1) this.form.tags.push(tag);
            else this.form.tags.splice(i, 1);
        },

        toggleArr(field, value) {
            const i = this.form[field].indexOf(value);
            if (i === -1) this.form[field].push(value);
            else this.form[field].splice(i, 1);
        },

        addCustom(field, value) {
            const v = (value ?? '').trim();
            if (v && !this.form[field].includes(v)) this.form[field].push(v);
        },

        removeArr(field, idx) {
            this.form[field].splice(idx, 1);
        },

        tabCompleted(i) {
            if (i === 0) return !!(this.form.first_name && this.form.last_name);
            if (i === 1) return !!(this.form.mobile);
            return false;
        },

        nextTab() {
            this.errors = {};
            if (this.currentTab === 0) {
                if (!this.form.first_name) { this.errors.first_name = ['Required']; return; }
                if (!this.form.last_name)  { this.errors.last_name  = ['Required']; return; }
            }
            if (this.currentTab === 1) {
                if (!this.form.mobile) { this.errors.mobile = ['Required']; return; }
            }
            if (this.currentTab < 4) this.currentTab++;
        },

        async submit() {
            this.errors = {};
            if (!this.form.first_name) { this.errors.first_name = ['Required']; this.currentTab = 0; return; }
            if (!this.form.last_name)  { this.errors.last_name  = ['Required']; this.currentTab = 0; return; }
            if (!this.form.mobile)     { this.errors.mobile     = ['Required']; this.currentTab = 1; return; }

            this.loading = true;
            try {
                const resp = await fetch('{{ route('patients.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                const data = await resp.json();
                if (resp.ok && data.success) {
                    this.createdPatientId = data.patient?.patient_id ?? ('#' + data.patient?.id);
                    this.createdPatientUrl = data.patient_url;
                    this.success = true;
                    window.dispatchEvent(new CustomEvent('patient-added', { detail: data.patient }));
                } else {
                    this.errors = data.errors ?? { _general: ['Something went wrong. Please try again.'] };
                    if (this.errors.first_name || this.errors.last_name) this.currentTab = 0;
                    else if (this.errors.mobile || this.errors.area)     this.currentTab = 1;
                }
            } catch (e) {
                this.errors = { _general: ['Network error. Please check your connection.'] };
            } finally {
                this.loading = false;
            }
        },

        goToProfile() {
            if (this.createdPatientUrl) window.location.href = this.createdPatientUrl;
        },

        addAnother() {
            this.reset();
        },
    };
}
</script>
