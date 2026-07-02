{{--
  WhatsApp Button Component
  Opens WhatsApp Web with optional pre-filled message.
  Usage: <x-communication.whatsapp-button :number="$item['whatsapp_number']" :message="'Hi!'" />

  IMPORTANT: No WhatsApp API. This is a simple "Open WhatsApp Web" link only.
  Full API integration is parked for future phase.
--}}
@props([
    'number'  => null,
    'message' => null,
    'variant' => 'icon',   // 'icon' | 'button'
    'label'   => 'WhatsApp',
])

@php
$url = $number
    ? 'https://wa.me/' . preg_replace('/\D/', '', $number) . ($message ? '?text=' . urlencode($message) : '')
    : '#';
@endphp

@if($number)
    @if($variant === 'button')
        <a href="{{ $url }}" target="_blank" rel="noopener"
           class="cm-btn cm-btn-secondary"
           title="Open WhatsApp">
            {{ $label }}
        </a>
    @else
        <a href="{{ $url }}" target="_blank" rel="noopener"
           class="cm-action-btn wa"
           title="Open WhatsApp">
           
        </a>
    @endif
@else
    <button class="cm-action-btn" disabled title="No WhatsApp number"></button>
@endif
