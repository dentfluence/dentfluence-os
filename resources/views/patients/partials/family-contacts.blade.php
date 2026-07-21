{{--
    Family & Contacts — Patient Profile section (Phase 3, Slice 3).
    Self-contained, isolated Alpine scope. All labels/badges come from
    FamilyLinkService::linksFor(); this template renders only (no business rules).

    Expects: $patient, $familyLinks, $familyGuardians, $isMinor,
             $householdCount, $membershipFamilyName, $canEditFamily
--}}
@php
    $links         = $familyLinks ?? collect();
    $guardians     = $familyGuardians ?? collect();
    $canEdit       = $canEditFamily ?? false;
    $types         = \App\Services\Patient\FamilyLinkService::RELATIONSHIP_TYPES;
    $needsGuardian = ($isMinor ?? false) && $guardians->isEmpty();
@endphp

<div class="bg-white border border-gray-200 rounded-lg overflow-hidden" x-data="familyContacts({{ $patient->id }})">
    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
        <span class="section-title">Family &amp; Contacts</span>
        @if($canEdit)
        <button type="button" @click="toggle('add')" class="text-xs text-[#6a0f70] hover:underline font-medium">+ Add</button>
        @endif
    </div>

    <div class="p-5 space-y-4">

        @if(session('family_status'))
        <div class="text-xs text-green-700 bg-green-50 border border-green-100 rounded px-3 py-2">{{ session('family_status') }}</div>
        @endif
        @error('family')
        <div class="text-xs text-red-700 bg-red-50 border border-red-100 rounded px-3 py-2">{{ $message }}</div>
        @enderror

        {{-- ── Guardian alert (minor, no guardian) ─────────────────────────────── --}}
        @if($needsGuardian)
        <div class="flex items-start justify-between gap-3 bg-amber-50 border border-amber-200 rounded px-3 py-2.5">
            <div class="text-xs text-amber-800 leading-snug">
                <span class="font-semibold">Minor — guardian required</span><br>
                A guardian is needed for consent (DPDP).
            </div>
            @if($canEdit)
            <button type="button" @click="toggle('guardian')" class="text-xs font-medium text-amber-900 underline whitespace-nowrap flex-shrink-0">+ Add guardian</button>
            @endif
        </div>
        @endif

        {{-- ── Add family member (link existing) ────────────────────────────────── --}}
        @if($canEdit)
        <div x-show="mode==='add'" x-cloak class="border border-gray-200 rounded p-3 space-y-2">
            <div class="relative">
                <input type="text" x-model="q" @input.debounce.300ms="search()" placeholder="Search existing patient by name or phone…"
                    class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                <div x-show="results.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded shadow max-h-48 overflow-auto">
                    <template x-for="p in results" :key="p.id">
                        <button type="button" @click="pick(p)" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm">
                            <span x-text="p.name"></span>
                            <span class="text-xs text-gray-400" x-text="p.meta"></span>
                        </button>
                    </template>
                </div>
            </div>

            <form method="POST" action="{{ route('patients.family.links.store', $patient) }}" class="space-y-2" x-show="selected" x-cloak>
                @csrf
                <input type="hidden" name="linked_patient_id" :value="selected?.id">
                <div class="text-sm">Linking <span class="font-medium" x-text="selected?.name"></span> as this patient's…</div>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($types as $t)
                    <label class="cursor-pointer">
                        <input type="radio" name="relationship_type" value="{{ $t }}" class="peer sr-only" @if($t==='other') checked @endif>
                        <span class="text-xs px-2.5 py-1 rounded border border-gray-200 peer-checked:bg-[#6a0f70] peer-checked:text-white peer-checked:border-[#6a0f70]">{{ ucfirst($t) }}</span>
                    </label>
                    @endforeach
                </div>
                <label class="flex items-center gap-1.5 text-xs text-gray-600">
                    <input type="checkbox" name="as_guardian" value="1"> Also the legal guardian
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="text-xs bg-[#6a0f70] text-white px-3 py-1.5 rounded">Link</button>
                    <button type="button" @click="reset()" class="text-xs text-gray-500 px-2">Cancel</button>
                </div>
            </form>
        </div>

        {{-- ── Add guardian (link existing OR create new person) ────────────────── --}}
        <div x-show="mode==='guardian'" x-cloak class="border border-amber-200 rounded p-3 space-y-2">
            <div class="flex items-center gap-3 text-xs">
                <button type="button" @click="createNew=false" :class="!createNew ? 'text-[#6a0f70] font-medium' : 'text-gray-400'">Link existing patient</button>
                <span class="text-gray-300">|</span>
                <button type="button" @click="createNew=true" :class="createNew ? 'text-[#6a0f70] font-medium' : 'text-gray-400'">Create new person</button>
            </div>

            {{-- existing --}}
            <div x-show="!createNew">
                <div class="relative">
                    <input type="text" x-model="q" @input.debounce.300ms="search()" placeholder="Search existing patient…"
                        class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                    <div x-show="results.length" x-cloak class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded shadow max-h-48 overflow-auto">
                        <template x-for="p in results" :key="p.id">
                            <button type="button" @click="pick(p)" class="w-full text-left px-3 py-2 hover:bg-gray-50 text-sm">
                                <span x-text="p.name"></span> <span class="text-xs text-gray-400" x-text="p.meta"></span>
                            </button>
                        </template>
                    </div>
                </div>
                <form method="POST" action="{{ route('patients.family.guardians.store', $patient) }}" class="space-y-2 mt-2" x-show="selected" x-cloak>
                    @csrf
                    <input type="hidden" name="existing_patient_id" :value="selected?.id">
                    <div class="text-sm"><span class="font-medium" x-text="selected?.name"></span> as guardian</div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($types as $t)
                        <label class="cursor-pointer">
                            <input type="radio" name="relationship_type" value="{{ $t }}" class="peer sr-only" @if($t==='other') checked @endif>
                            <span class="text-xs px-2.5 py-1 rounded border border-gray-200 peer-checked:bg-amber-600 peer-checked:text-white peer-checked:border-amber-600">{{ ucfirst($t) }}</span>
                        </label>
                        @endforeach
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="text-xs bg-amber-600 text-white px-3 py-1.5 rounded">Assign guardian</button>
                        <button type="button" @click="reset()" class="text-xs text-gray-500 px-2">Cancel</button>
                    </div>
                </form>
            </div>

            {{-- new person --}}
            <form x-show="createNew" x-cloak method="POST" action="{{ route('patients.family.guardians.store', $patient) }}" class="space-y-2 mt-1">
                @csrf
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" name="name" placeholder="Guardian name" required class="text-sm border border-gray-200 px-2 py-1.5 rounded col-span-2">
                    <input type="tel" name="phone" placeholder="Phone" required class="text-sm border border-gray-200 px-2 py-1.5 rounded">
                    <select name="gender" class="text-sm border border-gray-200 px-2 py-1.5 rounded">
                        <option value="">Gender</option>
                        <option value="female">Female</option>
                        <option value="male">Male</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="number" name="age_years" placeholder="Age" min="0" max="120" class="text-sm border border-gray-200 px-2 py-1.5 rounded">
                    <select name="relationship_type" class="text-sm border border-gray-200 px-2 py-1.5 rounded">
                        @foreach($types as $t)<option value="{{ $t }}" @if($t==='other') selected @endif>{{ ucfirst($t) }}</option>@endforeach
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="text-xs bg-amber-600 text-white px-3 py-1.5 rounded">Create &amp; assign guardian</button>
                    <button type="button" @click="reset()" class="text-xs text-gray-500 px-2">Cancel</button>
                </div>
            </form>
        </div>
        @endif

        {{-- ── Emergency contact ────────────────────────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-gray-500">Emergency Contact</span>
                @if($canEdit)
                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-edit-patient', { detail: window.__editPatientPrefill }))" class="text-xs text-[#6a0f70] hover:underline">Edit</button>
                @endif
            </div>
            @if($patient->emergency_contact_name || $patient->emergency_contact_number)
            <div class="text-sm text-gray-700">
                {{ $patient->emergency_contact_name ?: '—' }}
                @if($patient->emergency_contact_relationship)<span class="text-gray-400">· {{ $patient->emergency_contact_relationship }}</span>@endif
                @if($patient->emergency_contact_number)<span class="text-gray-400">· {{ $patient->emergency_contact_number }}</span>@endif
            </div>
            @else
            <div class="text-xs text-gray-400 italic">No emergency contact.</div>
            @endif
        </div>

        {{-- ── Family members ───────────────────────────────────────────────────── --}}
        <div>
            <span class="text-xs font-semibold text-gray-500">Family ({{ $links->count() }})</span>
            <div class="mt-1">
                @forelse($links as $item)
                    @php $cp = $item['counterpart']; @endphp
                    <div class="group flex items-center gap-2 py-1.5 border-b border-gray-50 last:border-0">
                        <a href="{{ route('patients.show', $cp->id) }}" class="flex items-center gap-2 flex-1 min-w-0">
                            <span class="w-7 h-7 rounded-full bg-purple-50 text-[#6a0f70] text-[10px] font-semibold flex items-center justify-center flex-shrink-0">{{ collect(explode(' ', trim($cp->name)))->filter()->take(2)->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: '?' }}</span>
                            <span class="min-w-0">
                                <span class="text-sm text-gray-800 truncate block">{{ $cp->name }}</span>
                                <span class="text-[11px] text-gray-400">{{ ucfirst($item['label']) }}@if($cp->ageInYears() !== null) · {{ $cp->ageInYears() }} yrs @endif</span>
                            </span>
                        </a>
                        @if($item['is_guardian'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 flex-shrink-0">Guardian</span>@endif
                        @if($item['is_ward'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 flex-shrink-0">Ward</span>@endif
                        @if($cp->isMinor())<span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 flex-shrink-0">Minor</span>@endif

                        @if($canEdit)
                        <div class="relative flex-shrink-0" x-data="{ o:false }">
                            <button type="button" @click="o=!o" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-gray-600 px-1 leading-none">⋯</button>
                            <div x-show="o" @click.outside="o=false" x-cloak class="absolute right-0 mt-1 bg-white border border-gray-200 rounded shadow-lg text-xs z-20 w-44">
                                <form method="POST" action="{{ route('patients.family.links.update', [$patient, $item['link_id']]) }}" class="p-2 border-b border-gray-100 space-y-1.5">
                                    @csrf @method('PATCH')
                                    <select name="relationship_type" class="w-full border border-gray-200 rounded text-xs py-1">
                                        @foreach($types as $t)<option value="{{ $t }}" @selected($item['relationship_type']===$t)>{{ ucfirst($t) }}</option>@endforeach
                                    </select>
                                    <label class="flex items-center gap-1"><input type="checkbox" name="as_guardian" value="1" @checked($item['is_guardian'])> Guardian</label>
                                    <button type="submit" class="text-[#6a0f70] font-medium">Save relationship</button>
                                </form>
                                <form method="POST" action="{{ route('patients.family.links.destroy', [$patient, $item['link_id']]) }}" onsubmit="return confirm('Remove this family link?')" class="p-2">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600">Remove link</button>
                                </form>
                            </div>
                        </div>
                        @endif
                    </div>
                @empty
                    <div class="text-xs text-gray-400 italic">No family linked.</div>
                @endforelse
            </div>
        </div>

        {{-- ── Read-only reference chips (household · membership) ────────────────── --}}
        @if(($householdCount ?? 0) > 0 || !empty($membershipFamilyName))
        <div class="flex flex-wrap gap-1.5 pt-1">
            @if(($householdCount ?? 0) > 0)
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Shares phone with {{ $householdCount }} {{ $householdCount === 1 ? 'record' : 'records' }}</span>
            @endif
            @if(!empty($membershipFamilyName))
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">AOCP: {{ $membershipFamilyName }}</span>
            @endif
        </div>
        @endif

        {{-- ── Nominee (collapsed, DPDP) ────────────────────────────────────────── --}}
        <div x-data="{ open:false }" class="pt-1 border-t border-gray-50">
            <button type="button" @click="open=!open" class="text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1 mt-1">
                <span x-text="open ? '▾' : '▸'"></span> Legal &amp; consent (Nominee)
            </button>
            <div x-show="open" x-cloak class="mt-1.5 text-sm text-gray-700">
                @if($patient->nominee_name)
                    {{ $patient->nominee_name }}
                    @if($patient->nominee_relationship)<span class="text-gray-400">· {{ $patient->nominee_relationship }}</span>@endif
                    @if($patient->nominee_contact)<span class="text-gray-400">· {{ $patient->nominee_contact }}</span>@endif
                @else
                    <span class="text-xs text-gray-400 italic">No nominee recorded.</span>
                @endif
            </div>
        </div>

    </div>
</div>

<script>
    function familyContacts(patientId) {
        return {
            mode: null,        // null | 'add' | 'guardian'
            q: '',
            results: [],
            selected: null,
            createNew: false,
            loading: false,
            toggle(m) {
                if (this.mode === m) { this.reset(true); return; }
                this.reset(false);
                this.mode = m;
            },
            reset(closePanel = true) {
                this.q = ''; this.results = []; this.selected = null; this.createNew = false;
                if (closePanel) this.mode = null;
            },
            async search() {
                if (this.q.trim().length < 2) { this.results = []; return; }
                this.loading = true;
                try {
                    const res = await fetch('{{ route('patients.search') }}?q=' + encodeURIComponent(this.q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    this.results = (data || []).filter(p => p.id !== patientId);
                } catch (e) { this.results = []; }
                this.loading = false;
            },
            pick(p) { this.selected = p; this.results = []; this.q = p.name; },
        };
    }
</script>
