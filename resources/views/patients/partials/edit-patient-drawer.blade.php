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
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Date of Birth</label>
                        <input type="date" name="dob" value="{{ $patient->date_of_birth?->format('Y-m-d') }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
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

                {{-- Allergies --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Allergies <span class="text-gray-400">(comma separated)</span></label>
                    <input type="text" name="allergies_text"
                           value="{{ implode(', ', $patient->allergies ?? []) }}"
                           placeholder="e.g. Penicillin, Latex, Aspirin"
                           class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>

                {{-- Medical alert --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Medical Alert</label>
                    <textarea name="medical_alert" rows="2"
                              placeholder="Critical alerts shown prominently on patient profile"
                              class="w-full text-sm border border-gray-200 px-3 py-2 resize-none focus:outline-none focus:border-[#6a0f70] focus:ring-1 focus:ring-red-300">{{ $patient->medical_alert }}</textarea>
                </div>

                {{-- Family notes --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Family Notes</label>
                    <input type="text" name="family_notes" value="{{ $patient->family_notes }}"
                           placeholder="e.g. Mother also a patient, ID#123"
                           class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                </div>
            </div>

            {{-- Source --}}
            <div class="px-6 py-5 space-y-4">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Source</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Source</label>
                        <select name="source" class="w-full text-sm border border-gray-200 px-3 py-2 bg-white focus:outline-none focus:border-[#6a0f70]">
                            <option value="">Select</option>
                            @foreach(['Walk-in','Google','Instagram','Facebook','Referral','JustDial','Practo','Other'] as $s)
                                <option value="{{ $s }}" {{ $patient->source === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Referred By</label>
                        <input type="text" name="referred_by" value="{{ $patient->referred_by }}"
                               class="w-full text-sm border border-gray-200 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
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

@push('scripts')
<script>
// Extend the patientProfile() Alpine component with the edit drawer submit
document.addEventListener('alpine:init', () => {
    // Patch the global component so submitEditPatient is available
    window.patientProfileEditPatch = {
        async submitEditPatient() {
            const form = document.getElementById('editPatientForm');
            const formData = new FormData(form);

            // Convert allergies_text → array
            const allergiesText = formData.get('allergies_text') || '';
            formData.delete('allergies_text');
            const allergies = allergiesText.split(',').map(s => s.trim()).filter(Boolean);
            allergies.forEach(a => formData.append('allergies[]', a));

            try {
                const res = await fetch(`/patients/{{ $patient->id }}`, {
                    method: 'POST', // PATCH via _method
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const data = await res.json();
                if (data.success) {
                    this.editDrawerOpen = false;
                    window.location.reload();
                }
            } catch(e) {
                console.error('Update failed', e);
            }
        }
    };
});
</script>
@endpush
