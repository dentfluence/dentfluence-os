{{--
    partials/photographs.blade.php
    Section 3 — Photographs
    Alpine state used: photos[] (array of 9), photoCount, handlePhoto(), triggerPhoto()
    Files stored via Laravel storage (photo_0 … photo_8 in FormData).
--}}
<div class="c-card" x-data="{open: {{ ($consultation && collect(range(0,8))->some(fn($i) => $consultation->{'photo_'.$i}) ) ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label">
            <span class="sec-num">3</span>Photographs
            <span style="font-size:9px;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;">Mandatory for Comprehensive</span>
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:11px;color:#9ca3af;">
                <span x-text="photoCount" style="font-weight:700;color:#6a0f70;"></span>/9
            </span>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;">
        <div class="photo-grid">
            @php
                $photoSlots = ['Extraoral','Extraoral Smile','Upper Arch','Lower Arch','Right Buccal','Left Buccal','Front','Occlusal Right','Occlusal Left'];
            @endphp

            @foreach($photoSlots as $i => $name)
            <div>
                <div class="photo-slot" :class="photos[{{ $i }}] ? 'filled' : ''" @click="triggerPhoto({{ $i }})">

                    {{-- New file preview (in-memory, from file picker) --}}
                    <template x-if="photos[{{ $i }}]">
                        <div style="width:100%;height:100%;position:relative;">
                            <img :src="photos[{{ $i }}].preview" alt="{{ $name }}">
                            <div class="photo-check">
                                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white"
                                     stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                        </div>
                    </template>

                    {{-- Existing saved photo (edit mode) --}}
                    @if($consultation && $consultation->{'photo_'.$i})
                    <template x-if="!photos[{{ $i }}]">
                        <div style="width:100%;height:100%;position:relative;">
                            <img src="{{ asset('storage/'.$consultation->{'photo_'.$i}) }}" alt="{{ $name }}">
                            <div class="photo-check">
                                <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="white"
                                     stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                            </div>
                        </div>
                    </template>
                    @else
                    <template x-if="!photos[{{ $i }}]">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d1d5db"
                             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                            <circle cx="9" cy="9" r="2"/>
                            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                        </svg>
                    </template>
                    @endif

                    <input type="file"
                           accept="image/*"
                           style="display:none;"
                           id="ph-{{ $i }}"
                           @change="handlePhoto($event, {{ $i }})">
                </div>
                <div class="photo-label">{{ $name }}</div>
            </div>
            @endforeach
        </div>

        {{-- Existing photos count info (edit mode) --}}
        @if($consultation)
        <div style="margin-top:10px;font-size:11px;color:#9ca3af;">
            Previously saved photos are shown above. Uploading a new file for a slot replaces the existing one on save.
        </div>
        @endif
    </div>
</div>
