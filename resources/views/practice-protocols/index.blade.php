@extends('layouts.app')
@section('page-title', 'Practice Protocols')

@section('content')
<div style="font-family:'Inter',sans-serif;padding:24px 28px;">

    {{-- HEADER --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:700;color:#1a0320;margin:0 0 2px;">Practice Protocols</h1>
            <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Standard recurring duties, defined once per role. These generate tasks automatically.</p>
        </div>
        <a href="{{ route('practice-protocols.create') }}"
           style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;background:#6a0f70;color:#fff;border-radius:7px;font-size:13px;font-weight:500;text-decoration:none;box-shadow:0 2px 8px rgba(106,15,112,.25);">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            Add Protocol
        </a>
    </div>

    @if(session('success'))
    <div style="margin:16px 0;padding:11px 16px;background:#e8f7ef;border:1.5px solid #c8ebd8;border-radius:8px;color:#1a7a45;font-size:13px;">
        {{ session('success') }}
    </div>
    @endif

    {{-- CATALOG, grouped by role --}}
    @forelse($protocols as $roleName => $group)
    <div style="margin-top:22px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <span style="font-size:11.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#6a0f70;">{{ $roleName }}</span>
            <span style="font-size:11px;color:#9a7aaa;">· {{ $group->count() }}</span>
        </div>

        <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="background:#faf6fd;color:#7a6a85;font-size:11px;text-transform:uppercase;letter-spacing:.06em;">
                        <th style="text-align:left;padding:10px 14px;font-weight:600;">Protocol</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;">Frequency</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;">Evidence</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;">Materials</th>
                        <th style="text-align:left;padding:10px 14px;font-weight:600;">Status</th>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group as $protocol)
                    <tr style="border-top:1px solid #f3eef7;">
                        <td style="padding:11px 14px;color:#1a0320;font-weight:500;">
                            {{ $protocol->title }}
                            <span style="display:block;font-size:11px;color:#9a7aaa;font-weight:400;">{{ $protocol->categoryLabel() }}</span>
                        </td>
                        <td style="padding:11px 14px;color:#7a6a85;">
                            {{ $protocol->frequencyLabel() }}
                            @if($protocol->default_due_time)
                                <span style="color:#b0a0bb;">· {{ \Carbon\Carbon::parse($protocol->default_due_time)->format('g:i A') }}</span>
                            @endif
                        </td>
                        <td style="padding:11px 14px;">
                            @if($protocol->requires_evidence)
                            <span style="font-size:11px;color:#7a5c00;background:#fff4d6;padding:2px 8px;border-radius:4px;">Required</span>
                            @else
                            <span style="font-size:11px;color:#b0a0bb;">—</span>
                            @endif
                        </td>
                        <td style="padding:11px 14px;color:#7a6a85;">{{ $protocol->materials->count() }}</td>
                        <td style="padding:11px 14px;">
                            @if($protocol->is_active)
                            <span style="font-size:11px;color:#1a7a45;background:#e8f7ef;padding:2px 8px;border-radius:4px;">Active</span>
                            @else
                            <span style="font-size:11px;color:#9a7aaa;background:#f3eef7;padding:2px 8px;border-radius:4px;">Inactive</span>
                            @endif
                        </td>
                        <td style="padding:11px 14px;text-align:right;white-space:nowrap;">
                            <a href="{{ route('practice-protocols.edit', $protocol) }}"
                               style="font-size:12px;color:#6a0f70;text-decoration:none;font-weight:600;margin-right:10px;">Edit</a>
                            <form action="{{ route('practice-protocols.destroy', $protocol) }}" method="POST" style="display:inline;"
                                  onsubmit="return confirm('Remove this protocol? It will stop generating tasks.');">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;color:#b52020;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;padding:0;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div style="text-align:center;padding:60px 20px;color:#b0a0bb;">
        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 14px;display:block;opacity:.35;"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        <p style="font-size:14px;font-weight:500;margin:0 0 4px;">No protocols yet</p>
        <p style="font-size:12.5px;color:#c5b0d5;margin:0;">Click “Add Protocol” to define your first standard duty.</p>
    </div>
    @endforelse

</div>
@endsection
