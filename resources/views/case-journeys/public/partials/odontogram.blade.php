{{-- Anatomical SVG odontogram for the patient microsite.
     Params: $upper, $lower (FDI arrays), $toothMap (tooth => data).
     Shaded tooth silhouettes by type (anterior / premolar / molar); upper arch
     roots-up, lower arch flipped. Treated teeth highlighted + tappable. --}}
@php
    // Silhouettes, viewBox 0 0 24 60 (root top, crown bottom = UPPER orientation).
    $paths = [
        'anterior' => 'M10,30 C9,18 9,7 12,3 C15,7 15,18 14,30 Z M7,31 C7,30 9,29 12,29 C15,29 17,30 17,31 C17.5,43 15.5,57 12,58 C8.5,57 6.5,43 7,31 Z',
        'premolar' => 'M10,30 C9,18 9,7 12,4 C15,7 15,18 14,30 Z M6,32 C6,30 8,29 12,29 C16,29 18,30 18,32 C19,43 17,55 12,57 C7,55 5,43 6,32 Z',
        'molar'    => 'M7,30 C6,18 5,7 8,4 C10,9 10,20 10,30 Z M17,30 C18,18 19,7 16,4 C14,9 14,20 14,30 Z M4,33 C4,31 5.2,30 6,30 C7,29.2 8,30 8.6,31 C9.2,30 10.2,29.2 11,30 C11.8,29.2 12.8,30 13.4,31 C14,30 15,29.2 16,30 C16.8,30 18,31 18,33 C19,44 17,55 12,57 C7,55 5,44 4,33 Z',
    ];
    $toothType = function (int $fdi) {
        $d = $fdi % 10;
        return $d <= 3 ? 'anterior' : ($d <= 5 ? 'premolar' : 'molar');
    };
    $renderRow = function (array $teeth, bool $flip) use ($paths, $toothType, $toothMap) {
        $html = '';
        foreach ($teeth as $i => $t) {
            if ($i === 8) $html .= '<div class="od-mid"></div>';
            $on = isset($toothMap[(string) $t]);
            $click = $on ? ' onclick="CJ.tooth(\'' . $t . '\')"' : '';
            $html .= '<div class="od-cell">'
                . '<svg class="od-tooth ' . ($on ? 'on' : '') . ($flip ? ' flip' : '') . '" viewBox="0 0 24 60"' . $click . '>'
                . '<path d="' . $paths[$toothType($t)] . '"/>'
                . '<path class="od-shine" d="' . $paths[$toothType($t)] . '"/></svg></div>';
        }
        return $html;
    };
    $renderNums = function (array $teeth) use ($toothMap) {
        $html = '';
        foreach ($teeth as $i => $t) {
            if ($i === 8) $html .= '<div class="od-mid-n"></div>';
            $on = isset($toothMap[(string) $t]);
            $html .= '<div class="od-num ' . ($on ? 'on' : '') . '">' . $t . '</div>';
        }
        return $html;
    };
@endphp

{{-- Shared gradient defs (referenced document-wide by url(#...)) --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
    <linearGradient id="odIvory" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#e7d5ac"/><stop offset=".45" stop-color="#f8f0da"/><stop offset="1" stop-color="#ecdfbe"/>
    </linearGradient>
    <linearGradient id="odBrand" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#a53db9"/><stop offset="1" stop-color="#4e0a53"/>
    </linearGradient>
    <linearGradient id="odShine" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#ffffff" stop-opacity=".55"/><stop offset=".45" stop-color="#ffffff" stop-opacity="0"/>
    </linearGradient>
</defs></svg>

<div class="odgram">
    <div class="od-row">{!! $renderRow($upper, false) !!}</div>
    <div class="od-nrow">{!! $renderNums($upper) !!}</div>
    <div class="od-split"></div>
    <div class="od-nrow">{!! $renderNums($lower) !!}</div>
    <div class="od-row">{!! $renderRow($lower, true) !!}</div>
</div>
