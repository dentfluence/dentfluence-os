{{--
  Source Icon Component
  Usage: <x-communication.source-icon :source="$item['source']" />
  Sources: call, whatsapp, instagram, google, walkin, sms, facebook, email
--}}
@props(['source' => 'call', 'size' => 'md'])

@php
$icons = [
    'call'      => ['emoji' => '📞', 'label' => 'Call'],
    'whatsapp'  => ['emoji' => '💬', 'label' => 'WhatsApp'],
    'instagram' => ['emoji' => '📸', 'label' => 'Instagram'],
    'google'    => ['emoji' => '🔍', 'label' => 'Google Lead'],
    'walkin'    => ['emoji' => '🚶', 'label' => 'Walk-in'],
    'sms'       => ['emoji' => '✉️',  'label' => 'SMS'],
    'facebook'  => ['emoji' => '👥', 'label' => 'Facebook'],
    'email'     => ['emoji' => '📧', 'label' => 'Email'],
];

$icon = $icons[$source] ?? $icons['call'];
@endphp

<div class="cm-source-icon {{ $source }}" title="{{ $icon['label'] }}">
    {{ $icon['emoji'] }}
</div>
