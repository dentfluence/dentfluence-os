{{--
    partials/add-appointment-modal.blade.php
    Triggered by: $dispatch('open-add-appointment') from anywhere.
    All URLs are hardcoded paths — no route() calls that could fail at render time.
    POST /appointments  |  GET /treatment-categories  |  GET /treatment-categories/{id}/treatments
--}}
<div
    x-data="{
        open: false,
        loading: false,
        errors: {},
        success: false,
        categories: [],
        treatments: [],
        form: {
            first_name: '',
            last_name: '',
            mobile: '',
            appointment_date: '',
            appointment_time: '',
            treatment_category_id: '',
            treatment_id: '',
            notes: ''
        },
        async loadCategories() {
            try {
                const resp = await fetch('/treatment-categories', {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.categories = Array.isArray(data) ? data : (data.data ?? []);
                }
            } catch (e) {
                this.categories = [];
            }
        },
        async loadTreatments(categoryId) {
            this.form.treatment_id = '';
            this.treatments = [];
            if (!categoryId) return;
            try {
                const resp = await fetch(`/treatment-categories/${categoryId}/treatments`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                if (resp.ok) {
                    const data = await resp.json();
                    this.treatments = Array.isArray(data) ? data : (data.data ?? []);
                }
            } catch (e) {
                this.treatments = [];
            }
        },
        reset() {
            this.form = {
                first_name: '',
                last_name: '',
                mobile: '',
                appointment_date: new Date().toISOString().split('T')[0],
                appointment_time: '',
                treatment_category_id: '',
                treatment_id: '',
                notes: ''
            };
            this.errors = {};
            this.success = false;
            this.loading = false;
            this.treatments = [];
        },
        async submit() {
            this.loading = true;
            this.errors = {};
            try {
                const resp = await fetch('/appointments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await resp.json();
                if (resp.ok && data.success) {
                    this.success = true;
                    setTimeout(() => {
                        this.open = false;
                        this.reset();
                        window.location.reload();
                    }, 1400);
                } else {
                    this.errors = data.errors ?? {};
                }
            } catch (e) {
                this.errors = { general: ['Something went wrong. Please try again.'] };
            } finally {
                this.loading = false;
            }
        }
    }"
    x-on:open-add-appointment.window="open = true; reset(); loadCategories()"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center"
>
    {{-- Backdrop --}}
    <div
        class="absolute inset-0 bg-black/40 backdrop-blur-sm"
        x-on:click="open = false; reset()"
    ></div>

    {{-- Modal Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="relative z-10 w-full max-w-lg bg-white shadow-xl mx-4 max-h-[90vh] flex flex-col"
        x-on:click.stop
    >
        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0] shrink-0">
            <div>
                <h2 class="text-xl font-semibold text-[#380740] font-[Cormorant_Garamond]">New Appointment</h2>
                <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans] mt-0.5">Book a slot</p>
            </div>
            <button type="button" x-on:click="open = false; reset()"
                class="text-gray-400 hover:text-[#6a0f70] transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Success --}}
        <div x-show="success" class="flex-1 flex flex-col items-center justify-center py-12 px-6">
            <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-green-500" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <p class="text-lg font-semibold text-[#380740] font-[Cormorant_Garamond]">Appointment Booked!</p>
            <p class="text-xs text-gray-400 font-[DM_Sans] mt-1">Refreshing your schedule…</p>
        </div>

        {{-- Form body --}}
        <div x-show="!success" class="overflow-y-auto flex-1 px-6 py-5 space-y-4">

            <template x-if="errors.general">
                <p class="text-xs text-red-500 font-[DM_Sans]" x-text="errors.general[0]"></p>
            </template>

            {{-- Name --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">First Name <span class="text-red-400">*</span></label>
                    <input type="text" x-model="form.first_name" placeholder="Priya"
                        class="w-full border px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                        :class="errors.first_name ? 'border-red-400' : 'border-gray-200'" />
                    <template x-if="errors.first_name">
                        <p class="text-xs text-red-500 mt-1 font-[DM_Sans]" x-text="errors.first_name[0]"></p>
                    </template>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Last Name <span class="text-red-400">*</span></label>
                    <input type="text" x-model="form.last_name" placeholder="Sharma"
                        class="w-full border px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                        :class="errors.last_name ? 'border-red-400' : 'border-gray-200'" />
                    <template x-if="errors.last_name">
                        <p class="text-xs text-red-500 mt-1 font-[DM_Sans]" x-text="errors.last_name[0]"></p>
                    </template>
                </div>
            </div>

            {{-- Mobile --}}
            <div>
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Mobile <span class="text-red-400">*</span></label>
                <input type="tel" x-model="form.mobile" placeholder="9876543210"
                    class="w-full border px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                    :class="errors.mobile ? 'border-red-400' : 'border-gray-200'" />
                <template x-if="errors.mobile">
                    <p class="text-xs text-red-500 mt-1 font-[DM_Sans]" x-text="errors.mobile[0]"></p>
                </template>
            </div>

            {{-- Date + Time --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Date <span class="text-red-400">*</span></label>
                    <input type="date" x-model="form.appointment_date"
                        class="w-full border px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                        :class="errors.appointment_date ? 'border-red-400' : 'border-gray-200'" />
                    <template x-if="errors.appointment_date">
                        <p class="text-xs text-red-500 mt-1 font-[DM_Sans]" x-text="errors.appointment_date[0]"></p>
                    </template>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Time <span class="text-red-400">*</span></label>
                    <input type="time" x-model="form.appointment_time"
                        class="w-full border px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition"
                        :class="errors.appointment_time ? 'border-red-400' : 'border-gray-200'" />
                    <template x-if="errors.appointment_time">
                        <p class="text-xs text-red-500 mt-1 font-[DM_Sans]" x-text="errors.appointment_time[0]"></p>
                    </template>
                </div>
            </div>

            {{-- Treatment Category --}}
            <div>
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Treatment Category</label>
                <select x-model="form.treatment_category_id"
                    x-on:change="loadTreatments($event.target.value)"
                    class="w-full border border-gray-200 px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white">
                    <option value="">Select category</option>
                    <template x-for="cat in categories" :key="cat.id">
                        <option :value="cat.id" x-text="cat.name"></option>
                    </template>
                </select>
                <p x-show="categories.length === 0"
                   class="text-xs text-gray-400 mt-1 font-[DM_Sans]">
                    No categories available — you can still book without one.
                </p>
            </div>

            {{-- Treatment --}}
            <div x-show="treatments.length > 0">
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Treatment</label>
                <select x-model="form.treatment_id"
                    class="w-full border border-gray-200 px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition bg-white">
                    <option value="">Select treatment</option>
                    <template x-for="t in treatments" :key="t.id">
                        <option :value="t.id" x-text="t.name"></option>
                    </template>
                </select>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-xs uppercase tracking-widest text-gray-500 font-[DM_Sans] mb-1">Notes <span class="text-red-400">*</span></label>
                <textarea x-model="form.notes" rows="3"
                    placeholder="Reason for visit, patient concerns…"
                    class="w-full border px-3 py-2 text-sm font-[DM_Sans] text-gray-800 focus:outline-none focus:border-[#6a0f70] transition resize-none"
                    :class="errors.notes ? 'border-red-400' : 'border-gray-200'"></textarea>
                <template x-if="errors.notes">
                    <p class="text-xs text-red-500 mt-1 font-[DM_Sans]" x-text="errors.notes[0]"></p>
                </template>
            </div>

        </div>

        {{-- Footer --}}
        <div x-show="!success" class="px-6 py-4 border-t border-[#e8d5f0] flex items-center justify-between shrink-0 bg-white">
            <button type="button" x-on:click="open = false; reset()"
                class="text-xs uppercase tracking-widest font-[DM_Sans] text-gray-400 hover:text-gray-600 transition">
                Cancel
            </button>
            <button type="button" x-on:click="submit()" :disabled="loading"
                class="flex items-center gap-2 px-5 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#380740] transition disabled:opacity-60">
                <svg x-show="loading" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span x-text="loading ? 'Booking…' : 'Book Appointment'"></span>
            </button>
        </div>

    </div>
</div>
