@extends('layouts.communication')

{{-- Phase B 1.2 (Chunk 3b) — WhatsApp Inbox: list of conversations. --}}

@section('communication-content')
<x-communication.top-nav-tabs active="whatsapp" />
<div style="padding:20px 24px; max-width:900px; margin:0 auto;">

    {{-- Header --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:16px;">
        <div>
            <h1 style="font-size:20px; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                <i class="ti ti-brand-whatsapp" style="color:#25D366; font-size:24px;"></i>
                WhatsApp Inbox
            </h1>
            <p style="margin:4px 0 0; color:#6b7280; font-size:13px;">
                Two-way patient conversations.
                @if($unreadTotal > 0)
                    <strong style="color:#16a34a;">{{ $unreadTotal }} unread</strong>
                @endif
            </p>
        </div>
        @if(config('whatsapp.dry_run'))
            <span title="WHATSAPP_DRY_RUN=true — messages are simulated, not actually sent."
                  style="background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; white-space:nowrap;">
                DRY-RUN MODE
            </span>
        @endif
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div style="background:#DCFCE7; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:#FEE2E2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('error') }}</div>
    @endif

    {{-- Conversation list --}}
    <div style="background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
        @forelse($threads as $t)
            @php
                $name    = $t->display_name;
                $initial = strtoupper(mb_substr($name, 0, 1));
            @endphp
            <a href="{{ route('communication.whatsapp.show', $t) }}"
               style="display:flex; align-items:center; gap:12px; padding:14px 16px; text-decoration:none; color:inherit; border-bottom:1px solid #f1f5f9; transition:background .12s;"
               onmouseover="this.style.background='#f8fafc';" onmouseout="this.style.background='#fff';">

                {{-- Avatar --}}
                <div style="flex:0 0 auto; width:42px; height:42px; border-radius:50%; background:#E1F5EE; color:#0F6E56; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px;">
                    {{ $initial }}
                </div>

                {{-- Name + preview --}}
                <div style="flex:1 1 auto; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-weight:600; font-size:14px; color:#111827;">{{ $name }}</span>
                        @if($t->patient_id)
                            <span style="background:#EDE9FE; color:#5B21B6; font-size:10px; font-weight:600; padding:1px 7px; border-radius:999px;">Patient</span>
                        @elseif($t->lead_id)
                            <span style="background:#DBEAFE; color:#1E40AF; font-size:10px; font-weight:600; padding:1px 7px; border-radius:999px;">Lead</span>
                        @endif
                    </div>
                    <div style="font-size:12.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                        @if($t->last_direction === 'outbound')
                            <i class="ti ti-arrow-back-up" style="font-size:12px;"></i>
                        @endif
                        {{ $t->last_preview ?: $t->contact_phone }}
                    </div>
                </div>

                {{-- Time + unread --}}
                <div style="flex:0 0 auto; text-align:right;">
                    <div style="font-size:11px; color:#9ca3af;">
                        {{ optional($t->last_message_at)->diffForHumans(null, true) }}
                    </div>
                    @if($t->unread_count > 0)
                        <span style="display:inline-block; margin-top:4px; background:#16a34a; color:#fff; font-size:11px; font-weight:700; min-width:20px; padding:1px 6px; border-radius:999px;">{{ $t->unread_count }}</span>
                    @endif
                </div>
            </a>
        @empty
            <div style="padding:48px 16px; text-align:center; color:#9ca3af;">
                <i class="ti ti-message-off" style="font-size:32px;"></i>
                <p style="margin:8px 0 0; font-size:14px;">No conversations yet.</p>
                <p style="margin:2px 0 0; font-size:12px;">Inbound WhatsApp messages and replies you send will appear here.</p>
            </div>
        @endforelse
    </div>

    <div style="margin-top:14px;">
        {{ $threads->links() }}
    </div>
</div>
@endsection
