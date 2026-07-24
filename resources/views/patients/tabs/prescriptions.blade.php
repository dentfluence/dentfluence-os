{{-- ═══════════════════════════════════════════════════════════
     PRESCRIPTIONS TAB
════════════════════════════════════ --}}
<div x-show="activeTab === 'prescriptions'" style="display:none" class="w-full px-6 py-5">
    <div class="max-w-4xl mx-auto" x-data="{ activeForm: null }">

        {{-- ── Tab header ── --}}
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-800">Prescriptions</h2>
            <button @click="activeForm = (activeForm === 'new' ? null : 'new')"
                    dusk="rx-new-toggle"
                    class="text-sm px-3 py-1.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition"
                    x-text="activeForm === 'new' ? 'Cancel' : '+ New Prescription'">
            </button>
        </div>

        {{-- Medical alert banner --}}
        @if($patient->medical_alert)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex gap-2">
            <span></span>
            <span><strong>Medical Alert:</strong> {{ $patient->medical_alert }}</span>
        </div>
        @endif

        {{-- ── Inline Quick Prescription Form ── --}}
        <div x-show="activeForm === 'new'" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="mb-5">
            @include('prescriptions.partials.quick-form', [
                'patient'      => $patient,
                'prescription' => null,
                'formAction'   => route('patients.prescriptions.store', $patient),
                'formMethod'   => 'POST',
                'cancelUrl'    => null,
            ])
        </div>

        {{-- ── Past Prescriptions List ── --}}
        @if(isset($prescriptions) && $prescriptions->isNotEmpty())
        <div class="space-y-2">
            @foreach($prescriptions as $rx)
            <div class="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-4 hover:border-red-200 transition">

                {{-- Status dot --}}
                <div class="shrink-0">
                    @if(in_array($rx->status, ['issued','printed','whatsapp_sent','email_sent']))
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>
                    @elseif($rx->status === 'draft')
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>
                    @elseif($rx->status === 'cancelled')
                        <span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>
                    @elseif($rx->status === 'revised')
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400 inline-block"></span>
                    @else
                        <span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>
                    @endif
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-semibold text-brand-700">{{ $rx->prescription_number }}</span>

                        {{-- Status badge — covers all statuses --}}
                        @php
                            $statusStyle = match($rx->status) {
                                'issued'         => 'bg-green-100 text-green-700',
                                'printed'        => 'bg-teal-100 text-teal-700',
                                'whatsapp_sent'  => 'bg-lime-100 text-lime-700',
                                'email_sent'     => 'bg-sky-100 text-sky-700',
                                'draft'          => 'bg-amber-100 text-amber-700',
                                'revised'        => 'bg-purple-100 text-purple-700',
                                'cancelled'      => 'bg-red-100 text-red-500',
                                default          => 'bg-gray-100 text-gray-400',
                            };
                            $statusLabel = match($rx->status) {
                                'whatsapp_sent'  => 'WhatsApp',
                                'email_sent'     => 'Emailed',
                                default          => ucfirst($rx->status),
                            };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $statusStyle }}">
                            {{ $statusLabel }}
                        </span>

                        {{-- Source badge --}}
                        @if($rx->source)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-100">
                            {{ $rx->sourceLabel() }}
                        </span>
                        @endif

                        @if($rx->diagnosis)
                            <span class="text-xs text-gray-400 truncate max-w-xs">{{ $rx->diagnosis }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $rx->created_at->format('d M Y') }}
                        &nbsp;·&nbsp; {{ $rx->prescribedBy?->doctor_name ?? '—' }}
                        @if($rx->items && $rx->items->count())
                            &nbsp;·&nbsp; {{ $rx->items->count() }} {{ Str::plural('drug', $rx->items->count()) }}
                        @endif
                        @if($rx->print_count)
                            &nbsp;·&nbsp; <span class="text-gray-300">Printed {{ $rx->print_count }}×</span>
                        @endif
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1.5 shrink-0">
                    {{-- Print — opens print-optimised page with auto-print --}}
                    <a href="{{ route('patients.prescriptions.print', [$patient, $rx]) }}?auto=1"
                       target="_blank"
                       title="Print prescription"
                       class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition flex items-center gap-1">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print
                    </a>

                    {{-- PDF — opens same print page (user saves as PDF) --}}
                    <a href="{{ route('patients.prescriptions.pdf', [$patient, $rx]) }}"
                       target="_blank"
                       title="Save as PDF"
                       class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 hover:text-red-600 transition flex items-center gap-1">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        PDF
                    </a>

                    {{-- View --}}
                    <a href="{{ route('patients.prescriptions.show', [$patient, $rx]) }}"
                       class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                        View
                    </a>

                    {{-- Edit (always available unless cancelled) — toggles the same inline form used for New Prescription --}}
                    @if(!$rx->isCancelled())
                    <button type="button"
                            @click="activeForm = (activeForm === {{ $rx->id }} ? null : {{ $rx->id }})"
                            class="text-xs px-2.5 py-1.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition"
                            x-text="activeForm === {{ $rx->id }} ? 'Cancel' : 'Edit'">
                    </button>
                    @endif
                </div>
            </div>

            {{-- Inline edit form for this Rx — same component as "+ New Prescription" --}}
            @if(!$rx->isCancelled())
            <div x-show="activeForm === {{ $rx->id }}" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white border border-red-100 rounded-xl p-4 -mt-1">
                @include('prescriptions.partials.quick-form', [
                    'patient'      => $patient,
                    'prescription' => $rx,
                    'formAction'   => route('patients.prescriptions.update', [$patient, $rx]),
                    'formMethod'   => 'PUT',
                    'cancelUrl'    => null,
                ])
            </div>
            @endif
            @endforeach
        </div>

        @if($prescriptions->count() >= 20)
        <div class="mt-3 text-center">
            <a href="{{ route('patients.prescriptions.index', $patient) }}"
               class="text-xs text-brand-600 hover:underline">View all prescriptions →</a>
        </div>
        @endif

        @else
        <div class="text-center py-12 text-gray-400">
            <p class="text-3xl mb-2"></p>
            <p class="text-sm font-medium">No prescriptions yet</p>
            <button @click="activeForm = 'new'"
                    class="mt-3 inline-block text-sm text-red-600 hover:underline">
                Write the first prescription →
            </button>
        </div>
        @endif

    </div>
</div>{{-- /prescriptions tab --}}
