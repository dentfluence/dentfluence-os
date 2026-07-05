{{--
|==========================================================================
| PRE — Message Templates
| Route: GET /relationship/templates   [relationship.templates.index]
|
| Moved here from communication/templates/index.blade.php on 2026-07-06 —
| Templates conceptually belongs to PRE (Recall/Birthday copy).
| Original archived at under_review/pre_consolidation_2026_07_06/. Deep-link
| only (not a tab in relationship.layouts.app's $relTabs) — reached via gear
| icons on the Settings page.
|==========================================================================
--}}
@extends('relationship.layouts.app')
@section('page-title', 'Templates')
@section('relationship-content')
<div style="padding:8px 4px 40px;font-family:'DM Sans',sans-serif;max-width:900px;margin:0 auto;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
        <h1 style="font-size:22px;font-weight:600;color:#1f2937;margin:0;">Message Templates</h1>
        <a href="{{ route('relationship.templates.create') }}"
           style="display:inline-flex;align-items:center;gap:6px;background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:8px 16px;font-size:13px;font-weight:500;text-decoration:none;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New Template
        </a>
    </div>
    <p style="color:#9a7aaa;font-size:13px;margin:0 0 20px;">Manage WhatsApp, SMS, and Email templates used across Recall, Birthday, and other features.</p>

    @if(session('success'))
    <div style="background:#e8f7ee;border:1px solid #a3d9b8;color:#1a7a45;padding:10px 16px;border-radius:6px;font-size:13px;margin-bottom:16px;">
        {{ session('success') }}
    </div>
    @endif

    {{-- ── Type filter ── --}}
    <div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap;">
        <a href="{{ route('relationship.templates.index') }}"
           style="padding:5px 12px;border-radius:20px;font-size:12px;text-decoration:none;
                  background:{{ $typeFilter ? '#f5f0f8' : '#6a0f70' }};color:{{ $typeFilter ? '#6a0f70' : '#fff' }};">All</a>
        @foreach($types as $key => $label)
        <a href="{{ route('relationship.templates.index', ['type' => $key]) }}"
           style="padding:5px 12px;border-radius:20px;font-size:12px;text-decoration:none;
                  background:{{ $typeFilter === $key ? '#6a0f70' : '#f5f0f8' }};color:{{ $typeFilter === $key ? '#fff' : '#6a0f70' }};">{{ $label }}</a>
        @endforeach
    </div>

    @forelse($templates as $type => $group)
    <div style="margin-bottom:22px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;margin-bottom:8px;">
            {{ $types[$type] ?? ucfirst($type) }}
        </div>
        <div style="background:#fff;border:1px solid #ede4f3;border-radius:10px;overflow:hidden;">
            {{-- Column headers --}}
            <div style="display:grid;grid-template-columns:1fr 110px 90px 70px;padding:10px 18px;background:#f9f5fc;border-bottom:1px solid #ede4f3;font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">
                <div>Name</div>
                <div>Channel</div>
                <div>Active</div>
                <div style="text-align:right;">Actions</div>
            </div>
            @foreach($group as $tpl)
            <div style="display:grid;grid-template-columns:1fr 110px 90px 70px;align-items:center;padding:12px 18px;border-bottom:1px solid #f5f0f8;">
                <div>
                    <div style="font-size:13.5px;font-weight:500;color:#1a0320;">{{ $tpl->name }}</div>
                    <div style="font-size:11.5px;color:#9a7aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px;">{{ \Illuminate\Support\Str::limit($tpl->body, 60) }}</div>
                </div>
                <div>
                    <span style="font-size:10.5px;background:#f3eef7;color:#8b44aa;padding:2px 8px;border-radius:20px;text-transform:uppercase;font-weight:600;">{{ $tpl->channel }}</span>
                </div>
                <div>
                    <label class="df-toggle {{ $tpl->is_active ? 'on' : '' }}" style="pointer-events:none;">
                        <span class="df-toggle-track"></span>
                    </label>
                </div>
                <div style="display:flex;gap:6px;justify-content:flex-end;">
                    <a href="{{ route('relationship.templates.edit', $tpl->id) }}"
                       title="Edit" style="background:#f5f0f8;border-radius:5px;padding:5px 8px;color:#6a0f70;text-decoration:none;display:inline-flex;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </a>
                    <form action="{{ route('relationship.templates.destroy', $tpl->id) }}" method="POST" onsubmit="return confirm('Delete this template?')">
                        @csrf @method('DELETE')
                        <button type="submit" title="Delete" style="background:#fdeaea;border:none;border-radius:5px;padding:5px 8px;color:#b52020;cursor:pointer;display:inline-flex;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @empty
    <div style="background:#fff;border:1px solid #ede4f3;border-radius:10px;padding:40px;text-align:center;color:#9a7aaa;font-size:13px;">
        No templates yet. Click "New Template" to create one.
    </div>
    @endforelse
</div>

<style>
    .df-toggle { display:inline-flex; align-items:center; }
    .df-toggle-track { width:32px; height:18px; border-radius:10px; background:#e0d5e8; display:inline-block; position:relative; }
    .df-toggle-track::after { content:''; position:absolute; top:2px; left:2px; width:14px; height:14px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.18); }
    .df-toggle.on .df-toggle-track { background:#6a0f70; }
    .df-toggle.on .df-toggle-track::after { transform:translateX(14px); }
</style>
@endsection
