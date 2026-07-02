{{--
|==========================================================================
| Component: platform-badge
| Usage: <x-marketing.platform-badge platform="instagram" />
|
| Props:
|   platform — 'instagram' | 'facebook' | 'google' | 'whatsapp' | 'blog' | 'wordpress'
|==========================================================================
--}}
@props(['platform' => 'blog'])

@php
$platforms = [
    'instagram' => [
        'label' => 'Instagram',
        'bg'    => 'rgba(225,48,108,0.10)',
        'color' => '#e1306c',
        'border'=> 'rgba(225,48,108,0.20)',
    ],
    'facebook' => [
        'label' => 'Facebook',
        'bg'    => 'rgba(24,119,242,0.10)',
        'color' => '#1877f2',
        'border'=> 'rgba(24,119,242,0.20)',
    ],
    'google' => [
        'label' => 'Google',
        'bg'    => 'rgba(66,133,244,0.10)',
        'color' => '#4285f4',
        'border'=> 'rgba(66,133,244,0.20)',
    ],
    'whatsapp' => [
        'label' => 'WhatsApp',
        'bg'    => 'rgba(37,211,102,0.10)',
        'color' => '#25d366',
        'border'=> 'rgba(37,211,102,0.20)',
    ],
    'blog' => [
        'label' => 'Blog',
        'bg'    => 'rgba(106,15,112,0.08)',
        'color' => '#6a0f70',
        'border'=> 'rgba(106,15,112,0.18)',
    ],
    'wordpress' => [
        'label' => 'WordPress',
        'bg'    => 'rgba(33,117,155,0.10)',
        'color' => '#21759b',
        'border'=> 'rgba(33,117,155,0.20)',
    ],
];

$p = $platforms[$platform] ?? $platforms['blog'];
@endphp

<span style="
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 9px 3px 6px;
    border-radius: 20px;
    background: {{ $p['bg'] }};
    border: 1px solid {{ $p['border'] }};
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    font-weight: 500;
    color: {{ $p['color'] }};
    white-space: nowrap;
">

    {{-- Platform icon --}}
    @if($platform === 'instagram')
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
        <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/>
        <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
    </svg>

    @elseif($platform === 'facebook')
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
        <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
    </svg>

    @elseif($platform === 'google')
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
    </svg>

    @elseif($platform === 'whatsapp')
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>

    @elseif($platform === 'wordpress')
    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
        <path d="M21.469 6.825c.694 1.279 1.09 2.747 1.09 4.31 0 3.891-2.116 7.285-5.265 9.101L21.469 6.825zM2.531 6.825L7.16 20.236c-3.149-1.816-5.265-5.21-5.265-9.101 0-1.563.396-3.031 1.636-4.31zM12 2.333c-1.5 0-2.938.284-4.251.8L12 14.4l4.251-11.267A9.555 9.555 0 0012 2.333zM2 12c0 5.523 4.477 10 10 10S22 17.523 22 12 17.523 2 12 2 2 6.477 2 12z"/>
    </svg>

    @else
    {{-- Blog / default: pencil icon --}}
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/>
        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/>
    </svg>
    @endif

    {{ $p['label'] }}
</span>
