@extends('layouts.app')

@section('content')
<div class="p-6 max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('appointments.index') }}"
               class="text-[#6a0f70] hover:opacity-70 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/>
                </svg>
            </a>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Appointment</p>
                <h1 class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                    {{ $appointment->patient->name }}
                </h1>
            </div>
        </div>

        {{-- Status Badge --}}
        <span class="px-3 py-1 text-xs font-semibold uppercase tracking-widest font-[DM_Sans]
            @switch($appointment->status)
                @case('scheduled') class="bg-blue-100 text-blue-700" @break
                @case('checkin') @break
                @case('in_chair') @break
                @case('checkout') @break
                @case('done') @break
                @case('cancelled') @break
                @case('no_show') @break
            @endswitch
            {{ match($appointment->status) {
                'scheduled'  => 'bg-blue-100 text-blue-700',
                'checkin'    => 'bg-yellow-100 text-yellow-700',
                'in_chair'   => 'bg-orange-100 text-orange-700',
                'checkout'   => 'bg-teal-100 text-teal-700',
                'done'       => 'bg-green-100 text-green-700',
                'cancelled'  => 'bg-red-100 text-red-600',
                'no_show'    => 'bg-gray-100 text-gray-500',
                default      => 'bg-gray-100 text-gray-500',
            } }}">
            {{ str_replace('_', ' ', $appointment->status) }}
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- LEFT: Appointment Info --}}
        <div class="md:col-span-2 space-y-4">

            {{-- Core Details --}}
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans] border-b border-[#e8d5f0] pb-2">
                    Appointment Details
                </h2>

                <div class="grid grid-cols-2 gap-4 text-sm font-[DM_Sans]">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Date</p>
                        <p class="text-gray-800 font-medium">
                            {{ $appointment->appointment_date->format('D, d M Y') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Time</p>
                        <p class="text-gray-800 font-medium">
                            {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Duration</p>
                        <p class="text-gray-800 font-medium">{{ $appointment->duration_minutes }} min</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Type</p>
                        <p class="text-gray-800 font-medium capitalize">{{ $appointment->type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Doctor</p>
                        <p class="text-gray-800 font-medium">{{ $appointment->doctor->name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Booked By</p>
                        <p class="text-gray-800 font-medium">{{ $appointment->createdBy->name ?? '—' }}</p>
                    </div>
                </div>

                @if($appointment->chief_complaint)
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Chief Complaint</p>
                    <p class="text-gray-800 text-sm">{{ $appointment->chief_complaint }}</p>
                </div>
                @endif

                @if($appointment->notes)
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Notes</p>
                    <p class="text-gray-800 text-sm">{{ $appointment->notes }}</p>
                </div>
                @endif
            </div>

            {{-- Status Actions --}}
            @if(!in_array($appointment->status, ['done', 'cancelled', 'no_show']))
            <div class="bg-white border border-[#e8d5f0] p-5" x-data="apptStatus()">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans] border-b border-[#e8d5f0] pb-2 mb-4">
                    Update Status
                </h2>
                <div class="flex flex-wrap gap-2">
                    @foreach([
                        'checkin'   => ['label' => 'Check In',   'color' => 'border-yellow-400 text-yellow-700 hover:bg-yellow-50'],
                        'in_chair'  => ['label' => 'In Chair',   'color' => 'border-orange-400 text-orange-700 hover:bg-orange-50'],
                        'checkout'  => ['label' => 'Check Out',  'color' => 'border-teal-400 text-teal-700 hover:bg-teal-50'],
                        'done'      => ['label' => 'Mark Done',  'color' => 'border-green-500 text-green-700 hover:bg-green-50'],
                        'no_show'   => ['label' => 'No Show',    'color' => 'border-gray-400 text-gray-500 hover:bg-gray-50'],
                        'cancelled' => ['label' => 'Cancel',     'color' => 'border-red-400 text-red-600 hover:bg-red-50'],
                    ] as $status => $meta)
                    @if($status !== $appointment->status)
                    <form method="POST"
                          action="{{ route('appointments.updateStatus', $appointment) }}"
                          @submit.prevent="submit($el)">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $status }}">
                        <button type="submit"
                                class="px-4 py-2 text-xs font-semibold uppercase tracking-widest border font-[DM_Sans] transition {{ $meta['color'] }}"
                                :disabled="loading"
                                x-text="loading && activeStatus === '{{ $status }}' ? 'Updating…' : '{{ $meta['label'] }}'">
                        </button>
                    </form>
                    @endif
                    @endforeach
                </div>
                <div x-show="success"
                     x-transition
                     class="mt-3 text-xs text-green-600 font-[DM_Sans]">
                    Status updated. Refreshing…
                </div>
            </div>
            @endif

            {{-- Add Note --}}
            <div class="bg-white border border-[#e8d5f0] p-5" x-data="apptNote()">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans] border-b border-[#e8d5f0] pb-2 mb-4">
                    Add Clinical Note
                </h2>
                <form method="POST"
                      action="{{ route('patient_notes.store', $appointment->patient) }}"
                      @submit.prevent="submit($el)">
                    @csrf
                    <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                    <textarea name="note"
                              rows="3"
                              x-model="note"
                              placeholder="Write your clinical note…"
                              class="w-full border border-[#e8d5f0] p-3 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70] resize-none"
                              required></textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit"
                                :disabled="loading || !note.trim()"
                                class="px-5 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#380740] transition disabled:opacity-40">
                            <span x-text="loading ? 'Saving…' : 'Save Note'"></span>
                        </button>
                    </div>
                </form>

                {{-- Existing Notes --}}
                @if($appointment->patient->notes->count())
                <div class="mt-4 space-y-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans]">Previous Notes</p>
                    @foreach($appointment->patient->notes()->latest()->take(5)->get() as $note)
                    <div class="border-l-2 border-[#6a0f70] pl-3 py-1">
                        <p class="text-sm text-gray-700 font-[DM_Sans]">{{ $note->note }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $note->createdBy->name ?? 'System' }} &middot;
                            {{ $note->created_at->format('d M Y, h:i A') }}
                        </p>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

        </div>

        {{-- RIGHT: Patient Info --}}
        <div class="space-y-4">
            <div class="bg-white border border-[#e8d5f0] p-5">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans] border-b border-[#e8d5f0] pb-2 mb-4">
                    Patient
                </h2>
                <div class="space-y-3 text-sm font-[DM_Sans]">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Name</p>
                        <a href="{{ route('patients.show', $appointment->patient) }}"
                           class="text-[#6a0f70] font-medium hover:underline">
                            {{ $appointment->patient->name }}
                        </a>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Phone</p>
                        <p class="text-gray-800">{{ $appointment->patient->phone }}</p>
                    </div>
                    @if($appointment->patient->date_of_birth)
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Age</p>
                        <p class="text-gray-800">
                            {{ \Carbon\Carbon::parse($appointment->patient->date_of_birth)->age }} yrs
                        </p>
                    </div>
                    @endif
                    @if($appointment->patient->medical_alert)
                    <div class="bg-red-50 border border-red-200 p-2">
                        <p class="text-xs text-red-600 font-semibold uppercase tracking-wider mb-0.5">⚠ Medical Alert</p>
                        <p class="text-xs text-red-700">{{ $appointment->patient->medical_alert }}</p>
                    </div>
                    @endif
                </div>

                <a href="{{ route('patients.show', $appointment->patient) }}"
                   class="mt-4 flex items-center gap-1 text-xs text-[#6a0f70] hover:underline font-[DM_Sans]">
                    View full profile
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

            {{-- Past Appointments --}}
            @php
                $past = \App\Models\Appointment::where('patient_id', $appointment->patient_id)
                    ->where('id', '!=', $appointment->id)
                    ->latest('appointment_date')
                    ->take(4)
                    ->get();
            @endphp
            @if($past->count())
            <div class="bg-white border border-[#e8d5f0] p-5">
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans] border-b border-[#e8d5f0] pb-2 mb-3">
                    Past Visits
                </h2>
                <div class="space-y-2">
                    @foreach($past as $past_appt)
                    <a href="{{ route('appointments.show', $past_appt) }}"
                       class="block text-xs font-[DM_Sans] hover:text-[#6a0f70] transition">
                        <span class="text-gray-700">{{ $past_appt->appointment_date->format('d M Y') }}</span>
                        <span class="text-gray-400 ml-1">— {{ str_replace('_', ' ', $past_appt->status) }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {

    Alpine.data('apptStatus', () => ({
        loading: false,
        success: false,
        activeStatus: '',
        submit(form) {
            const statusInput = form.querySelector('input[name="status"]');
            this.activeStatus = statusInput?.value;
            this.loading = true;
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => {
                if (r.ok || r.redirected) {
                    this.success = true;
                    setTimeout(() => window.location.reload(), 900);
                }
            }).catch(() => {
                this.loading = false;
            });
        }
    }));

    Alpine.data('apptNote', () => ({
        loading: false,
        note: '',
        submit(form) {
            if (!this.note.trim()) return;
            this.loading = true;
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => {
                if (r.ok || r.redirected) {
                    this.note = '';
                    setTimeout(() => window.location.reload(), 500);
                }
            }).catch(() => {
                this.loading = false;
            });
        }
    }));

});
</script>
@endpush
