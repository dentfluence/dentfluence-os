{{--
    Component: prm.source-tag
    Props: $source (string)
--}}
@php
    $sourceMap = [
        'WhatsApp'   => ['icon' => 'ti-brand-whatsapp', 'bg' => '#E1F5EE', 'color' => '#0F6E56'],
        'Instagram'  => ['icon' => 'ti-brand-instagram', 'bg' => '#FBEAF0', 'color' => '#993556'],
        'Facebook'   => ['icon' => 'ti-brand-facebook', 'bg' => '#E6F1FB', 'color' => '#185FA5'],
        'Google'     => ['icon' => 'ti-brand-google',   'bg' => '#FAECE7', 'color' => '#993C1D'],
        'Website'    => ['icon' => 'ti-world',           'bg' => '#EEEDFE', 'color' => '#534AB7'],
        'Walk-in'    => ['icon' => 'ti-walk',            'bg' => '#F1EFE8', 'color' => '#5F5E5A'],
        'Referral'   => ['icon' => 'ti-users',           'bg' => '#FAEEDA', 'color' => '#854F0B'],
        'Call Manager'=> ['icon'=> 'ti-phone',           'bg' => '#EEEDFE', 'color' => '#534AB7'],
    ];
    $meta = $sourceMap[$source] ?? ['icon' => 'ti-circle', 'bg' => '#F1EFE8', 'color' => '#888'];
@endphp

<span class="prm-source-tag" style="background:{{ $meta['bg'] }};color:{{ $meta['color'] }}">
    <i class="ti {{ $meta['icon'] }}"></i>
    {{ $source }}
</span>
