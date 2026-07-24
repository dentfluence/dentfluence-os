{{--
  WhatsApp Button Component — click-to-chat (wa.me) send.

  Two modes:

  1) DIRECT (legacy, unchanged callers):
       <x-communication.whatsapp-button :number="$phone" :message="'Hi!'" />
     Builds a wa.me link inline. Now normalizes 10-digit numbers to +91.
     No consent gate (no patient context to check against).

  2) CONTEXT (recommended for patient sends):
       <x-communication.whatsapp-button
           context="appointment_reminder"
           :patient-id="$patient->id"
           :params="['date' => '20 Jul', 'time' => '4:00 PM', 'doctor' => 'Asha']" />
     Posts to communication.whatsapp.link, which runs the DPDP consent gate,
     renders the config template, logs the contact, and returns the wa.me URL —
     which the button then opens. Message copy lives in config, not here.

  Full WhatsApp Cloud API integration is parked; swapping it in later changes
  only WhatsAppLinkService, not this component or its callers.
--}}
@props([
    'number'    => null,
    'message'   => null,
    'variant'   => 'icon',   // 'icon' | 'button'
    'label'     => 'WhatsApp',
    'context'   => null,     // set to enable consent-gated CONTEXT mode
    'patientId' => null,
    'params'    => [],
    'type'      => 'service',
])

@php
    // Direct-mode URL (India +91 normalization to match WhatsAppLinkService)
    $directUrl = null;
    if (! $context && $number) {
        $digits = preg_replace('/\D/', '', (string) $number);
        if (strlen($digits) === 10) {
            $digits = (string) config('communication.whatsapp.country_code', '91') . $digits;
        }
        $directUrl = 'https://wa.me/' . $digits
            . ($message ? '?text=' . rawurlencode($message) : '');
    }

    // When a caller supplies its own class, let it fully own the styling (so the
    // button matches the host page); otherwise fall back to the default cm styling.
    $classes = $attributes->has('class')
        ? ''
        : ($variant === 'button' ? 'cm-btn cm-btn-secondary' : 'cm-action-btn wa');
@endphp

@if($context)
    {{-- CONTEXT mode: consent-gated, resolved server-side.
         $attributes lets callers pass their own class/title to match the page. --}}
    <button type="button"
            {{ $attributes->merge(['class' => $classes, 'title' => 'Send on WhatsApp']) }}
            data-wa-endpoint="{{ route('communication.whatsapp.link') }}"
            data-wa-context="{{ $context }}"
            data-wa-type="{{ $type }}"
            @if($patientId) data-wa-patient="{{ $patientId }}" @endif
            @if($number) data-wa-phone="{{ $number }}" @endif
            @if($message) data-wa-message="{{ $message }}" @endif
            data-wa-params="{{ json_encode($params) }}"
            onclick="dfWhatsAppSend(this)">
        {{ $slot->isEmpty() ? ($variant === 'button' ? $label : '') : $slot }}
    </button>
@elseif($directUrl)
    {{-- DIRECT mode --}}
    <a href="{{ $directUrl }}" target="_blank" rel="noopener"
       {{ $attributes->merge(['class' => $classes, 'title' => 'Open WhatsApp']) }}>
        {{ $slot->isEmpty() ? ($variant === 'button' ? $label : '') : $slot }}
    </a>
@else
    <button class="cm-action-btn" disabled title="No WhatsApp number"></button>
@endif

@once
    {{-- Inlined (not @push'd) so the helper is present regardless of which
         layout renders this button and whether it exposes a scripts stack. --}}
    <script>
    /**
     * Shared click-to-chat opener. Posts the send context to the single
     * consent-gated endpoint, then opens the returned wa.me URL. Blocked sends
     * (do-not-contact / missing consent, when enforced) surface the reason.
     */
    window.dfWhatsAppSend = async function (el) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        let params = {};
        try { params = JSON.parse(el.dataset.waParams || '{}'); } catch (e) {}

        const payload = {
            context:    el.dataset.waContext,
            type:       el.dataset.waType || 'service',
            patient_id: el.dataset.waPatient || null,
            phone:      el.dataset.waPhone || null,
            message:    el.dataset.waMessage || null,
            params:     params,
        };

        el.disabled = true;
        try {
            const res = await fetch(el.dataset.waEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok && data.success && data.url) {
                window.open(data.url, '_blank', 'noopener');
            } else {
                alert(data.message || 'Could not open WhatsApp for this contact.');
            }
        } catch (e) {
            alert('Could not reach WhatsApp send. Please try again.');
        } finally {
            el.disabled = false;
        }
    };
    </script>
@endonce
