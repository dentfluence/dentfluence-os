{{--
|==========================================================================
| PRE — WhatsApp (2026-07-09)
| Route: GET /relationship/whatsapp   [relationship.whatsapp]
|
| "All conversations at once" triage list, PRE-native. NOT the legacy
| /communication/whatsapp inbox — that page stays in the background
| (see feedback_pre_only_no_prm_links memory). Every row here links into
| relationship.profile?tab=communication, where the actual chat (reply box,
| template fallback) lives — built on the same WaThread data, just a
| different front door.
|
| Variables from WhatsAppOverviewController@index: $threads (paginator), $unreadTotal
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'WhatsApp')

@section('head-extra')
<style>
    .rl-page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:12px; }
    .rl-page-title { font-family:'Cormorant Garamond', Georgia, serif; font-size:26px; font-weight:600; color:#1a0320; margin:0 0 4px; }
    .rl-page-sub { font-size:13px; color:#9a7aaa; margin:0; }
</style>
@endsection

@section('relationship-content')
<div style="max-width:900px;">

    {{-- Header --}}
    <div class="rl-page-header">
        <div>
            <h1 class="rl-page-title">WhatsApp</h1>
            <p class="rl-page-sub">
                Two-way patient conversations — reply from each relationship's profile.
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

    {{-- Conversation list --}}
    <div style="background:#fff; border:1px solid #e5d8ea; border-radius:8px; overflow:hidden;">
        @forelse($threads as $t)
            @php
                $name             = $t->display_name;
                $initial          = strtoupper(mb_substr($name, 0, 1));
                $relationshipId   = $t->patient->relationship_id ?? $t->lead->relationship_id ?? null;
            @endphp

            @if($relationshipId)
                <a href="{{ route('relationship.profile', $relationshipId) }}?tab=communication"
                   style="display:flex; align-items:center; gap:12px; padding:14px 16px; text-decoration:none; color:inherit; border-bottom:1px solid #f1e8f4; transition:background .12s;"
                   onmouseover="this.style.background='#fbf5fc';" onmouseout="this.style.background='#fff';">
            @else
                {{-- Orphan thread — no patient/lead linked to a Relationship yet. Not
                     clickable (nowhere in PRE to send it), shown so it isn't silently lost. --}}
                <div style="display:flex; align-items:center; gap:12px; padding:14px 16px; opacity:0.55;">
            @endif

                <div style="flex:0 0 auto; width:42px; height:42px; border-radius:50%; background:#f0e6f2; color:#6a0f70; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px;">
                    {{ $initial }}
                </div>

                <div style="flex:1 1 auto; min-width:0;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span style="font-weight:600; font-size:14px; color:#1a0a24;">{{ $name }}</span>
                        @if($t->patient_id)
                            <span style="background:#f0e6f2; color:#6a0f70; font-size:10px; font-weight:600; padding:1px 7px; border-radius:999px;">Patient</span>
                        @elseif($t->lead_id)
                            <span style="background:#DBEAFE; color:#1E40AF; font-size:10px; font-weight:600; padding:1px 7px; border-radius:999px;">Lead</span>
                        @endif
                        @if(!$relationshipId)
                            <span style="background:#fee2e2; color:#991b1b; font-size:10px; font-weight:600; padding:1px 7px; border-radius:999px;">Not linked</span>
                        @endif
                    </div>
                    <div style="font-size:12.5px; color:#9a7aaa; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                        {{ $t->last_preview ?: $t->contact_phone }}
                    </div>
                </div>

                <div style="flex:0 0 auto; text-align:right;">
                    <div style="font-size:11px; color:#b0a4bc;">
                        {{ optional($t->last_message_at)->diffForHumans(null, true) }}
                    </div>
                    @if($t->unread_count > 0)
                        <span style="display:inline-block; margin-top:4px; background:#16a34a; color:#fff; font-size:11px; font-weight:700; min-width:20px; padding:1px 6px; border-radius:999px;">{{ $t->unread_count }}</span>
                    @endif
                </div>

            @if($relationshipId)
                </a>
            @else
                </div>
            @endif
        @empty
            <div style="padding:48px 16px; text-align:center; color:#9a7aaa;">
                <p style="margin:0; font-size:14px;">No conversations yet.</p>
                <p style="margin:2px 0 0; font-size:12px;">Inbound WhatsApp messages and replies you send will appear here.</p>
            </div>
        @endforelse
    </div>

    <div style="margin-top:14px;">
        {{ $threads->links() }}
    </div>
</div>
@endsection
