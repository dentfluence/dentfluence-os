{{--
  Quick Actions Component
  Renders the action buttons column on each queue card.
  Usage: <x-communication.quick-actions :item="$item" />
--}}
@props(['item'])

<div class="cm-card-actions">
    {{-- Call --}}
    <a href="tel:{{ $item['phone'] ?? '' }}"
       class="cm-action-btn call"
       title="Call {{ $item['person_name'] ?? '' }}">
       
    </a>

    {{-- WhatsApp --}}
    <x-communication.whatsapp-button
        :number="$item['whatsapp_number'] ?? null"
        variant="icon" />

    {{-- Add Note --}}
    <button class="cm-action-btn"
            title="Add Note"
            onclick="openNoteModal({{ $item['id'] ?? 0 }})">
       
    </button>

    {{-- Schedule Follow-up --}}
    <button class="cm-action-btn"
            title="Schedule Follow-up"
            onclick="openFollowUpModal({{ $item['id'] ?? 0 }})">
       
    </button>

    {{-- More actions (assign, escalate, move pipeline) —
         shown in expanded dropdown --}}
    <button class="cm-action-btn"
            title="More actions"
            onclick="openActionsMenu(this, {{ $item['id'] ?? 0 }})">
        ⋯
    </button>
</div>
