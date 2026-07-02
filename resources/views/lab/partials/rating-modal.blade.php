{{--
| Lab Case Rating Modal
| Shown on the case detail page when status = complete.
| Usage: @include('lab.partials.rating-modal', ['labCase' => $labCase])
--}}

@php
    $rating  = $labCase->rating ?? null;
    $canRate = $labCase->status === 'complete';
@endphp

@if($canRate)
<div x-data="{ open: false }" class="mt-1">

    {{-- Trigger button --}}
    @if($rating)
    <div class="flex items-center gap-2">
        <div class="flex gap-0.5 text-amber-400">
            @for($i = 1; $i <= 5; $i++)
                <span>{{ $i <= $rating->overall ? '★' : '☆' }}</span>
            @endfor
        </div>
        <span class="text-xs text-gray-500">{{ $rating->overallLabel() }}</span>
        <button @click="open = true" class="text-xs text-[#6a0f70] hover:underline ml-1">Edit Rating</button>
    </div>
    @else
    <button @click="open = true"
        class="inline-flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 text-amber-700 text-sm font-medium rounded-lg hover:bg-amber-100 transition">
        ⭐ Rate this Lab Work
    </button>
    @endif

    {{-- Modal overlay --}}
    <div x-show="open" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @keydown.escape.window="open = false">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 space-y-5"
             @click.stop>

            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Rate Lab Work</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $labCase->case_number }} · {{ $labCase->vendor?->name ?? 'Lab' }}
                    </p>
                </div>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
            </div>

            <form method="POST" action="{{ route('lab.rating.store', $labCase) }}" x-data="ratingForm()">
                @csrf

                {{-- Star raters --}}
                @php
                $clinicalFields = [
                    ['key' => 'fit',           'label' => 'Fit'],
                    ['key' => 'shade',         'label' => 'Shade Match'],
                    ['key' => 'margins',       'label' => 'Margins'],
                    ['key' => 'occlusion',     'label' => 'Occlusion'],
                    ['key' => 'quality',       'label' => 'Overall Quality'],
                ];
                $serviceFields = [
                    ['key' => 'communication', 'label' => 'Communication'],
                    ['key' => 'value',         'label' => 'Value for Money'],
                ];
                @endphp

                <div class="space-y-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Clinical</p>
                    @foreach($clinicalFields as $f)
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                        <label class="text-sm text-gray-700 w-36">{{ $f['label'] }}</label>
                        <div class="flex gap-1" x-data="{ hover: 0 }">
                            @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                @mouseenter="hover = {{ $i }}"
                                @mouseleave="hover = 0"
                                @click="scores['{{ $f['key'] }}'] = {{ $i }}"
                                class="text-xl transition"
                                :class="(hover >= {{ $i }} || (!hover && scores['{{ $f['key'] }}'] >= {{ $i }})) ? 'text-amber-400' : 'text-gray-200'">
                                ★
                            </button>
                            @endfor
                            <input type="hidden" name="{{ $f['key'] }}" :value="scores['{{ $f['key'] }}']">
                        </div>
                        <span class="text-xs text-gray-400 w-20 text-right"
                            x-text="labels[scores['{{ $f['key'] }}']] ?? ''"></span>
                    </div>
                    @endforeach
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Service</p>
                    @foreach($serviceFields as $f)
                    <div class="flex items-center justify-between py-1.5 border-b border-gray-50">
                        <label class="text-sm text-gray-700 w-36">{{ $f['label'] }}</label>
                        <div class="flex gap-1" x-data="{ hover: 0 }">
                            @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                @mouseenter="hover = {{ $i }}"
                                @mouseleave="hover = 0"
                                @click="scores['{{ $f['key'] }}'] = {{ $i }}"
                                class="text-xl transition"
                                :class="(hover >= {{ $i }} || (!hover && scores['{{ $f['key'] }}'] >= {{ $i }})) ? 'text-amber-400' : 'text-gray-200'">
                                ★
                            </button>
                            @endfor
                            <input type="hidden" name="{{ $f['key'] }}" :value="scores['{{ $f['key'] }}']">
                        </div>
                        <span class="text-xs text-gray-400 w-20 text-right"
                            x-text="labels[scores['{{ $f['key'] }}']] ?? ''"></span>
                    </div>
                    @endforeach
                </div>

                {{-- Overall --}}
                <div class="bg-gray-50 rounded-xl p-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Overall Satisfaction *</p>
                    <div class="flex gap-2 justify-center" x-data="{ hover: 0 }">
                        @for($i = 1; $i <= 5; $i++)
                        <button type="button"
                            @mouseenter="hover = {{ $i }}"
                            @mouseleave="hover = 0"
                            @click="scores['overall'] = {{ $i }}"
                            class="text-3xl transition"
                            :class="(hover >= {{ $i }} || (!hover && scores['overall'] >= {{ $i }})) ? 'text-amber-400' : 'text-gray-200'">
                            ★
                        </button>
                        @endfor
                        <input type="hidden" name="overall" :value="scores['overall']">
                    </div>
                    <p class="text-center text-sm text-amber-600 font-medium mt-1"
                        x-text="labels[scores['overall']] ?? 'Tap to rate'"></p>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="text-xs text-gray-500 mb-1 block">Comments (optional)</label>
                    <textarea name="notes" rows="2" placeholder="Any feedback for the lab…"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm resize-none focus:ring-2 focus:ring-brand-400">{{ $rating?->notes }}</textarea>
                </div>

                {{-- Pre-fill existing --}}
                @if($rating)
                <script>
                document.addEventListener('alpine:init', () => {
                    // pre-fill handled in ratingForm() init below
                });
                </script>
                @endif

                <div class="flex gap-3">
                    <button type="submit"
                        :disabled="!scores['overall']"
                        class="flex-1 py-2.5 bg-[#6a0f70] text-white text-sm font-semibold rounded-lg hover:bg-[#380740] transition disabled:opacity-40 disabled:cursor-not-allowed">
                        {{ $rating ? 'Update Rating' : 'Submit Rating' }}
                    </button>
                    <button type="button" @click="open = false"
                        class="px-4 py-2.5 border border-gray-300 text-gray-600 text-sm rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function ratingForm() {
    return {
        scores: {
            fit:           {{ $rating?->fit ?? 'null' }},
            shade:         {{ $rating?->shade ?? 'null' }},
            margins:       {{ $rating?->margins ?? 'null' }},
            occlusion:     {{ $rating?->occlusion ?? 'null' }},
            quality:       {{ $rating?->quality ?? 'null' }},
            communication: {{ $rating?->communication ?? 'null' }},
            value:         {{ $rating?->value ?? 'null' }},
            overall:       {{ $rating?->overall ?? 'null' }},
        },
        labels: {
            1: 'Poor', 2: 'Below Average', 3: 'Acceptable', 4: 'Good', 5: 'Excellent'
        },
    };
}
</script>
@endpush
@endonce
@endif
