{{-- Component: source-tag  Usage: <x-prm.source-tag :source="$lead['source']" /> --}}
@props(['source' => ''])

@php
$map = [
    'WhatsApp'     => ['icon' => 'brand-whatsapp', 'class' => 'src-wa'],
    'Instagram'    => ['icon' => 'brand-instagram', 'class' => 'src-ig'],
    'Facebook'     => ['icon' => 'brand-facebook', 'class' => 'src-fb'],
    'Google'       => ['icon' => 'brand-google',   'class' => 'src-google'],
    'Website'      => ['icon' => 'world',           'class' => 'src-web'],
    'Walk-in'      => ['icon' => 'walk',            'class' => 'src-walkin'],
    'Call Manager' => ['icon' => 'phone',           'class' => 'src-call'],
    'Referral'     => ['icon' => 'user-plus',       'class' => 'src-ref'],
    'Camp'         => ['icon' => 'map-pin',         'class' => 'src-camp'],
    'Manual'       => ['icon' => 'pencil',          'class' => 'src-manual'],
];
$item = $map[$source] ?? ['icon' => 'dots-circle-horizontal', 'class' => 'src-other'];
@endphp

<span class="source-tag {{ $item['class'] }}">
    <i class="ti ti-{{ $item['icon'] }}" aria-hidden="true"></i>
    {{ $source }}
</span>
