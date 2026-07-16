{{-- One rendered education block from the block DTO. `$block` = ['block_type','title','body','media'=>[...]]. --}}
@php
    $accent = in_array($block['block_type'], ['risk','disadvantage','contraindication'], true)
        ? 'text-amber-700'
        : (in_array($block['block_type'], ['advantage'], true) ? 'text-emerald-700' : 'text-gray-800');
@endphp
<div class="space-y-1.5">
    @if (!empty($block['title']))
        <h3 class="text-sm font-semibold {{ $accent }}">{{ $block['title'] }}</h3>
    @endif

    @foreach (($block['media'] ?? []) as $media)
        @if (($media['media_type'] ?? null) === 'image')
            {{-- Placeholder-safe: real art replaces the same path later. --}}
            <div class="rounded-lg bg-gray-100 border border-dashed border-gray-200 text-[11px] text-gray-400 flex items-center justify-center h-32">
                {{ \Illuminate\Support\Str::afterLast($media['path'], '/') }}
            </div>
        @endif
    @endforeach

    @if (!empty($block['body']))
        <p class="text-sm text-gray-600 leading-relaxed">{{ $block['body'] }}</p>
    @endif
</div>
