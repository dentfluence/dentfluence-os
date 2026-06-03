{{-- Media type badge --}}
@php
$type = $type ?? 'photo';
$tiny = $tiny ?? false;

$cfg = match($type) {
    'photo'  => ['label' => 'Photo',  'color' => '#16a34a', 'bg' => '#dcfce7'],
    'xray'   => ['label' => 'X-Ray',  'color' => '#2563eb', 'bg' => '#dbeafe'],
    'opg'    => ['label' => 'OPG',    'color' => '#7c3aed', 'bg' => '#ede9fe'],
    'cbct'   => ['label' => 'CBCT',   'color' => '#0891b2', 'bg' => '#cffafe'],
    'scan'   => ['label' => 'Scan',   'color' => '#d97706', 'bg' => '#fef3c7'],
    'video'  => ['label' => 'Video',  'color' => '#dc2626', 'bg' => '#fee2e2'],
    'pdf'    => ['label' => 'PDF',    'color' => '#374151', 'bg' => '#f3f4f6'],
    default  => ['label' => 'File',   'color' => '#6b7280', 'bg' => '#f9fafb'],
};
@endphp

@if($tiny)
<span style="position:absolute;top:4px;left:4px;padding:1px 5px;border-radius:3px;font-size:8px;font-weight:700;background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};line-height:1.4;">
    {{ $cfg['label'] }}
</span>
@else
<span style="display:inline-flex;align-items:center;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700;background:{{ $cfg['bg'] }};color:{{ $cfg['color'] }};white-space:nowrap;">
    {{ $cfg['label'] }}
</span>
@endif
