@extends('layouts.communication')

{{-- Phase B 1.2 (Chunk 3b) — WhatsApp conversation + reply box. --}}

@section('communication-content')
<div style="padding:20px 24px; max-width:760px; margin:0 auto;">

    {{-- Header / back --}}
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:14px;">
        <a href="{{ route('communication.whatsapp.index') }}"
           style="flex:0 0 auto; color:#6b7280; text-decoration:none; font-size:20px; line-height:1;" title="Back to inbox">
            <i class="ti ti-arrow-left"></i>
        </a>
        <div style="flex:0 0 auto; width:42px; height:42px; border-radius:50%; background:#E1F5EE; color:#0F6E56; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px;">
            {{ strtoupper(mb_substr($thread->display_name, 0, 1)) }}
        </div>
        <div style="flex:1 1 auto; min-width:0;">
            <div style="font-weight:700; font-size:15px; color:#111827;">{{ $thread->display_name }}</div>
            <div style="font-size:12px; color:#6b7280;">
                +{{ $thread->contact_phone }}
                @if($thread->patient_id)
                    · <a href="{{ url('/patients/'.$thread->patient_id) }}" style="color:#5B21B6; text-decoration:none;">View patient</a>
                @endif
            </div>
        </div>
        @if($thread->isWindowOpen())
            <span title="The patient messaged within 24h — free-text replies are allowed."
                  style="background:#DCFCE7; color:#166534; border:1px solid #BBF7D0; font-size:11px; font-weight:600; padding:3px 9px; border-radius:999px; white-space:nowrap;">
                <i class="ti ti-clock"></i> Window open
            </span>
        @else
            <span title="Outside Meta's 24-hour window — needs an approved template (coming in Chunk 4)."
                  style="background:#F3F4F6; color:#6b7280; border:1px solid #e5e7eb; font-size:11px; font-weight:600; padding:3px 9px; border-radius:999px; white-space:nowrap;">
                <i class="ti ti-clock-off"></i> Window closed
            </span>
        @endif
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div style="background:#DCFCE7; border:1px solid #BBF7D0; color:#166534; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div style="background:#FEE2E2; border:1px solid #FECACA; color:#991B1B; padding:10px 14px; border-radius:8px; font-size:13px; margin-bottom:12px;">{{ session('error') }}</div>
    @endif

    {{-- Conversation --}}
    <div style="background:#F0F2F5; border:1px solid #e5e7eb; border-radius:12px; padding:16px; min-height:280px; max-height:55vh; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
        @forelse($messages as $m)
            @php $out = $m->isOutbound(); @endphp
            <div style="display:flex; {{ $out ? 'justify-content:flex-end;' : 'justify-content:flex-start;' }}">
                <div style="max-width:78%; background:{{ $out ? '#DCF8C6' : '#fff' }}; border:1px solid {{ $out ? '#bce5a0' : '#e5e7eb' }}; border-radius:10px; padding:8px 11px;">
                    <div style="font-size:13.5px; color:#111827; white-space:pre-wrap; word-break:break-word;">{{ $m->body }}</div>
                    <div style="font-size:10.5px; color:#9ca3af; text-align:right; margin-top:3px; display:flex; gap:5px; justify-content:flex-end; align-items:center;">
                        <span>{{ optional($m->created_at)->format('d M, h:i A') }}</span>
                        @if($out)
                            @if($m->status === 'failed')
                                <span title="{{ $m->error }}" style="color:#dc2626;">✕ failed</span>
                            @elseif($m->status === 'dry_run')
                                <span style="color:#92400E;">dry-run</span>
                            @else
                                <span style="color:#16a34a;">{{ $m->status }}</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div style="text-align:center; color:#9ca3af; font-size:13px; margin:auto;">No messages in this conversation yet.</div>
        @endforelse
    </div>

    {{-- Reply box --}}
    <div style="margin-top:14px;">
        @if($gate['allowed'])
            <form method="POST" action="{{ route('communication.whatsapp.reply', $thread) }}"
                  style="display:flex; gap:8px; align-items:flex-end;">
                @csrf
                <textarea name="body" rows="2" required maxlength="4000"
                          placeholder="Type a reply…"
                          style="flex:1 1 auto; resize:vertical; border:1px solid #d1d5db; border-radius:10px; padding:10px 12px; font-size:14px; font-family:inherit;">{{ old('body') }}</textarea>
                <button type="submit"
                        style="flex:0 0 auto; background:#16a34a; color:#fff; border:none; border-radius:10px; padding:11px 18px; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <i class="ti ti-send"></i> Send
                </button>
            </form>
            @error('body')
                <div style="color:#dc2626; font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
            @if(config('whatsapp.dry_run'))
                <p style="font-size:11px; color:#92400E; margin:6px 2px 0;">Dry-run is on — your message is recorded and logged but not actually delivered.</p>
            @endif
        @else
            <div style="background:#FEF3C7; border:1px solid #FDE68A; color:#92400E; padding:12px 14px; border-radius:10px; font-size:13px;">
                <strong><i class="ti ti-lock"></i> Reply blocked.</strong>
                {{ $gate['reason'] }}
            </div>
        @endif
    </div>

    {{-- Template sender — works even when the 24h window is closed (reminders/recalls). --}}
    @if(($templateGate['allowed'] ?? false) && !empty($templates))
        @php
            $tplForJs = collect($templates)->map(fn ($t, $k) => [
                'key'   => $k,
                'label' => $t['label']  ?? $k,
                'vars'  => $t['body_vars'] ?? [],
                'sample'=> $t['sample']  ?? '',
            ])->values();
        @endphp
        <details style="margin-top:14px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:0 14px;">
            <summary style="cursor:pointer; padding:12px 0; font-size:13px; font-weight:600; color:#374151;">
                <i class="ti ti-template"></i> Send an approved template (works outside the 24-hour window)
            </summary>
            <form method="POST" action="{{ route('communication.whatsapp.template', $thread) }}"
                  x-data="{ templates: {{ \Illuminate\Support\Js::from($tplForJs) }}, selected: '', get current(){ return this.templates.find(t => t.key === this.selected) } }"
                  style="padding-bottom:14px;">
                @csrf
                <input type="hidden" name="template" :value="selected">

                <label style="display:block; font-size:12px; color:#6b7280; margin-bottom:4px;">Template</label>
                <select x-model="selected"
                        style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:9px 11px; font-size:14px; margin-bottom:10px;">
                    <option value="">— choose a template —</option>
                    <template x-for="t in templates" :key="t.key">
                        <option :value="t.key" x-text="t.label"></option>
                    </template>
                </select>

                <template x-if="current">
                    <div>
                        <template x-for="v in current.vars" :key="v">
                            <div style="margin-bottom:8px;">
                                <label style="display:block; font-size:12px; color:#6b7280; margin-bottom:3px; text-transform:capitalize;" x-text="v"></label>
                                <input type="text" :name="'vars[' + v + ']'" required maxlength="500"
                                       style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:8px 11px; font-size:14px;">
                            </div>
                        </template>
                        <p style="font-size:11px; color:#9ca3af; margin:6px 0 10px;">
                            Preview: <span x-text="current.sample"></span>
                        </p>
                        <button type="submit"
                                style="background:#0F6E56; color:#fff; border:none; border-radius:9px; padding:10px 16px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                            <i class="ti ti-send"></i> Send template
                        </button>
                    </div>
                </template>
            </form>
        </details>
    @endif
</div>
@endsection
