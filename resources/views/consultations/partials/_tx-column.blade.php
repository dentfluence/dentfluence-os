{{--
    partials/_tx-column.blade.php
    Variables: $col (string), $label (string), $color (hex), $borderColor (hex), $options (string[])
    Parent Alpine scope must have: txSelected.{col}[], toggleTx(col, item), isTxSelected(col, item)
--}}
<div style="border:1px solid {{ $borderColor }};border-radius:8px;padding:14px;background:#fff;">
    <div style="font-size:11px;font-weight:700;color:{{ $color }};text-transform:uppercase;letter-spacing:.06em;
                font-family:'Inter',sans-serif;margin-bottom:10px;padding-bottom:6px;
                border-bottom:1px solid {{ $borderColor }};">
        {{ $label }}
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;">
        @foreach($options as $opt)
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:5px 6px;border-radius:5px;
                      font-size:12px;font-family:'Inter',sans-serif;"
               :style="isTxSelected('{{ $col }}', '{{ addslashes($opt) }}')
                    ? 'background:{{ $color }}18;color:{{ $color }};font-weight:600;'
                    : 'color:#374151;'"
               @click.prevent="toggleTx('{{ $col }}', '{{ addslashes($opt) }}')">
            <span :style="isTxSelected('{{ $col }}', '{{ addslashes($opt) }}')
                        ? 'width:14px;height:14px;border-radius:3px;background:{{ $color }};display:flex;align-items:center;justify-content:center;flex-shrink:0;'
                        : 'width:14px;height:14px;border-radius:3px;border:1.5px solid #d1d5db;display:flex;align-items:center;justify-content:center;flex-shrink:0;'">
                <svg x-show="isTxSelected('{{ $col }}', '{{ addslashes($opt) }}')"
                     width="9" height="9" fill="none" stroke="white" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 12 12">
                    <path d="M2 6l3 3 5-5"/>
                </svg>
            </span>
            {{ $opt }}
        </label>
        @endforeach
    </div>
</div>
